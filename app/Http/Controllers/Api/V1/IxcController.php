<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IxcConfig;
use App\Services\IxcService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IxcController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected IxcService $ixcService
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