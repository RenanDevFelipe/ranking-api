<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvaliacaoN2 extends Model
{
    protected $table = 'avaliacao_n2';
    protected $primaryKey = 'id_avaliacao_n2';

    public $timestamps = true;

    protected $fillable = [
        'ponto_finalizacao_os',
        'ponto_lavagem_carro',
        'organizacao_material',
        'data_finalizacao',
        'id_tecnico_n2',
        'id_setor',
        'ponto_fardamento',
    ];

    protected $casts = [
        'data_finalizacao' => 'date'
    ];

    public function tecnico()
    {
        return $this->belongsTo(Colaborador::class, 'id_tecnico_n2', 'id_colaborador');
    }

    public function setor()
    {
        return $this->belongsTo(Setor::class, 'id_setor', 'id_setor');
    }
}
