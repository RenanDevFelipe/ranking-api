<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RankingConfiguracao;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RankingConfiguracaoController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        try {
            $configs = RankingConfiguracao::orderByDesc('id')->get();

            return $this->successResponse($configs, 'Configurações listadas com sucesso.');
        } catch (\Throwable $e) {
            Log::error('Erro ao listar configurações de ranking', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao listar configurações.', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'meta_pontos_os_diaria' => ['required', 'numeric', 'min:0'],
                'meta_media_avaliacoes' => ['required', 'numeric', 'min:0', 'max:10'],
                'dias_minimos_meta_mensal' => ['required', 'integer', 'min:1', 'max:31'],
                'meses_minimos_meta_anual' => ['required', 'integer', 'min:1', 'max:12'],
                'ativo' => ['sometimes', 'boolean'],
            ]);

            if (($data['ativo'] ?? false) === true) {
                RankingConfiguracao::where('ativo', true)->update(['ativo' => false]);
            }

            $config = RankingConfiguracao::create([
                'meta_pontos_os_diaria' => $data['meta_pontos_os_diaria'],
                'meta_media_avaliacoes' => $data['meta_media_avaliacoes'],
                'dias_minimos_meta_mensal' => $data['dias_minimos_meta_mensal'],
                'meses_minimos_meta_anual' => $data['meses_minimos_meta_anual'],
                'ativo' => $data['ativo'] ?? true,
            ]);

            return $this->successResponse(
                $config,
                'Configuração criada com sucesso.',
                201
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao criar configuração de ranking', [
                'payload' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao criar configuração.', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $config = RankingConfiguracao::findOrFail($id);

            return $this->successResponse($config, 'Configuração encontrada com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Configuração não encontrada.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao buscar configuração de ranking', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao buscar configuração.', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $config = RankingConfiguracao::findOrFail($id);

            $data = $request->validate([
                'meta_pontos_os_diaria' => ['sometimes', 'numeric', 'min:0'],
                'meta_media_avaliacoes' => ['sometimes', 'numeric', 'min:0', 'max:10'],
                'dias_minimos_meta_mensal' => ['sometimes', 'integer', 'min:1', 'max:31'],
                'meses_minimos_meta_anual' => ['sometimes', 'integer', 'min:1', 'max:12'],
                'ativo' => ['sometimes', 'boolean'],
            ]);

            if (($data['ativo'] ?? false) === true) {
                RankingConfiguracao::where('id', '!=', $config->id)
                    ->update(['ativo' => false]);
            }

            $config->update($data);

            return $this->successResponse(
                $config,
                'Configuração atualizada com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Configuração não encontrada.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar configuração de ranking', [
                'id' => $id,
                'payload' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao atualizar configuração.', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $config = RankingConfiguracao::findOrFail($id);
            $config->delete();

            return $this->successResponse(null, 'Configuração removida com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Configuração não encontrada.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao remover configuração de ranking', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao remover configuração.', 500);
        }
    }

    public function ativa()
    {
        try {
            $config = RankingConfiguracao::where('ativo', true)->first();

            if (!$config) {
                return $this->errorResponse('Nenhuma configuração ativa encontrada.', 404);
            }

            return $this->successResponse($config, 'Configuração ativa encontrada.');
        } catch (\Throwable $e) {
            Log::error('Erro ao buscar configuração ativa de ranking', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao buscar configuração ativa.', 500);
        }
    }
}