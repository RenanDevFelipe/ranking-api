<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\V1\ProducaoOsSyncController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class SyncProducaoOsCommand extends Command
{
    protected $signature = 'producao-os:sync';

    protected $description = 'Sincroniza produção de OS do IXC para o banco local';

    public function handle()
    {
        $dataHoje = now()->format('Y-m-d');

        $request = new Request([
            'data_inicio' => $dataHoje,
            'data_fim' => $dataHoje,
            'rp' => 500,
        ]);

        app(ProducaoOsSyncController::class)->sync($request);

        $this->info("Produção sincronizada do dia {$dataHoje}");
    }
}