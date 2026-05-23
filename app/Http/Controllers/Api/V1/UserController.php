<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        try {
            $users = User::select([
                'id_user',
                'nome_user',
                'id_ixc_user',
                'email_user',
                'role',
                'setor_user'
            ])
                ->orderBy('nome_user')
                ->get();

            return $this->successResponse($users);
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao listar usuários.',
                $e
            );
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nome_user' => ['required', 'string', 'max:255'],
                'id_ixc_user' => ['nullable', 'integer'],
                'email_user' => [
                    'required',
                    'email',
                    'max:255',
                    'unique:users,email_user'
                ],
                'senha_user' => ['required', 'string', 'min:6'],
                'role' => ['required'],
                'setor_user' => ['nullable'],
            ]);

            $user = User::create($data);

            return $this->successResponse([
                'id_user' => $user->id_user,
                'nome_user' => $user->nome_user,
                'id_ixc_user' => $user->id_ixc_user,
                'email_user' => $user->email_user,
                'role' => $user->role,
                'setor_user' => $user->setor_user,
            ], 'Usuário criado com sucesso.', 201);
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao criar usuário.',
                $e
            );
        }
    }

    public function show(string $id)
    {
        try {
            $user = User::select([
                'id_user',
                'nome_user',
                'id_ixc_user',
                'email_user',
                'role',
                'setor_user'
            ])
                ->findOrFail($id);

            return $this->successResponse($user);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Usuário não encontrado.');
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao buscar usuário.',
                $e,
                ['id' => $id]
            );
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);

            $data = $request->validate([
                'nome_user' => ['sometimes', 'string', 'max:255'],
                'id_ixc_user' => ['sometimes', 'nullable', 'integer'],
                'email_user' => [
                    'sometimes',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email_user')
                        ->ignore($user->id_user, 'id_user')
                ],
                'senha_user' => ['sometimes', 'nullable', 'string', 'min:6'],
                'role' => ['sometimes'],
                'setor_user' => ['sometimes', 'nullable'],
            ]);

            if (array_key_exists('senha_user', $data) && empty($data['senha_user'])) {
                unset($data['senha_user']);
            }

            $user->update($data);

            return $this->successResponse([
                'id_user' => $user->id_user,
                'nome_user' => $user->nome_user,
                'id_ixc_user' => $user->id_ixc_user,
                'email_user' => $user->email_user,
                'role' => $user->role,
                'setor_user' => $user->setor_user,
            ], 'Usuário atualizado com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Usuário não encontrado.');
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao atualizar usuário.',
                $e,
                ['id' => $id]
            );
        }
    }

    public function destroy(string $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->tokens()->delete();
            $user->delete();

            return $this->successResponse(null, 'Usuário removido com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Usuário não encontrado.');
        } catch (\Throwable $e) {
            return $this->internalErrorResponse(
                'Erro interno ao remover usuário.',
                $e,
                ['id' => $id]
            );
        }
    }
}
