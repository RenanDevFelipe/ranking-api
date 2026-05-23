<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChecklistItem;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChecklistItemController extends Controller
{
    use ApiResponseTrait;

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'id_checklist' => ['required', 'integer', 'exists:checklists,id_checklist'],
                'pergunta' => ['required', 'string', 'max:255'],
                'tipo_resposta' => ['required', 'string', 'in:sim_nao,nota,texto,numero'],
                'peso' => ['nullable', 'numeric', 'min:0'],
                'obrigatorio' => ['sometimes', 'boolean'],
                'ordem' => ['nullable', 'integer', 'min:1'],
            ]);

            $item = ChecklistItem::create([
                'id_checklist' => $data['id_checklist'],
                'pergunta' => $data['pergunta'],
                'tipo_resposta' => $data['tipo_resposta'],
                'peso' => $data['peso'] ?? 0,
                'obrigatorio' => $data['obrigatorio'] ?? true,
                'ordem' => $data['ordem'] ?? 1,
            ]);

            return $this->successResponse(
                $item,
                'Item adicionado ao checklist com sucesso.',
                201
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao criar item do checklist', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao criar item do checklist.', 500);
        }
    }

    public function show(string $id)
    {
        try {
            $item = ChecklistItem::with('checklist')->findOrFail($id);

            return $this->successResponse(
                $item,
                'Item do checklist encontrado com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Item do checklist não encontrado.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao buscar item do checklist', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao buscar item do checklist.', 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $item = ChecklistItem::findOrFail($id);

            $data = $request->validate([
                'pergunta' => ['sometimes', 'string', 'max:255'],
                'tipo_resposta' => ['sometimes', 'string', 'in:sim_nao,nota,texto,numero'],
                'peso' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'obrigatorio' => ['sometimes', 'boolean'],
                'ordem' => ['sometimes', 'nullable', 'integer', 'min:1'],
            ]);

            $item->update($data);

            return $this->successResponse(
                $item,
                'Item do checklist atualizado com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Item do checklist não encontrado.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar item do checklist', [
                'id' => $id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao atualizar item do checklist.', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $item = ChecklistItem::findOrFail($id);
            $item->delete();

            return $this->successResponse(
                null,
                'Item do checklist removido com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Item do checklist não encontrado.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao remover item do checklist', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao remover item do checklist.', 500);
        }
    }
}