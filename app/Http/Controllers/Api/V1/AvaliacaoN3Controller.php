<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AvaliacaoN3;
use App\Models\AvaliacaoN3Resposta;
use App\Models\ChecklistItem;
use App\Models\IxcConfig;
use App\Services\IxcFinalizacaoAutomaticaService;
use App\Services\IxcService;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AvaliacaoN3Controller extends Controller
{

    use ApiResponseTrait;

    public function __construct(
        protected IxcService $ixcService,
        protected IxcFinalizacaoAutomaticaService $finalizacaoAutomaticaService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = AvaliacaoN3::with([
                'tecnico',
                'setor',
                'usuarioAvaliador',
                'checklist',
                'respostas.item',
            ]);

            if ($request->filled('id_tecnico')) {
                $query->where('id_tecnico', $request->id_tecnico);
            }

            if ($request->filled('id_setor')) {
                $query->where('id_setor', $request->id_setor);
            }

            if ($request->filled('id_os')) {
                $query->where('id_os', $request->id_os);
            }

            if ($request->filled('id_assunto_ixc')) {
                $query->where('id_assunto_ixc', $request->id_assunto_ixc);
            }

            if ($request->filled('data_inicio') && $request->filled('data_fim')) {
                $query->whereBetween('data_finalizacao', [
                    $request->data_inicio,
                    $request->data_fim
                ]);
            }

            $avaliacoes = $query
                ->orderByDesc('data_finalizacao')
                ->paginate($request->get('per_page', 20));

            return $this->successResponse(
                $avaliacoes,
                'Avaliações N3 listadas com sucesso.'
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao listar avaliações N3', [
                'params' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erro interno ao listar avaliações N3.',
                500
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
                'id_os' => ['required', 'integer', 'unique:avaliacao_n3,id_os'],
                'id_assunto_ixc' => ['required', 'integer'],
                'id_checklist' => ['required', 'integer', 'exists:checklists,id_checklist'],

                'desc_os' => ['required', 'string'],
                'data_finalizacao_os' => ['required', 'date'],
                'data_finalizacao' => ['required', 'date'],

                'id_tecnico' => ['required', 'integer'],
                'id_setor' => ['required', 'integer', 'exists:setor,id_setor'],

                'respostas' => ['required', 'array', 'min:1'],
                'respostas.*.id_item' => [
                    'required',
                    'integer',
                    'exists:checklist_itens,id_item'
                ],
                'respostas.*.resposta' => ['required'],
                'respostas.*.pontuacao' => ['required', 'numeric', 'min:0'],
                'mensagens_finalizacao' => ['sometimes', 'array'],
                'mensagens_finalizacao.*.id_checklist_assunto' => [
                    'required_with:mensagens_finalizacao',
                    'integer',
                    'exists:checklist_assuntos,id',
                ],
                'mensagens_finalizacao.*.mensagem' => [
                    'required_with:mensagens_finalizacao',
                    'string',
                    'max:10000',
                ],
            ]);

            $avaliacao = DB::transaction(function () use ($data, $request) {

                $pontuacaoTotal = collect($data['respostas'])->sum('pontuacao');

                $pesoTotal = ChecklistItem::where('id_checklist', $data['id_checklist'])
                    ->sum('peso');

                $nota = $pesoTotal > 0
                    ? round(($pontuacaoTotal / $pesoTotal) * 10, 2)
                    : 0;

                $avaliacao = AvaliacaoN3::create([
                    'id_os' => $data['id_os'],
                    'id_assunto_ixc' => $data['id_assunto_ixc'],
                    'id_checklist' => $data['id_checklist'],

                    'desc_os' => $data['desc_os'],
                    'pontuacao_os' => $pontuacaoTotal,
                    'nota_os' => $nota,

                    'data_finalizacao_os' => $data['data_finalizacao_os'],
                    'data_finalizacao' => $data['data_finalizacao'],

                    'id_tecnico' => $data['id_tecnico'],
                    'id_setor' => $data['id_setor'],
                    'avaliador' => $request->user()->id_user,

                    'check_list' => $data['respostas'],
                    'mensagens_finalizacao' => $data['mensagens_finalizacao'] ?? [],
                ]);

                foreach ($data['respostas'] as $resposta) {
                    AvaliacaoN3Resposta::create([
                        'id_avaliacao' => $avaliacao->id_avaliacao,
                        'id_item' => $resposta['id_item'],
                        'resposta' => $resposta['resposta'],
                        'pontuacao' => $resposta['pontuacao'],
                    ]);
                }

                return $avaliacao;
            });

            $finalizacoesAutomaticas = $this->processarFinalizacoesAutomaticas(
                $data['id_os'],
                $data['id_checklist'],
                $data['id_assunto_ixc'],
                $request->user()->id_ixc_user,
                $data['respostas'],
                $data['mensagens_finalizacao'] ?? []
            );
            $avaliacao->setAttribute('finalizacoes_automaticas', $finalizacoesAutomaticas);

            return $this->successResponse(
                $avaliacao->load([
                    'tecnico',
                    'setor',
                    'usuarioAvaliador',
                    'checklist',
                    'respostas.item'
                ]),
                'Avaliação N3 registrada com sucesso.',
                201
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao registrar avaliação N3', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erro interno ao registrar avaliação N3.',
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $avaliacao = AvaliacaoN3::with([
                'tecnico',
                'setor',
                'usuarioAvaliador',
                'checklist',
                'respostas.item',
            ])->findOrFail($id);

            return $this->successResponse(
                $avaliacao,
                'Avaliação N3 encontrada com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Avaliação N3 não encontrada.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao buscar avaliação N3', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erro interno ao buscar avaliação N3.',
                500
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $avaliacao = AvaliacaoN3::findOrFail($id);

            $data = $request->validate([
                'id_assunto_ixc' => ['sometimes', 'integer'],
                'id_checklist' => ['sometimes', 'integer', 'exists:checklists,id_checklist'],

                'desc_os' => ['sometimes', 'string'],
                'data_finalizacao_os' => ['sometimes', 'date'],
                'data_finalizacao' => ['sometimes', 'date'],

                'id_tecnico' => ['sometimes', 'integer'],
                'id_setor' => ['sometimes', 'integer', 'exists:setor,id_setor'],

                'respostas' => ['sometimes', 'array', 'min:1'],
                'respostas.*.id_item' => ['required_with:respostas', 'integer', 'exists:checklist_itens,id_item'],
                'respostas.*.resposta' => ['required_with:respostas'],
                'respostas.*.pontuacao' => ['required_with:respostas', 'numeric', 'min:0'],
                'mensagens_finalizacao' => ['sometimes', 'array'],
                'mensagens_finalizacao.*.id_checklist_assunto' => [
                    'required_with:mensagens_finalizacao',
                    'integer',
                    'exists:checklist_assuntos,id',
                ],
                'mensagens_finalizacao.*.mensagem' => [
                    'required_with:mensagens_finalizacao',
                    'string',
                    'max:10000',
                ],
            ]);

            $avaliacao = DB::transaction(function () use ($avaliacao, $data) {
                $dadosAvaliacao = collect($data)->except('respostas')->toArray();

                if (isset($data['respostas'])) {
                    $idChecklist = $data['id_checklist'] ?? $avaliacao->id_checklist;

                    $pontuacaoTotal = collect($data['respostas'])->sum('pontuacao');

                    $pesoTotal = ChecklistItem::where('id_checklist', $idChecklist)
                        ->sum('peso');

                    $nota = $pesoTotal > 0
                        ? round(($pontuacaoTotal / $pesoTotal) * 10, 2)
                        : 0;

                    $dadosAvaliacao['pontuacao_os'] = $pontuacaoTotal;
                    $dadosAvaliacao['nota_os'] = $nota;
                    $dadosAvaliacao['check_list'] = $data['respostas'];

                    $avaliacao->respostas()->delete();

                    foreach ($data['respostas'] as $resposta) {
                        $avaliacao->respostas()->create([
                            'id_item' => $resposta['id_item'],
                            'resposta' => $resposta['resposta'],
                            'pontuacao' => $resposta['pontuacao'],
                        ]);
                    }
                }

                $avaliacao->update($dadosAvaliacao);

                return $avaliacao;
            });

            return $this->successResponse(
                $avaliacao->load([
                    'tecnico',
                    'setor',
                    'usuarioAvaliador',
                    'checklist',
                    'respostas.item'
                ]),
                'Avaliação N3 atualizada com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                'Avaliação N3 não encontrada.',
                404
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar avaliação N3', [
                'id' => $id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erro interno ao atualizar avaliação N3.',
                500
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $avaliacao = AvaliacaoN3::with('usuarioAvaliador')->findOrFail($id);
            $avaliacao->delete();

            return $this->successResponse(null, 'Avaliação N3 removida com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Avaliação N3 não encontrada.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao remover avaliação N3', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao remover avaliação N3.', 500);
        }
    }

    public function verificarOsAvaliada(string $idOs)
    {
        try {

            $avaliacao = AvaliacaoN3::where('id_os', $idOs)
                ->with([
                    'tecnico',
                    'usuarioAvaliador',
                    'checklist'
                ])
                ->first();
            
            if (!$avaliacao) {
                return $this->successResponse([
                    'avaliada' => false,
                ], 'O.S ainda não foi avaliada');
            }

            return $this->successResponse([
                'avaliada' => true,
                'avaliacao' => $avaliacao,
            ], 'O.S já foi avaliada.');

        } catch (\Throwable $e)
        {
            Log::error('Erro ao verificar O.S avaliada.', [
                'id' => $idOs,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro ao verificar O.S avaliada.', 500);
        }
    }

    public function reprocessarFinalizacoesAutomaticas(string $id)
    {
        try {
            $avaliacao = AvaliacaoN3::findOrFail($id);

            return $this->successResponse(
                $this->processarFinalizacoesAutomaticas(
                    $avaliacao->id_os,
                    $avaliacao->id_checklist,
                    $avaliacao->id_assunto_ixc,
                    $avaliacao->usuarioAvaliador?->id_ixc_user,
                    $avaliacao->check_list ?? [],
                    $avaliacao->mensagens_finalizacao ?? []
                ),
                'Finalizacoes automaticas da avaliacao processadas com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Avaliacao N3 nao encontrada.', 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao reprocessar finalizacoes automaticas da avaliacao N3', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro ao reprocessar finalizacoes automaticas.', 500);
        }
    }

    private function processarFinalizacoesAutomaticas(
        int $idOs,
        int $idChecklist,
        int $idAssuntoIxc,
        int|string|null $idColaboradorIxc = null,
        array $respostas = [],
        array $mensagensFinalizacao = []
    ): array
    {
        try {
            $config = IxcConfig::where('ativo', true)->first();

            if (!$config) {
                return [
                    'fechamentos' => [],
                    'ignorado' => true,
                    'motivo' => 'Nenhuma configuracao IXC ativa encontrada.',
                ];
            }

            $resultadoOrdem = $this->ixcService->buscarOrdemServicoPorId($config, $idOs);

            if (!$resultadoOrdem['success']) {
                return [
                    'fechamentos' => [],
                    'ignorado' => true,
                    'motivo' => 'Nao foi possivel consultar a ordem no IXC.',
                ];
            }

            $ordem = $this->ixcService->extrairRegistrosResposta($resultadoOrdem['data'])[0] ?? null;

            if (!$ordem) {
                return [
                    'fechamentos' => [],
                    'ignorado' => true,
                    'motivo' => 'Ordem de servico nao encontrada no IXC.',
                ];
            }

            return $this->finalizacaoAutomaticaService->processarOrdemAvaliada(
                $config,
                $ordem,
                $idChecklist,
                $idAssuntoIxc,
                $idColaboradorIxc,
                $respostas,
                $mensagensFinalizacao
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao processar finalizacoes automaticas apos avaliacao N3', [
                'id_os' => $idOs,
                'error' => $e->getMessage(),
            ]);

            return [
                'fechamentos' => [],
                'ignorado' => true,
                'motivo' => 'Erro ao processar finalizacoes automaticas no IXC.',
            ];
        }
    }
}
