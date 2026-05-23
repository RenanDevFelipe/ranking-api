<?php

namespace App\Models;

use Collator;
use Illuminate\Database\Eloquent\Model;

class AvaliacaoEstoque extends Model
{
    protected $table = 'avaliacao_estoque';
    protected $primaryKey = 'id_avaliacao_estoque';

    public $timestamps = true;

    protected $fillable = [
        'pnt_pedido',
        'pnt_prazo',
        'pnt_etiqueta',
        'pnt_baixa_mat',
        'pnt_troca_equip',
        'pnt_transferencia',
        'data_finalizacao',
        'id_tecnico_estoque',
        'id_setor_avaliacao',
    ];

    public function tecnico()
    {
        return $this->belongsTo(Colaborador::class, 'id_tecnico', 'id_tecnico');
    }
    
    public function setor()
    {
        return $this->belongsTo(Setor::class, 'id_setor', 'id_setor');
    }
}
