<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use  Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'id_user';

    protected $fillable = [
        'nome_user',
        'id_ixc_user',
        'email_user',
        'senha_user',
        'role',
        'setor_user',
    ];

    protected $hidden = [
        'senha_user',
    ];

    public function getAuthPassword()
    {
        return $this->senha_user;
    }

    protected function senhaUser(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => 
                !empty($value)
                    ? Hash::make($value)
                    : null
        );
    }
}
