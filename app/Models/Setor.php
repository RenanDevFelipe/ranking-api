<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setor extends Model
{
    protected $table = "setor";
    protected $primaryKey = 'id_setor';

    public $timestamps = true;

    protected $fillable = [
        'nome_setor',
    ];
}
