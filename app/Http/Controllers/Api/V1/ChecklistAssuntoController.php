<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChecklistAssunto;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ChecklistAssuntoController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        try {
            $query = ChecklistAssunto::with(['checklist', 'finalizacaoIxc']);

            if ($request->filled('id_checklist')) {
                $query->where('id_checklist', $request->id_checklist);
            }

            if ($request->filled('id_assunto_ixc')) {
                $query->where('id_assunto_ixc', $request->id_assunto_ixc);
            }

            $assuntos = $query
                ->orderBy('nome_assunto_ixc')
                ->get();

            return $this->successResponse(
                $assuntos,
                'Assuntos vinculados listados com sucesso.'
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao listar assuntos vinculados ao checklist', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erro interno ao listar assuntos vinculados.',
                500
            );
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'id_checklist' => [
                    'required',
                    'integer',
                    'exists:checklists,id_checklist'
                ],
                'id_assunto_ixc' => [
                    'required',
                    'integer',
                    'unique:checklist_assuntos,id_assunto_ixc'
                ],
                'nome_assunto_ixc' => [
                    'required',
                    'string',
                    'max:255'
                ],
            ]);

            $assunto = ChecklistAssunto::create($data);

            return $this->successResponse(
                $assunto->load(['checklist', 'finalizacaoIxc']),
                'Assunto vinculado ao checklist com sucesso.',
                201
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao vincular assunto IXC ao checklist', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erro interno ao vincular assunto ao checklist.',
                500
            );
        }
    }

    public function show(string $id)
    {
        try {
            $assunto = ChecklistAssunto::with(['checklist', 'finalizacaoIxc'])->findOrFail($id);

            return $this->successResponse(
                $assunto,
                'Assunto vinculado encontrado com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'Assunto vinculado não encontrado.',
                404
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao buscar assunto vinculado ao checklist', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erro interno ao buscar assunto vinculado.',
                500
            );
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $assunto = ChecklistAssunto::findOrFail($id);

            $data = $request->validate([
                'id_checklist' => [
                    'sometimes',
                    'integer',
                    'exists:checklists,id_checklist'
                ],
                'id_assunto_ixc' => [
                    'sometimes',
                    'integer',
                    Rule::unique('checklist_assuntos', 'id_assunto_ixc')
                        ->ignore($assunto->id)
                ],
                'nome_assunto_ixc' => [
                    'sometimes',
                    'string',
                    'max:255'
                ],
            ]);

            $assunto->update($data);

            return $this->successResponse(
                $assunto->load(['checklist', 'finalizacaoIxc']),
                'Vínculo de assunto atualizado com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'Assunto vinculado não encontrado.',
                404
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar vínculo de assunto IXC', [
                'id' => $id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erro interno ao atualizar vínculo de assunto.',
                500
            );
        }
    }

    public function destroy(string $id)
    {
        try {
            $assunto = ChecklistAssunto::findOrFail($id);
            $assunto->delete();

            return $this->successResponse(
                null,
                'Vínculo de assunto removido com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'Assunto vinculado não encontrado.',
                404
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao remover vínculo de assunto IXC', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erro interno ao remover vínculo de assunto.',
                500
            );
        }
    }

    public function buscarPorAssuntoIxc(string $idAssuntoIxc)
    {
        try {
            $assunto = ChecklistAssunto::with([
                    'checklist.itens' => fn ($query) => $query->orderBy('ordem'),
                    'finalizacaoIxc',
                ])
                ->where('id_assunto_ixc', $idAssuntoIxc)
                ->first();

            if (!$assunto) {
                return $this->errorResponse(
                    'Nenhum checklist vinculado a este assunto IXC.',
                    404
                );
            }

            return $this->successResponse(
                $assunto,
                'Checklist encontrado para o assunto IXC.'
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao buscar checklist por assunto IXC', [
                'id_assunto_ixc' => $idAssuntoIxc,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erro interno ao buscar checklist do assunto.',
                500
            );
        }
    }
}
