<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricoRh extends Model
{
    protected $table = 'historico_rh';
    protected $primaryKey = 'id_historico';

    public $timestamps = true;

    protected $fillable = [
        'nome_avaliador',
        'data_avaliacao',
        'data_infracao',
        'pontuacao_anterior',
        'pontuacao_atual',
        'observacao',
        'nome_tecnico',
        'id_tecnico',
        'campo',
        'valor_anterior',
        'valor_movimentado',
        'valor_atual',
        'tipo_movimentacao',
        'id_usuario',
        'data_referencia'
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
            'id_colaborador',
            'id_tecnico'
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
