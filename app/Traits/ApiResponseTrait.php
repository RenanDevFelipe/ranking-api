<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;

trait ApiResponseTrait
{
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operação realizada com sucesso.',
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function errorResponse(
        string $message = 'Erro interno.',
        int $status = 500,
        mixed $data = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (!is_null($data)) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    protected function notFoundResponse(string $message = 'Registro não encontrado.'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    protected function validationErrorResponse(
        array|MessageBag $errors,
        string $message = 'Erro de validação.',
        int $status = 422
    ): JsonResponse {
        return $this->errorResponse($message, $status, ['errors' => $errors]);
    }

    protected function internalErrorResponse(
        string $message = 'Erro interno.',
        \Throwable $exception = null,
        array $context = []
    ): JsonResponse {
        if ($exception !== null) {
            Log::error($message, array_merge(['error' => $exception->getMessage()], $context));
        }

        return $this->errorResponse($message, 500);
    }
}
