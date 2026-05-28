<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IxcFinalizacaoConfig extends Model
{
    protected $table = 'ixc_finalizacao_configs';

    protected $fillable = [
        'id_checklist_assunto',
        'id_assunto_ixc',
        'id_item_condicao',
        'resposta_condicao',
        'nome_assunto_ixc',
        'ativo',
        'finalizar_atendimento',
        'origem_mensagem',
        'ordem_execucao',
        'payload',
    ];

    protected $appends = [
        'id_checklist_assuntos',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'resposta_condicao' => 'array',
        'ordem_execucao' => 'integer',
        'payload' => 'array',
    ];

    public function payloadFechamento(): array
    {
        return array_replace(
            $this->payload ?? [],
            ['finaliza_processo_aux' => $this->finalizar_atendimento]
        );
    }

    public function assunto()
    {
        return $this->belongsTo(ChecklistAssunto::class, 'id_checklist_assunto', 'id');
    }

    public function assuntos()
    {
        return $this->belongsToMany(
            ChecklistAssunto::class,
            'ixc_finalizacao_config_assuntos',
            'id_ixc_finalizacao_config',
            'id_checklist_assunto'
        )->withTimestamps();
    }

    public function itemCondicao()
    {
        return $this->belongsTo(ChecklistItem::class, 'id_item_condicao', 'id_item');
    }

    public function getIdChecklistAssuntosAttribute(): array
    {
        if (!$this->relationLoaded('assuntos')) {
            return [];
        }

        return $this->assuntos->pluck('id')->values()->all();
    }
}
