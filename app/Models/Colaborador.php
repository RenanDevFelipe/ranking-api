<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Colaborador extends Model
{
    protected $table = 'colaborador';
    protected $primaryKey = 'id_colaborador';

    public $timestamps = true;

    protected $fillable = [
        'id_ixc',
        'nome_colaborador',
        'setor_colaborador',
        'url_image',
    ];

    public function setor()
    {
        return $this->belongsTo(Setor::class, 'setor_colaborador', 'id_setor');
    }

}
