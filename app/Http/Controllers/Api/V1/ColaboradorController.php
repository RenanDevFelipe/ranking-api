<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ColaboradorController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $colaboradores = Colaborador::with('setor')
                ->orderBy('nome_colaborador')
                ->get();

            return $this->successResponse($colaboradores);
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao listar colaboradores.',
                $e
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'id_ixc' => ['required', 'integer', 'unique:colaborador,id_ixc'],
                'nome_colaborador' => ['required', 'string', 'max:150'],
                'setor_colaborador' => ['nullable', 'integer', 'exists:setor,id_setor'],
                'url_image' => ['nullable', 'string', 'max:500'],
            ]);

            $colaborador = Colaborador::create($data);

            return $this->successResponse(
                $colaborador->load('setor'),
                'Colaborador criado com sucesso.',
                201
            );
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao criar colaborador.',
                $e
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $colaborador = Colaborador::with('setor')->findOrFail($id);

            return $this->successResponse($colaborador);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Colaborador não encontrado.');
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao buscar colaborador.',
                $e,
                ['id' => $id]
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $colaborador = Colaborador::findOrFail($id);

            $data = $request->validate([
                'id_ixc' => [
                    'sometimes',
                    'integer',
                    Rule::unique('colaborador', 'id_ixc')
                        ->ignore($colaborador->id_colaborador, 'id_colaborador')
                ],
                'nome_colaborador' => ['sometimes', 'string', 'max:150'],
                'setor_colaborador' => ['sometimes', 'nullable', 'integer', 'exists:setor,id_setor'],
                'url_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            ]);

            $colaborador->update($data);

            return $this->successResponse(
                $colaborador->load('setor'),
                'Colaborador atualizado com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Colaborador não encontrado.');
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao atualizar colaborador.',
                $e,
                ['id' => $id]
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $colaborador = Colaborador::findOrFail($id);
            $colaborador->delete();

            return $this->successResponse(null, 'Colaborador removido com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Colaborador não encontrado.');
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao remover colaborador.',
                $e,
                ['id' => $id]
            );
        }
    }
}
