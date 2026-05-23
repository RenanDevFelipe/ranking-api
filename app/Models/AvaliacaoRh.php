<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvaliacaoRh extends Model
{
    protected $table = 'avaliacao_rh';
    protected $primaryKey = 'id_avaliacao_rh';

    protected $fillable = [
        'pnt_ponto',
        'pnt_atestado',
        'pnt_falta',
        'id_tecnico',
        'id_setor',
        'data_avaliacao',
        'nome_avaliador',
    ];

    protected $casts = [
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
