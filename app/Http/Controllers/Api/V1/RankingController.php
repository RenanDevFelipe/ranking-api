<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Services\RankingService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RankingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected RankingService $rankingService
    ) {}

    public function diario(Request $request)
    {
        try {
            $data = $request->validate([
                'data' => ['required', 'date'],
                'id_colaborador' => ['sometimes', 'integer'],
            ]);

            $configRanking = $this->rankingService->getConfiguracaoAtiva();

            $tecnicos = Colaborador::query()
                ->when(!empty($data['id_colaborador']), function ($query) use ($data) {
                    $query->where('id_colaborador', $data['id_colaborador']);
                })
                ->where('setor_colaborador', 22)
                ->whereNotNull('id_ixc')
                ->orderBy('nome_colaborador')
                ->get();

            $ranking = [];

            foreach ($tecnicos as $tecnico) {
                $producao = $this->rankingService->calcularProducaoDiaria(
                    idColaborador: $tecnico->id_colaborador,
                    data: $data['data'],
                    metaDiaria: $configRanking->meta_pontos_os_diaria
                );

                $qualidade = $this->rankingService->calcularQualidadeDiaria(
                    idColaborador: $tecnico->id_colaborador,
                    data: $data['data']
                );

                $mediaGeral = round(
                    ($producao['nota_producao'] + $qualidade['media_geral']) / 2,
                    2
                );

                $ranking[] = [
                    'id_colaborador' => $tecnico->id_colaborador,
                    'id_ixc' => $tecnico->id_ixc,
                    'nome_tecnico' => $tecnico->nome_colaborador,
                    'data' => $data['data'],

                    'producao' => $producao,

                    'qualidade' => $qualidade,

                    'ranking' => [
                        'nota_producao' => $producao['nota_producao'],
                        'nota_qualidade' => $qualidade['media_geral'],
                        'media_geral' => $mediaGeral,
                    ],
                ];
            }

            $ranking = collect($ranking)
                ->sortByDesc('ranking.media_geral')
                ->values();

            return $this->successResponse(
                $ranking,
                'Ranking diário listado com sucesso.'
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao gerar ranking diário', [
                'params' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erro interno ao gerar ranking diário.',
                500
            );
        }
    }

    public function mensal(Request $request)
    {
        try {
            $data = $request->validate([
                'mes' => ['required', 'integer', 'min:1', 'max:12'],
                'ano' => ['required', 'integer', 'min:2020'],
                'id_colaborador' => ['sometimes', 'integer'],
            ]);

            $configRanking = $this->rankingService->getConfiguracaoAtiva();

            $inicio = now()
                ->setDate($data['ano'], $data['mes'], 1)
                ->startOfMonth();

            $fim = now()
                ->setDate($data['ano'], $data['mes'], 1)
                ->endOfMonth();

            $tecnicos = Colaborador::query()
                ->when(!empty($data['id_colaborador']), function ($query) use ($data) {
                    $query->where('id_colaborador', $data['id_colaborador']);
                })
                ->where('setor_colaborador', 22)
                ->whereNotNull('id_ixc')
                ->orderBy('nome_colaborador')
                ->get();

            $rankingMensal = [];

            foreach ($tecnicos as $tecnico) {
                $item = [
                    'id_colaborador' => $tecnico->id_colaborador,
                    'id_ixc' => $tecnico->id_ixc,
                    'nome_tecnico' => $tecnico->nome_colaborador,
                    'mes' => $data['mes'],
                    'ano' => $data['ano'],

                    'dias_bateu_meta' => 0,
                    'total_os' => 0,
                    'total_pontos_producao' => 0,

                    'soma_qualidade' => 0,
                    'dias_com_qualidade' => 0,

                    'soma_media_geral' => 0,
                    'dias_com_ranking' => 0,

                    'dias' => [],
                ];

                for ($dia = $inicio->copy(); $dia->lte($fim); $dia->addDay()) {
                    $dataDia = $dia->format('Y-m-d');

                    $producao = $this->rankingService->calcularProducaoDiaria(
                        idColaborador: $tecnico->id_colaborador,
                        data: $dataDia,
                        metaDiaria: $configRanking->meta_pontos_os_diaria
                    );

                    $qualidade = $this->rankingService->calcularQualidadeDiaria(
                        idColaborador: $tecnico->id_colaborador,
                        data: $dataDia
                    );

                    $mediaGeralDia = round(
                        ($producao['nota_producao'] + $qualidade['media_geral']) / 2,
                        2
                    );

                    if ($producao['bateu_meta']) {
                        $item['dias_bateu_meta']++;
                    }

                    $item['total_os'] += $producao['total_os'];
                    $item['total_pontos_producao'] += $producao['total_pontos'];

                    if ($qualidade['media_geral'] > 0) {
                        $item['soma_qualidade'] += $qualidade['media_geral'];
                        $item['dias_com_qualidade']++;
                    }

                    if ($mediaGeralDia > 0) {
                        $item['soma_media_geral'] += $mediaGeralDia;
                        $item['dias_com_ranking']++;
                    }

                    $item['dias'][] = [
                        'data' => $dataDia,
                        'producao' => [
                            'total_os' => $producao['total_os'],
                            'total_pontos' => $producao['total_pontos'],
                            'bateu_meta' => $producao['bateu_meta'],
                            'nota_producao' => $producao['nota_producao'],
                        ],
                        'qualidade' => [
                            'media_geral' => $qualidade['media_geral'],
                            'por_setor' => $qualidade['por_setor'],
                        ],
                        'ranking' => [
                            'media_geral' => $mediaGeralDia,
                        ],
                    ];
                }

                $item['qualidade_media_mensal'] = $item['dias_com_qualidade'] > 0
                    ? round($item['soma_qualidade'] / $item['dias_com_qualidade'], 2)
                    : 0;

                $item['media_geral_mensal'] = $item['dias_com_ranking'] > 0
                    ? round($item['soma_media_geral'] / $item['dias_com_ranking'], 2)
                    : 0;

                $item['dias_minimos_meta_mensal'] = $configRanking->dias_minimos_meta_mensal;

                $item['bateu_meta_mensal'] =
                    $item['dias_bateu_meta'] >= $configRanking->dias_minimos_meta_mensal;

                unset(
                    $item['soma_qualidade'],
                    $item['dias_com_qualidade'],
                    $item['soma_media_geral'],
                    $item['dias_com_ranking']
                );

                $rankingMensal[] = $item;
            }

            $rankingMensal = collect($rankingMensal)
                ->sortByDesc('media_geral_mensal')
                ->values();

            return $this->successResponse(
                $rankingMensal,
                'Ranking mensal listado com sucesso.'
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao gerar ranking mensal', [
                'params' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erro interno ao gerar ranking mensal.',
                500
            );
        }
    }

    public function anual(Request $request)
    {
        try {
            $data = $request->validate([
                'ano' => ['required', 'integer', 'min:2020'],
                'id_colaborador' => ['sometimes', 'integer'],
            ]);

            $configRanking = $this->rankingService->getConfiguracaoAtiva();

            $tecnicos = Colaborador::query()
                ->when(!empty($data['id_colaborador']), function ($query) use ($data) {
                    $query->where('id_colaborador', $data['id_colaborador']);
                })
                ->where('setor_colaborador', 22)
                ->whereNotNull('id_ixc')
                ->orderBy('nome_colaborador')
                ->get();

            $rankingAnual = [];

            foreach ($tecnicos as $tecnico) {
                $item = [
                    'id_colaborador' => $tecnico->id_colaborador,
                    'id_ixc' => $tecnico->id_ixc,
                    'nome_tecnico' => $tecnico->nome_colaborador,
                    'ano' => $data['ano'],

                    'meses_bateu_meta' => 0,
                    'total_os' => 0,
                    'total_pontos_producao' => 0,

                    'soma_media_mensal' => 0,
                    'meses_com_ranking' => 0,

                    'meses' => [],
                ];

                for ($mes = 1; $mes <= 12; $mes++) {
                    $requestMensal = new Request([
                        'mes' => $mes,
                        'ano' => $data['ano'],
                        'id_colaborador' => $tecnico->id_colaborador,
                    ]);

                    $response = $this->mensal($requestMensal);
                    $conteudo = $response->getData(true);

                    if (!($conteudo['success'] ?? false)) {
                        continue;
                    }

                    $mensal = $conteudo['data'][0] ?? null;

                    if (!$mensal) {
                        continue;
                    }

                    if ($mensal['bateu_meta_mensal']) {
                        $item['meses_bateu_meta']++;
                    }

                    $item['total_os'] += $mensal['total_os'];
                    $item['total_pontos_producao'] += $mensal['total_pontos_producao'];

                    if (($mensal['media_geral_mensal'] ?? 0) > 0) {
                        $item['soma_media_mensal'] += $mensal['media_geral_mensal'];
                        $item['meses_com_ranking']++;
                    }

                    $item['meses'][] = [
                        'mes' => $mes,
                        'total_os' => $mensal['total_os'],
                        'total_pontos_producao' => $mensal['total_pontos_producao'],
                        'dias_bateu_meta' => $mensal['dias_bateu_meta'],
                        'bateu_meta_mensal' => $mensal['bateu_meta_mensal'],
                        'media_geral_mensal' => $mensal['media_geral_mensal'],
                        'qualidade_media_mensal' => $mensal['qualidade_media_mensal'],
                    ];
                }

                $item['media_geral_anual'] = $item['meses_com_ranking'] > 0
                    ? round($item['soma_media_mensal'] / $item['meses_com_ranking'], 2)
                    : 0;

                $item['meses_minimos_meta_anual'] = $configRanking->meses_minimos_meta_anual;

                $item['bateu_meta_anual'] =
                    $item['meses_bateu_meta'] >= $configRanking->meses_minimos_meta_anual;

                unset(
                    $item['soma_media_mensal'],
                    $item['meses_com_ranking']
                );

                $rankingAnual[] = $item;
            }

            $rankingAnual = collect($rankingAnual)
                ->sortByDesc('media_geral_anual')
                ->values();

            return $this->successResponse(
                $rankingAnual,
                'Ranking anual listado com sucesso.'
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao gerar ranking anual', [
                'params' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erro interno ao gerar ranking anual.',
                500
            );
        }
    }
}
