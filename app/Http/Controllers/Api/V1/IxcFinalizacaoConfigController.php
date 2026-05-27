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
            IxcFinalizacaoConfig::with(['assunto.checklist', 'itemCondicao'])->orderBy('nome_assunto_ixc')->get(),
            'Configuracoes de finalizacao IXC listadas com sucesso.'
        );
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            $assunto = ChecklistAssunto::findOrFail($data['id_checklist_assunto']);
            $this->validarItemCondicao($assunto, $data);

            $config = IxcFinalizacaoConfig::create([
                ...$data,
                'id_assunto_ixc' => $assunto->id_assunto_ixc,
                'nome_assunto_ixc' => $assunto->nome_assunto_ixc,
                'ativo' => $data['ativo'] ?? true,
                'finalizar_atendimento' => $data['finalizar_atendimento'] ?? 'N',
            ]);

            return $this->successResponse(
                $config->load(['assunto.checklist', 'itemCondicao']),
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
                IxcFinalizacaoConfig::with(['assunto.checklist', 'itemCondicao'])->findOrFail($id),
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

            $idChecklistAssunto = $data['id_checklist_assunto'] ?? $config->id_checklist_assunto;
            if ($idChecklistAssunto) {
                $assunto = ChecklistAssunto::findOrFail($idChecklistAssunto);
                $this->validarItemCondicao($assunto, $data, $config);
                $data['id_assunto_ixc'] = $assunto->id_assunto_ixc;
                $data['nome_assunto_ixc'] = $assunto->nome_assunto_ixc;
            }

            $config->update($data);

            return $this->successResponse(
                $config->fresh()->load(['assunto.checklist', 'itemCondicao']),
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
                $presence,
                'integer',
                'exists:checklist_assuntos,id',
            ],
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
        ChecklistAssunto $assunto,
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

        $pertenceChecklist = ChecklistItem::where('id_item', $idItem)
            ->where('id_checklist', $assunto->id_checklist)
            ->exists();

        if (!$pertenceChecklist) {
            throw ValidationException::withMessages([
                'id_item_condicao' => 'O item de condicao deve pertencer ao checklist do assunto.',
            ]);
        }
    }
}
