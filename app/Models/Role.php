<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = "roles";
    protected $primaryKey = 'id_role';

    public $timestamps = true;

    protected $fillable = [
        'nome_role',
    ];
}
