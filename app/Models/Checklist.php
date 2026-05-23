<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    protected $table = "checklists";
    protected $primaryKey = 'id_checklist';

    protected $fillable = [
        'nome_checklist',
        'ativo',
    ];

    public $timestamps = true;

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function itens()
    {
        return $this->hasMany(ChecklistItem::class, 'id_checklist', 'id_checklist');
    }

    public function assuntos()
    {
        return $this->hasMany(ChecklistAssunto::class, 'id_checklist', 'id_checklist');
    }
}
