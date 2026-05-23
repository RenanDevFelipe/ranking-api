<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RankingConfiguracao extends Model
{
    protected $table = 'ranking_configuracoes';

    protected $fillable = [
        'meta_pontos_os_diaria',
        'meta_media_avaliacoes',
        'dias_minimos_meta_mensal',
        'meses_minimos_meta_anual',
        'ativo',
    ];

    protected $casts = [
        'meta_pontos_os_diaria' => 'float',
        'meta_media_avaliacoes' => 'float',
        'dias_minimos_meta_mensal' => 'integer',
        'meses_minimos_meta_anual' => 'integer',
        'ativo' => 'boolean',
    ];
}