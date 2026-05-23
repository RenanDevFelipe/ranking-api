<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setor;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SetorController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $setores = Setor::orderBy('nome_setor')->get();

            return $this->successResponse($setores);
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao listar setores.',
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
                'nome_setor' => [
                    'required',
                    'string',
                    'max:100',
                    'unique:setor,nome_setor'
                ],
            ]);

            $setor = Setor::create($data);

            return $this->successResponse($setor, 'Setor criado com sucesso.', 201);
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao criar setor.',
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
            $setor = Setor::findOrFail($id);

            return $this->successResponse($setor);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Setor não encontrado.');
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao buscar setor.',
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
            $setor = Setor::findOrFail($id);

            $data = $request->validate([
                'nome_setor' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('setor', 'nome_setor')->ignore($setor->id_setor, 'id_setor')
                ],
            ]);

            $setor->update($data);

            return $this->successResponse($setor, 'Setor atualizado com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Setor não encontrado.');
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao atualizar setor.',
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
            $setor = Setor::findOrFail($id);
            $setor->delete();

            return $this->successResponse(null, 'Setor removido com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Setor não encontrado.');
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao remover setor.',
                $e,
                ['id' => $id]
            );
        }
    }
}
