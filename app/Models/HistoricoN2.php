<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricoN2 extends Model
{
    protected $table = 'historico_n2';
    protected $primaryKey = 'id_historico';

    public $timestamps = true;

    protected $fillable = [
        'id_tecnico',
        'campo',

        'valor_anterior',
        'valor_movimentado',
        'valor_atual',

        'tipo_movimentacao',

        'observacao',

        'id_usuario',

        'data_referencia',
        'nome_avaliador',
        'data_avaliacao',
        'data_infracao',
        'pontuacao_anterior',
        'pontuacao_atual',
        'nome_tecnico',
    ];

    protected $casts = [
        'valor_anterior' => 'float',
        'valor_movimentado' => 'float',
        'valor_atual' => 'float',

        'data_referencia' => 'date',
    ];

    public function tecnico()
    {
        return $this->belongsTo(
            Colaborador::class,
            'id_tecnico',
            'id_ixc'
        );
    }

    public function usuario()
    {
        return $this->belongsTo(
            User::class,
            'id_usuario',
            'id_user'
        );
    }
}
