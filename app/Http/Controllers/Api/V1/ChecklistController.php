<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChecklistController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {

            $checklists = Checklist::with(['itens', 'assuntos'])
                ->orderBy('nome_checklist')
                ->get();

            return $this->successResponse(
                $checklists,
                'Checklists listados com sucesso.'
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao listar chekclists', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao listar checklists.', 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nome_checklist' => ['required', 'string', 'max:150'],
                'ativo' => ['sometimes', 'boolean'],

                'itens' => ['required', 'array', 'min:1'],
                'itens.*.pergunta' => ['required', 'string', 'max:255'],
                'itens.*.tipo_resposta' => ['required', 'string', 'in:sim_nao,nota,texto,numero'],
                'itens.*.peso' => ['nullable', 'numeric', 'min:0'],
                'itens.*.obrigatorio' => ['sometimes', 'boolean'],
                'itens.*.ordem' => ['nullable', 'integer', 'min:1'],
            ]);

            $checklist = DB::transaction(function () use ($data) {
                $checklist = Checklist::create([
                    'nome_checklist' => $data['nome_checklist'],
                    'ativo' => $data['ativo'] ?? true,
                ]);

                foreach ($data['itens'] as $index => $item) {
                    $checklist->itens()->create([
                        'pergunta' => $item['pergunta'],
                        'tipo_resposta' => $item['tipo_resposta'],
                        'peso' => $item['peso'] ?? 0,
                        'obrigatorio' => $item['obrigatorio'] ?? true,
                        'ordem' => $item['ordem'] ?? ($index + 1),
                    ]);
                }

                return $checklist;
            });

            return $this->successResponse(
                $checklist->load(['itens', 'assuntos']),
                'Checklist criado com sucesso.',
                201
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao criar checklist', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao criar checklist.', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {

            $checklist = Checklist::with([
                'itens' => fn($query) => $query->orderBy('ordem'),
                'assuntos'
            ])
                ->findOrFail($id);

            return $this->successResponse(
                $checklist,
                'Checklist encontrado com sucesso.'
            );
        } catch (ModelNotFoundException) {

            return $this->notFoundResponse('Checklist não encontrado');
        } catch (\Throwable $e) {
            Log::error('Erro ao listar checklist.', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao listar checklist', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $checklist = Checklist::findOrFail($id);

            $data = $request->validate([
                'nome_checklist' => ['sometimes', 'string', 'max:150'],
                'ativo' => ['sometimes', 'boolean'],
            ]);

            $checklist->update($data);

            return $this->successResponse(
                $checklist->load(['itens', 'assuntos']),
                'Checklist atualizado com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Checklist não encontrado.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar checklist', [
                'id' => $id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao atualizar checklist.', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $checklist = Checklist::findOrFail($id);

            DB::transaction(function () use ($checklist) {
                $checklist->assuntos()->delete();
                $checklist->itens()->delete();
                $checklist->delete();
            });

            return $this->successResponse(
                null,
                'Checklist removido com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Checklist não encontrado.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao remover checklist', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao remover checklist.', 500);
        }
    }
}
