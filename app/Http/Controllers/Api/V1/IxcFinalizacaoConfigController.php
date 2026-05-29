<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChecklistAssunto;
use App\Models\ChecklistItem;
use App\Models\IxcFinalizacaoConfig;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IxcFinalizacaoConfigController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->successResponse(
            IxcFinalizacaoConfig::with(['assuntos.checklist', 'itemCondicao'])->orderBy('id')->get(),
            'Configuracoes de finalizacao IXC listadas com sucesso.'
        );
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            $assuntos = $this->buscarAssuntosVinculados($data);
            $this->validarItemCondicao($assuntos, $data);

            $config = IxcFinalizacaoConfig::create([
                ...collect($data)->except(['id_checklist_assunto', 'id_checklist_assuntos', 'assuntos'])->toArray(),
                'id_checklist_assunto' => $assuntos->first()->id,
                'id_assunto_ixc' => $this->primeiroAssuntoIxcInformado($data, $assuntos->first()->id_assunto_ixc),
                'nome_assunto_ixc' => $this->primeiroNomeAssuntoInformado($data, $assuntos->first()->nome_assunto_ixc),
                'ativo' => $data['ativo'] ?? true,
                'finalizar_atendimento' => $data['finalizar_atendimento'] ?? 'N',
            ]);
            $config->assuntos()->sync($assuntos->pluck('id')->all());

            return $this->successResponse(
                $config->load(['assuntos.checklist', 'itemCondicao']),
                'Configuracao de finalizacao IXC criada com sucesso.',
                201
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Erro ao criar configuracao de finalizacao IXC', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao criar configuracao de finalizacao IXC.', 500);
        }
    }

    public function show(string $id)
    {
        try {
            return $this->successResponse(
                IxcFinalizacaoConfig::with(['assuntos.checklist', 'itemCondicao'])->findOrFail($id),
                'Configuracao de finalizacao IXC encontrada com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Configuracao de finalizacao IXC nao encontrada.', 404);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $config = IxcFinalizacaoConfig::findOrFail($id);
            $data = $request->validate($this->rules($config->id, false));

            $assuntos = null;
            if (
                array_key_exists('id_checklist_assuntos', $data)
                || array_key_exists('id_checklist_assunto', $data)
                || array_key_exists('assuntos', $data)
            ) {
                $assuntos = $this->buscarAssuntosVinculados($data);
                $this->validarItemCondicao($assuntos, $data, $config);
                $data['id_checklist_assunto'] = $assuntos->first()->id;
                $data['id_assunto_ixc'] = $this->primeiroAssuntoIxcInformado($data, $assuntos->first()->id_assunto_ixc);
                $data['nome_assunto_ixc'] = $this->primeiroNomeAssuntoInformado($data, $assuntos->first()->nome_assunto_ixc);
            } else {
                $this->validarItemCondicao($config->assuntos()->get(), $data, $config);
            }

            $config->update(collect($data)->except(['id_checklist_assuntos', 'assuntos'])->toArray());

            if ($assuntos) {
                $config->assuntos()->sync($assuntos->pluck('id')->all());
            }

            return $this->successResponse(
                $config->fresh()->load(['assuntos.checklist', 'itemCondicao']),
                'Configuracao de finalizacao IXC atualizada com sucesso.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Configuracao de finalizacao IXC nao encontrada.', 404);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar configuracao de finalizacao IXC', [
                'id' => $id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Erro interno ao atualizar configuracao de finalizacao IXC.', 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            IxcFinalizacaoConfig::findOrFail($id)->delete();

            return $this->successResponse(null, 'Configuracao de finalizacao IXC removida com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Configuracao de finalizacao IXC nao encontrada.', 404);
        }
    }

    private function rules(?int $ignoreId = null, bool $required = true): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'id_checklist_assunto' => [
                'sometimes',
                'integer',
                'exists:checklist_assuntos,id',
            ],
            'id_checklist_assuntos' => ['sometimes', 'array', 'min:1'],
            'id_checklist_assuntos.*' => ['integer', 'exists:checklist_assuntos,id'],
            'assuntos' => ['sometimes', 'array', 'min:1'],
            'assuntos.*.id_checklist_assunto' => ['sometimes', 'integer', 'exists:checklist_assuntos,id'],
            'assuntos.*.id_checklist' => ['sometimes', 'integer', 'exists:checklists,id_checklist'],
            'assuntos.*.id_assunto_ixc' => ['required_with:assuntos', 'integer'],
            'assuntos.*.nome_assunto_ixc' => ['required_with:assuntos', 'string', 'max:255'],
            'id_item_condicao' => ['sometimes', 'nullable', 'integer', 'exists:checklist_itens,id_item'],
            'resposta_condicao' => ['sometimes', 'nullable'],
            'ativo' => ['sometimes', 'boolean'],
            'finalizar_atendimento' => ['sometimes', Rule::in(['S', 'N'])],
            'origem_mensagem' => ['sometimes', Rule::in(['payload', 'checklist', 'usuario'])],
            'ordem_execucao' => ['sometimes', 'integer', 'min:1'],
            'payload' => ['sometimes', 'array'],
        ];
    }

    private function validarItemCondicao(
        \Illuminate\Support\Collection $assuntos,
        array $data,
        ?IxcFinalizacaoConfig $config = null
    ): void {
        $idItem = array_key_exists('id_item_condicao', $data)
            ? $data['id_item_condicao']
            : $config?->id_item_condicao;

        if (!$idItem) {
            return;
        }

        if (
            !array_key_exists('resposta_condicao', $data)
            && (!$config || $config->resposta_condicao === null)
        ) {
            throw ValidationException::withMessages([
                'resposta_condicao' => 'Informe resposta_condicao para a regra condicional.',
            ]);
        }

        $checklists = $assuntos->pluck('id_checklist')->unique()->values();

        $pertenceChecklist = ChecklistItem::where('id_item', $idItem)
            ->whereIn('id_checklist', $checklists)
            ->exists();

        if (!$pertenceChecklist) {
            throw ValidationException::withMessages([
                'id_item_condicao' => 'O item de condicao deve pertencer ao checklist do assunto.',
            ]);
        }
    }

    private function buscarAssuntosVinculados(array $data): \Illuminate\Support\Collection
    {
        if (!empty($data['assuntos'])) {
            return collect($data['assuntos'])->map(function (array $assunto) {
                if (!empty($assunto['id_checklist_assunto'])) {
                    return ChecklistAssunto::findOrFail($assunto['id_checklist_assunto']);
                }

                if (empty($assunto['id_checklist'])) {
                    throw ValidationException::withMessages([
                        'assuntos' => 'Informe id_checklist ou id_checklist_assunto para cada assunto.',
                    ]);
                }

                return ChecklistAssunto::updateOrCreate(
                    [
                        'id_checklist' => $assunto['id_checklist'],
                        'id_assunto_ixc' => $assunto['id_assunto_ixc'],
                    ],
                    [
                        'nome_assunto_ixc' => $assunto['nome_assunto_ixc'],
                    ]
                );
            });
        }

        $ids = $data['id_checklist_assuntos'] ?? [$data['id_checklist_assunto'] ?? null];
        $ids = collect($ids)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'id_checklist_assuntos' => 'Informe ao menos um assunto para vincular a configuracao.',
            ]);
        }

        return ChecklistAssunto::whereIn('id', $ids)->get();
    }

    private function primeiroAssuntoIxcInformado(array $data, int|string|null $fallback = null): int|string|null
    {
        return $data['assuntos'][0]['id_assunto_ixc'] ?? $data['id_assunto_ixc'] ?? $fallback;
    }

    private function primeiroNomeAssuntoInformado(array $data, ?string $fallback = null): ?string
    {
        return $data['assuntos'][0]['nome_assunto_ixc'] ?? $data['nome_assunto_ixc'] ?? $fallback;
    }
}
