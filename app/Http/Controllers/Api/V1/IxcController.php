<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IxcConfig;
use App\Models\IxcFinalizacaoConfig;
use App\Services\IxcFinalizacaoAutomaticaService;
use App\Services\IxcService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IxcController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected IxcService $ixcService,
        protected IxcFinalizacaoAutomaticaService $finalizacaoAutomaticaService
    ) {}

    private function getConfigAtiva(): ?IxcConfig
    {
        return IxcConfig::where('ativo', true)->first();
    }

    public function ordensServico(Request $request)
    {
        try {
            $config = $this->getConfigAtiva();

            if (!$config) {
                return $this->errorResponse('Nenhuma configuração IXC ativa encontrada.', 404);
            }

            $result = $this->ixcService->listarOrdensServicoFormatadas(
                config: $config,
                filters: $request->all()
            );

            if (!$result['success']) {
                return $this->errorResponse($result['message'], $result['status']);
            }

            return $this->successResponse($result['data'], $result['message']);
        } catch (\Throwable $e) {
            Log::error('Erro ao listar ordens de serviço IXC', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao listar ordens de serviço.', 500);
        }
    }

    public function ordensServicoFinalizadasPorTecnico(Request $request)
    {
        try {
            $data = $request->validate([
                'id_tecnico' => ['required', 'integer'],
                'data_inicio' => ['required', 'date'],
                'data_fim' => ['required', 'date', 'after_or_equal:data_inicio'],
                'page' => ['sometimes', 'integer', 'min:1'],
                'rp' => ['sometimes', 'integer', 'min:1', 'max:500'],
            ]);

            $config = $this->getConfigAtiva();

            if (!$config) {
                return $this->errorResponse('Nenhuma configuração IXC ativa encontrada.', 404);
            }

            $result = $this->ixcService->listarOrdensServicoFinalizadasPorTecnicoFormatadas(
                config: $config,
                idTecnico: $data['id_tecnico'],
                dataInicio: $data['data_inicio'],
                dataFim: $data['data_fim'],
                filters: $data
            );

            if (!$result['success']) {
                return $this->errorResponse($result['message'], $result['status']);
            }

            $result['data']['finalizacoes_automaticas'] = collect($result['data']['registros'] ?? [])
                ->map(fn (array $os) => $this->finalizacaoAutomaticaService
                    ->processarPorOrdemFinalizada($config, $os))
                ->filter(fn (array $processamento) => !empty($processamento['fechamentos']))
                ->values();

            return $this->successResponse($result['data'], $result['message']);
        } catch (\Throwable $e) {
            Log::error('Erro ao listar OS finalizadas por técnico', [
                'params' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao listar OS finalizadas por técnico.', 500);
        }
    }

    public function ordemServico(string $id)
    {
        try {
            $config = $this->getConfigAtiva();

            if (!$config) {
                return $this->errorResponse('Nenhuma configuração IXC ativa encontrada.', 404);
            }

            $result = $this->ixcService->buscarOrdemServicoPorId(
                config: $config,
                id: $id
            );

            if (!$result['success']) {
                return $this->errorResponse($result['message'], $result['status']);
            }

            return $this->successResponse($result['data'], $result['message']);
        } catch (\Throwable $e) {
            Log::error('Erro ao buscar ordem de serviço IXC', [
                'id_os' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao buscar ordem de serviço.', 500);
        }
    }

    public function fecharOrdemServico(Request $request)
    {
        try {
            $data = $request->validate([
                'id_os' => ['required', 'integer'],
                'id_checklist_assunto' => ['required', 'integer'],
                'id_config_finalizacao' => ['sometimes', 'integer', 'exists:ixc_finalizacao_configs,id'],
                'id_tecnico' => ['sometimes', 'integer'],
                'id_equipe' => ['sometimes', 'integer'],
                'data_inicio' => ['sometimes', 'date'],
                'data_final' => ['sometimes', 'date'],
                'data' => ['sometimes', 'date'],
            ]);

            $config = $this->getConfigAtiva();

            if (!$config) {
                return $this->errorResponse('Nenhuma configuração IXC ativa encontrada.', 404);
            }

            $finalizacaoConfig = IxcFinalizacaoConfig::where('id_checklist_assunto', $data['id_checklist_assunto'])
                ->where('ativo', true)
                ->when(
                    isset($data['id_config_finalizacao']),
                    fn ($query) => $query->where('id', $data['id_config_finalizacao']),
                    fn ($query) => $query->whereNull('id_item_condicao')
                )
                ->first();

            if (!$finalizacaoConfig) {
                return $this->errorResponse('Nenhuma configuracao ativa encontrada para o assunto IXC.', 404);
            }

            $context = [
                'data_inicio' => $data['data_inicio'] ?? $data['data'] ?? now()->format('Y-m-d H:i:s'),
                'data_final' => $data['data_final'] ?? $data['data'] ?? now()->format('Y-m-d H:i:s'),
                'data' => $data['data'] ?? $data['data_final'] ?? now()->format('Y-m-d H:i:s'),
                'id_tecnico' => $data['id_tecnico'] ?? '',
                'id_equipe' => $data['id_equipe'] ?? '',
            ];

            $result = $this->ixcService->fecharOrdemServico(
                config: $config,
                idTicket: $data['id_os'],
                context: $context,
                payloadTemplate: $finalizacaoConfig->payloadFechamento()
            );

            if (!$result['success']) {
                return $this->errorResponse($result['message'], $result['status']);
            }

            return $this->successResponse($result['data'], 'Ordem de serviço finalizada no IXC com sucesso.');
        } catch (\Throwable $e) {
            Log::error('Erro ao fechar ordem de serviço IXC', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao fechar ordem de serviço.', 500);
        }
    }

    public function testarConexao()
    {
        try {
            $config = $this->getConfigAtiva();

            if (!$config) {
                return $this->errorResponse('Nenhuma configuração IXC ativa encontrada.', 404);
            }

            $result = $this->ixcService->listarOrdensServico(
                config: $config,
                filters: [
                    'page' => 1,
                    'rp' => 1,
                ]
            );

            if (!$result['success']) {
                return $this->errorResponse('Falha ao conectar com o IXC.', $result['status']);
            }

            return $this->successResponse([
                'config_id' => $config->id,
                'nome' => $config->nome,
                'base_url' => $config->base_url,
                'conexao' => true,
            ], 'Conexão com IXC realizada com sucesso.');
        } catch (\Throwable $e) {
            Log::error('Erro ao testar conexão IXC', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao testar conexão IXC.', 500);
        }
    }
}
