<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PontuacaoAssunto extends Model
{
    protected $table = 'pontuacao_assuntos';

    protected $fillable = [
        'id_checklist_assunto',
        'pontos',
        'ativo',
    ];

    protected $casts = [
        'pontos' => 'float',
        'ativo' => 'boolean',
    ];

    public function assunto()
    {
        return $this->belongsTo(ChecklistAssunto::class, 'id_checklist_assunto', 'id');
    }
}
