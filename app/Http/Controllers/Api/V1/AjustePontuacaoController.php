<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AvaliacaoN2;
use App\Models\AvaliacaoRh;
use App\Models\AvaliacaoEstoque;
use App\Models\Colaborador;
use App\Models\HistoricoN2;
use App\Models\HistoricoRh;
use App\Models\HistoricoEstoque;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AjustePontuacaoController extends Controller
{
    use ApiResponseTrait;

    public function ajustarN2(Request $request)
    {
        return $this->ajustarPontuacao($request, [
            'model' => AvaliacaoN2::class,
            'historico' => HistoricoN2::class,
            'data_coluna' => 'data_finalizacao',
            'tecnico_coluna' => 'id_tecnico_n2',
            'campos' => [
                'ponto_finalizacao_os',
                'ponto_lavagem_carro',
                'organizacao_material',
                'ponto_fardamento',
            ],
        ]);
    }

    public function ajustarRh(Request $request)
    {
        return $this->ajustarPontuacao($request, [
            'model' => AvaliacaoRh::class,
            'historico' => HistoricoRh::class,
            'data_coluna' => 'data_avaliacao',
            'tecnico_coluna' => 'id_tecnico',
            'campos' => [
                'pnt_ponto',
                'pnt_atestado',
                'pnt_falta',
            ],
        ]);
    }

    public function ajustarEstoque(Request $request)
    {
        return $this->ajustarPontuacao($request, [
            'model' => AvaliacaoEstoque::class,
            'historico' => HistoricoEstoque::class,
            'data_coluna' => 'data_finalizacao',
            'tecnico_coluna' => 'id_tecnico_estoque',
            'campos' => [
                'pnt_pedido',
                'pnt_prazo',
                'pnt_etiqueta',
                'pnt_baixa_mat',
                'pnt_troca_equip',
                'pnt_transferencia',
            ],
        ]);
    }

    private function ajustarPontuacao(Request $request, array $config)
    {
        try {
            $data = $request->validate([
                'id_tecnico' => ['required', 'integer'],
                'data_referencia' => ['required', 'date'],
                'campo' => ['required', 'string', Rule::in($config['campos'])],
                'pontos' => ['required', 'numeric', 'min:0.01'],
                'tipo_movimentacao' => ['required', 'string', Rule::in(['REMOCAO', 'DEVOLUCAO'])],
                'observacao' => ['required', 'string', 'max:500'],
            ]);

            $resultado = DB::transaction(function () use ($data, $request, $config) {
                $avaliacao = $config['model']::where($config['tecnico_coluna'], $data['id_tecnico'])
                    ->whereDate($config['data_coluna'], $data['data_referencia'])
                    ->lockForUpdate()
                    ->first();

                if (!$avaliacao) {
                    return [
                        'erro' => true,
                        'status' => 404,
                        'message' => 'Avaliação não encontrada para este técnico nesta data.',
                    ];
                }

                $campo = $data['campo'];
                $valorAnterior = (float) $avaliacao->{$campo};
                $pontos = (float) $data['pontos'];

                $valorMovimentado = $data['tipo_movimentacao'] === 'REMOCAO'
                    ? -abs($pontos)
                    : abs($pontos);

                $valorAtual = max(0, $valorAnterior + $valorMovimentado);

                $avaliacao->update([
                    $campo => $valorAtual,
                ]);

                $tecnico = Colaborador::where('id_colaborador', $data['id_tecnico'])->first();

                $historico = $config['historico']::create([
                    'nome_avaliador' => $request->user()->nome_user ?? 'Sistema',
                    'data_avaliacao' => now(),
                    'data_infracao' => $data['data_referencia'],
                    'pontuacao_anterior' => $valorAnterior,
                    'pontuacao_atual' => $valorAtual,
                    'observacao' => $data['observacao'],
                    'nome_tecnico' => $tecnico->nome_colaborador ?? 'Técnico não encontrado',
                    'id_tecnico' => $data['id_tecnico'],

                    'campo' => $campo,
                    'valor_anterior' => $valorAnterior,
                    'valor_movimentado' => $valorMovimentado,
                    'valor_atual' => $valorAtual,
                    'tipo_movimentacao' => $data['tipo_movimentacao'],
                    'id_usuario' => $request->user()->id_user,
                    'data_referencia' => $data['data_referencia'],
                ]);

                return [
                    'erro' => false,
                    'avaliacao' => $avaliacao->fresh(),
                    'historico' => $historico,
                ];
            });

            if ($resultado['erro'] ?? false) {
                return $this->errorResponse($resultado['message'], $resultado['status']);
            }

            return $this->successResponse(
                $resultado,
                'Pontuação ajustada com sucesso.'
            );

        } catch (\Throwable $e) {
            Log::error('Erro ao ajustar pontuação', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao ajustar pontuação.', 500);
        }
    }
}
