<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $roles = Role::orderBy('nome_role')->get();

            return $this->successResponse($roles);
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao listar roles.',
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
                'nome_role' => [
                    'required',
                    'string',
                    'max:15',
                    'unique:roles,nome_role'
                ],
            ]);

            $role = Role::create($data);

            return $this->successResponse($role, 'Role criada com sucesso.', 201);
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao criar role.',
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
            $role = Role::findOrFail($id);

            return $this->successResponse($role);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Role não encontrada.');
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao buscar role.',
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
            $role = Role::findOrFail($id);

            $data = $request->validate([
                'nome_role' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:15',
                    Rule::unique('roles', 'nome_role')->ignore($role->id_role, 'id_role')
                ],
            ]);

            $role->update($data);

            return $this->successResponse($role, 'Role atualizada com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Role não encontrada.');
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao atualizar role.',
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
            $role = Role::findOrFail($id);
            $role->delete();

            return $this->successResponse(null, 'Role removida com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Role não encontrada.');
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao remover role.',
                $e,
                ['id' => $id]
            );
        }
    }
}
