<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IxcConfig;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IxcConfigController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        try {
            return $this->successResponse(
                IxcConfig::orderByDesc('id')->get(),
                'Configurações IXC listadas com sucesso.'
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao listar configurações IXC', ['error' => $e->getMessage()]);
            return $this->errorResponse('Erro interno ao listar configurações IXC.', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nome' => ['required', 'string', 'max:100'],
                'base_url' => ['required', 'url', 'max:255'],
                'token' => ['required', 'string'],
                'ativo' => ['sometimes', 'boolean'],
            ]);

            if (($data['ativo'] ?? false) === true) {
                IxcConfig::where('ativo', true)->update(['ativo' => false]);
            }

            $config = IxcConfig::create($data);

            return $this->successResponse(
                $config,
                'Configuração IXC criada com sucesso.',
                201
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao criar configuração IXC', ['error' => $e->getMessage()]);
            return $this->errorResponse('Erro interno ao criar configuração IXC.', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $config = IxcConfig::findOrFail($id);

            return $this->successResponse(
                $config,
                'Configuração IXC encontrada com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Configuração IXC não encontrada.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao buscar configuração IXC', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao buscar configuração IXC.', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $config = IxcConfig::findOrFail($id);

            $data = $request->validate([
                'nome' => ['sometimes', 'string', 'max:100'],
                'base_url' => ['sometimes', 'url', 'max:255'],
                'token' => ['sometimes', 'string'],
                'ativo' => ['sometimes', 'boolean'],
            ]);

            if (($data['ativo'] ?? false) === true) {
                IxcConfig::where('id', '!=', $config->id)
                    ->update(['ativo' => false]);
            }

            $config->update($data);

            return $this->successResponse(
                $config,
                'Configuração IXC atualizada com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Configuração IXC não encontrada.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar configuração IXC', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao atualizar configuração IXC.', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $config = IxcConfig::findOrFail($id);
            $config->delete();

            return $this->successResponse(
                null,
                'Configuração IXC removida com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Configuração IXC não encontrada.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao remover configuração IXC', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao remover configuração IXC.', 500);
        }
    }
}