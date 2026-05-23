<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PontuacaoAssunto;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PontuacaoAssuntoController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {

            $pontuacoes = PontuacaoAssunto::with('assunto.checklist')
                ->orderByDesc('id')
                ->get();

            return $this->successResponse($pontuacoes, 'Pontuações listadas com sucesso.');
        } catch (\Throwable $e) {
            Log::error('Erro ao listar pontuações por assunto', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro ao listar pontuações por assunto.', 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {

            $data = $request->validate([
                'id_checklist_assunto' => [
                    'required',
                    'integer',
                    'exists:checklist_assuntos,id',
                    'unique:pontuacao_assuntos,id_checklist_assunto'
                ],
                'pontos' => ['required', 'numeric', 'min:0'],
                'ativo' => ['sometimes', 'boolean'],
            ]);

            $pontuacao = PontuacaoAssunto::create([
                'id_checklist_assunto' => $data['id_checklist_assunto'],
                'pontos' => $data['pontos'],
                'ativo' => $data['ativo'] ?? true,
            ]);

            return $this->successResponse(
                $pontuacao->load('assunto.checklist'),
                'Pontuação cadastrada com sucesso.',
                201
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao cadastrar pontuação por assunto.', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro ao cadastrar pontuação por assunto.', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $pontuacao = PontuacaoAssunto::with('assunto.checklist')->findOrFail($id);

            return $this->successResponse($pontuacao, 'Pontuação encontrada com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Pontuação não encontrada.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao buscar pontuação por assunto', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao buscar pontuação.', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $pontuacao = PontuacaoAssunto::findOrFail($id);

            $data = $request->validate([
                'pontos' => ['sometimes', 'numeric', 'min:0'],
                'ativo' => ['sometimes', 'boolean'],
            ]);

            $pontuacao->update($data);

            return $this->successResponse(
                $pontuacao->load('assunto.checklist'),
                'Pontuação atualizada com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Pontuação não encontrada.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar pontuação por assunto', [
                'id' => $id,
                'payload' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao atualizar pontuação.', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $pontuacao = PontuacaoAssunto::findOrFail($id);
            $pontuacao->delete();

            return $this->successResponse(null, 'Pontuação removida com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Pontuação não encontrada.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao remover pontuação por assunto', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Erro interno ao remover pontuação.', 500);
        }
    }
}
