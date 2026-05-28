<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistAssunto extends Model
{
    protected $table = 'checklist_assuntos';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_checklist',
        'id_assunto_ixc',
        'nome_assunto_ixc',
    ];

    public $timestamps = true;

    public function checklist()
    {
        return $this->belongsTo(Checklist::class, 'id_checklist', 'id_checklist');
    }

    public function pontuacao()
    {
        return $this->hasOne(PontuacaoAssunto::class, 'id_checklist_assunto', 'id');
    }

    public function finalizacaoIxc()
    {
        return $this->belongsToMany(
            IxcFinalizacaoConfig::class,
            'ixc_finalizacao_config_assuntos',
            'id_checklist_assunto',
            'id_ixc_finalizacao_config'
        )->withTimestamps();
    }
}
