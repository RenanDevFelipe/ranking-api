<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProducaoOs extends Model
{
    protected $table = 'producao_os';

    protected $fillable = [
        'id_os',
        'id_colaborador',
        'id_ixc',
        'id_assunto_ixc',
        'nome_assunto_ixc',
        'pontos',
        'data_finalizacao',
        'data_finalizacao_os',
        'raw',
    ];

    protected $casts = [
        'pontos' => 'float',
        'data_finalizacao' => 'date',
        'data_finalizacao_os' => 'datetime',
        'raw' => 'array',
    ];

    public function colaborador()
    {
        return $this->belongsTo(Colaborador::class, 'id_colaborador', 'id_colaborador');
    }
}