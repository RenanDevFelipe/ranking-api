<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function login(Request $request)
    {
        $request->validate([
            'email_user' => ['required', 'email'],
            'senha_user' => ['required', 'string'],
        ]);

        $user = User::where('email_user', $request->email_user)->first();

        if (!$user || !Hash::check($request->senha_user, $user->senha_user)) {
            throw ValidationException::withMessages([
                'email_user' => ['E-mail ou senha inválidos.'],  
            ]);
        }

        $user->tokens()->delete();

        $token = $user->createToken('ranking-api-token')->plainTextToken;

        return $this->successResponse([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => [
                'id_user' => $user->id_user,
                'nome_user' => $user->nome_user,
                'email_user' => $user->email_user,
                'role' => $user->role,
                'setor_user' => $user->setor_user,
            ],
        ], 'Login realizado com sucesso.');
    }

    public function me(Request $request)
    {
        return $this->successResponse(
            ['user' => $request->user()],
            'Usuário autenticado com sucesso.'
        );
    }

    public function logout(Request $request)
    {
        return $this->successResponse(null, 'Logout realizado com sucesso.');
    }
}
