<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\ProducaoOs;
use App\Services\RankingService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected RankingService $rankingService
    ) {}

    public function resumo(Request $request)
    {
        try {
            $data = $request->validate([
                'data' => ['sometimes', 'date'],
            ]);

            $dataReferencia = $data['data'] ?? now()->format('Y-m-d');

            $config = $this->rankingService->getConfiguracaoAtiva();

            $tecnicosAtivos = Colaborador::whereNotNull('id_ixc')->count();

            $osHoje = ProducaoOs::whereDate('data_finalizacao', $dataReferencia)->count();

            $pontosHoje = (float) ProducaoOs::whereDate('data_finalizacao', $dataReferencia)
                ->sum('pontos');

            $tecnicos = Colaborador::whereNotNull('id_ixc')->get();

            $somaQualidade = 0;
            $qtdQualidade = 0;
            $tecnicosBateramMeta = 0;

            foreach ($tecnicos as $tecnico) {
                $producao = $this->rankingService->calcularProducaoDiaria(
                    idColaborador: $tecnico->id_colaborador,
                    data: $dataReferencia,
                    metaDiaria: $config->meta_pontos_os_diaria
                );

                if ($producao['bateu_meta']) {
                    $tecnicosBateramMeta++;
                }

                $qualidade = $this->rankingService->calcularQualidadeDiaria(
                    idColaborador: $tecnico->id_colaborador,
                    data: $dataReferencia
                );

                if ($qualidade['media_geral'] > 0) {
                    $somaQualidade += $qualidade['media_geral'];
                    $qtdQualidade++;
                }
            }

            $mediaQualidadeHoje = $qtdQualidade > 0
                ? round($somaQualidade / $qtdQualidade, 2)
                : 0;

            return $this->successResponse([
                'data' => $dataReferencia,
                'tecnicos_ativos' => $tecnicosAtivos,
                'os_finalizadas' => $osHoje,
                'pontos_producao' => $pontosHoje,
                'media_qualidade' => $mediaQualidadeHoje,
                'tecnicos_bateram_meta' => $tecnicosBateramMeta,
                'meta_diaria' => $config->meta_pontos_os_diaria,
                'meta_media_avaliacoes' => $config->meta_media_avaliacoes,
            ], 'Resumo do dashboard carregado com sucesso.');

        } catch (\Throwable $e) {
            Log::error('Erro ao carregar resumo do dashboard', [
                'params' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao carregar dashboard.', 500);
        }
    }

    public function topAssuntos(Request $request)
    {
        try {
            $data = $request->validate([
                'data_inicio' => ['required', 'date'],
                'data_fim' => ['required', 'date', 'after_or_equal:data_inicio'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            ]);

            $limit = $data['limit'] ?? 10;

            $assuntos = ProducaoOs::selectRaw('
                    id_assunto_ixc,
                    nome_assunto_ixc,
                    COUNT(*) as total_os,
                    SUM(pontos) as total_pontos
                ')
                ->whereBetween('data_finalizacao', [$data['data_inicio'], $data['data_fim']])
                ->groupBy('id_assunto_ixc', 'nome_assunto_ixc')
                ->orderByDesc('total_os')
                ->limit($limit)
                ->get();

            return $this->successResponse(
                $assuntos,
                'Top assuntos carregado com sucesso.'
            );

        } catch (\Throwable $e) {
            Log::error('Erro ao carregar top assuntos', [
                'params' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao carregar top assuntos.', 500);
        }
    }

    public function producaoPorDia(Request $request)
    {
        try {
            $data = $request->validate([
                'data_inicio' => ['required', 'date'],
                'data_fim' => ['required', 'date', 'after_or_equal:data_inicio'],
                'id_colaborador' => ['sometimes', 'integer'],
            ]);

            $query = ProducaoOs::selectRaw('
                    data_finalizacao,
                    COUNT(*) as total_os,
                    SUM(pontos) as total_pontos
                ')
                ->whereBetween('data_finalizacao', [$data['data_inicio'], $data['data_fim']]);

            if (!empty($data['id_colaborador'])) {
                $query->where('id_colaborador', $data['id_colaborador']);
            }

            $producao = $query
                ->groupBy('data_finalizacao')
                ->orderBy('data_finalizacao')
                ->get();

            return $this->successResponse(
                $producao,
                'Produção por dia carregada com sucesso.'
            );

        } catch (\Throwable $e) {
            Log::error('Erro ao carregar produção por dia', [
                'params' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao carregar produção por dia.', 500);
        }
    }
}