<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvaliacaoSucesso extends Model
{
    protected $table = 'avaliacao_sucesso';
    protected $primaryKey = 'id_avaliacao_sucesso';

    public $timestamps = false;

    protected $fillable = [
        'id_atendimento',
        'id_tecnico',
        'id_setor',
        'ponto_sucesso',
        'data_avaliacao',
    ];

    protected $casts = [
        'ponto_sucesso' => 'float',
        'data_avaliacao' => 'date',
    ];

    public function tecnico()
    {
        return $this->belongsTo(Colaborador::class, 'id_tecnico', 'id_colaborador');
    }

    public function setor()
    {
        return $this->belongsTo(Setor::class, 'id_setor', 'id_setor');
    }
}