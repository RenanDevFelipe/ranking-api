<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistItem extends Model
{
    protected $table = 'checklist_itens';
    protected $primaryKey = 'id_item';

    protected $fillable = [
        'id_checklist',
        'pergunta',
        'tipo_resposta',
        'peso',
        'obrigatorio',
        'ordem',
    ];

    protected $casts = [
        'obrigatorio' => 'boolean',
    ];

    public function checklist()
    {
        return $this->belongsTo(Checklist::class, 'id_checklist', 'id_checklist');
    }
}
