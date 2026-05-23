<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvaliacaoN3Resposta extends Model
{
    protected $table = 'avaliacao_n3_respostas';

    protected $fillable = [
        'id_avaliacao',
        'id_item',
        'resposta',
        'pontuacao',
    ];

    public $timestamps = true;

    protected $casts = [
        'resposta' => 'array',
    ];

    public function avaliacao()
    {
        return $this->belongsTo(AvaliacaoN3::class, 'id_avaliacao', 'id_avaliacao');
    }

    public function item()
    {
        return $this->belongsTo(ChecklistItem::class, 'id_item', 'id_item');
    }
}
