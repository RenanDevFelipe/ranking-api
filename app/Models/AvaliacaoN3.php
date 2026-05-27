<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvaliacaoN3 extends Model
{
    protected $table = 'avaliacao_n3';
    protected $primaryKey = 'id_avaliacao';

    public $timestamps = false;

    protected $fillable = [
        'id_os',
        'id_assunto_ixc',
        'id_checklist',
        'desc_os',
        'pontuacao_os',
        'nota_os',
        'data_finalizacao_os',
        'data_finalizacao',
        'id_tecnico',
        'id_setor',
        'avaliador',
        'check_list',
        'mensagens_finalizacao',
    ];

    protected $casts = [
        'check_list' => 'array',
        'mensagens_finalizacao' => 'array',
        'data_finalizacao_os' => 'datetime',
        'data_finalizacao' => 'date',
    ];

    public function tecnico()
    {
        return $this->belongsTo(Colaborador::class, 'id_tecnico', 'id_colaborador');
    }

    public function setor()
    {
        return $this->belongsTo(Setor::class, 'id_setor', 'id_setor');
    }

    public function usuarioAvaliador()
    {
        return $this->belongsTo(User::class, 'avaliador', 'id_user');
    }

    public function checklist()
    {
        return $this->belongsTo(Checklist::class, 'id_checklist', 'id_checklist');
    }

    public function respostas()
    {
        return $this->hasMany(AvaliacaoN3Resposta::class, 'id_avaliacao', 'id_avaliacao');
    }
}
