<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IxcConfig extends Model
{
    protected $table = 'ixc_configs';

    protected $fillable = [
        'nome',
        'base_url',
        'token',
        'ativo',
    ];

    protected $hidden = [
        'token'
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'token' => 'encrypted'
    ];
}
