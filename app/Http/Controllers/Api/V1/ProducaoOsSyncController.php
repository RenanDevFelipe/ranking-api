<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChecklistAssunto;
use App\Models\Colaborador;
use App\Models\IxcConfig;
use App\Models\ProducaoOs;
use App\Services\IxcFinalizacaoAutomaticaService;
use App\Services\IxcService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProducaoOsSyncController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected IxcService $ixcService,
        protected IxcFinalizacaoAutomaticaService $finalizacaoAutomaticaService
    ) {}

    public function sync(Request $request)
    {
        try {
            $data = $request->validate([
                'data_inicio' => ['required', 'date'],
                'data_fim' => ['required', 'date', 'after_or_equal:data_inicio'],
                'id_colaborador' => ['sometimes', 'integer', 'exists:colaborador,id_colaborador'],
                'rp' => ['sometimes', 'integer', 'min:1', 'max:500'],
            ]);

            $configIxc = IxcConfig::where('ativo', true)->first();

            if (!$configIxc) {
                return $this->errorResponse('Nenhuma configuração IXC ativa encontrada.', 404);
            }

            $colaboradores = Colaborador::query()
                ->whereNotNull('id_ixc')
                ->when(!empty($data['id_colaborador']), function ($query) use ($data) {
                    $query->where('id_colaborador', $data['id_colaborador']);
                })
                ->get();

            $totalSincronizadas = 0;
            $totalFinalizacoesAutomaticas = 0;

            foreach ($colaboradores as $colaborador) {
                $result = $this->ixcService->listarOrdensServicoFinalizadasPorTecnicoFormatadas(
                    config: $configIxc,
                    idTecnico: $colaborador->id_ixc,
                    dataInicio: $data['data_inicio'],
                    dataFim: $data['data_fim'],
                    filters: [
                        'rp' => $data['rp'] ?? 500,
                    ]
                );

                if (!$result['success']) {
                    continue;
                }

                $ordens = collect($result['data']['registros'] ?? []);

                foreach ($ordens as $os) {
                    $processamento = $this->finalizacaoAutomaticaService
                        ->processarPorOrdemFinalizada($configIxc, $os);
                    $totalFinalizacoesAutomaticas += count($processamento['fechamentos']);

                    $idAssuntoIxc = $os['assunto']['id'] ?? null;

                    $assunto = ChecklistAssunto::where('id_assunto_ixc', $idAssuntoIxc)
                        ->with('pontuacao')
                        ->first();

                    $pontos = $assunto?->pontuacao?->ativo
                        ? $assunto->pontuacao->pontos
                        : 0;

                    ProducaoOs::updateOrCreate(
                        [
                            'id_os' => $os['id_os'],
                        ],
                        [
                            'id_colaborador' => $colaborador->id_colaborador,
                            'id_ixc' => $colaborador->id_ixc,
                            'id_assunto_ixc' => $idAssuntoIxc,
                            'nome_assunto_ixc' => $os['assunto']['nome'] ?? null,
                            'pontos' => $pontos,
                            'data_finalizacao' => substr($os['datas']['finalizacao'] ?? $data['data_inicio'], 0, 10),
                            'data_finalizacao_os' => $os['datas']['finalizacao'] ?? null,
                            'raw' => $os,
                        ]
                    );

                    $totalSincronizadas++;
                }
            }

            return $this->successResponse([
                'total_sincronizadas' => $totalSincronizadas,
                'total_finalizacoes_automaticas' => $totalFinalizacoesAutomaticas,
            ], 'Produção sincronizada com sucesso.');

        } catch (\Throwable $e) {
            Log::error('Erro ao sincronizar produção OS', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao sincronizar produção.', 500);
        }
    }
}
