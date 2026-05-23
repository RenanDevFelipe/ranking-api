<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HistoricoEstoque;
use App\Models\HistoricoN2;
use App\Models\HistoricoRh;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HistoricoController extends Controller
{
    use ApiResponseTrait;

    public function n2(Request $request)
    {
        return $this->listarHistorico($request, HistoricoN2::class, 'Historico listado com sucesso.');
    }

    public function rh(Request $request)
    {
        return $this->listarHistorico($request, HistoricoRh::class, 'Histórico listado com sucesso.');
    }

    public function estoque(Request $request)
    {
        return $this->listarHistorico($request, HistoricoEstoque::class, 'Histórico listado com sucesso');
    }

    private function listarHistorico(Request $request, string $model, string $message)
    {
        try {

            $query = $model::with(['tecnico', 'usuario']);

            if ($request->filled('id_tecnico')) {
                $query->where('id_tecnico', $request->id_tecnico);
            }

            if ($request->filled('tipo_movimentacao')) {
                $query->where('tipo_movimentacao', $request->tipo_movimentacao);
            }

            if ($request->filled('campo')) {
                $query->where('campo', $request->campo);
            }

            if ($request->filled('data_inicio') && $request->filled('data_fim')) {
                $query->whereBetween('data_referencia', [
                    $request->data_inicio,
                    $request->data_fim,
                ]);
            }

            $historicos = $query
                ->orderByDesc('created_at')
                ->paginate($request->get('per_page', 20));

            return $this->successResponse($historicos, $message);
        } catch (\Throwable $e) {
            Log::error('Erro ao listar histórico', [
                'model' => $model,
                'params' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao listar histórico', 500);
        }
    }
}
