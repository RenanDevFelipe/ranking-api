<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ixc_finalizacao_config_assuntos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_ixc_finalizacao_config');
            $table->unsignedBigInteger('id_checklist_assunto');
            $table->timestamps();

            $table->unique([
                'id_ixc_finalizacao_config',
                'id_checklist_assunto',
            ], 'ixc_finalizacao_config_assuntos_unique');
        });

        DB::table('ixc_finalizacao_configs')
            ->whereNotNull('id_checklist_assunto')
            ->orderBy('id')
            ->get()
            ->each(function ($config) {
                DB::table('ixc_finalizacao_config_assuntos')->updateOrInsert([
                    'id_ixc_finalizacao_config' => $config->id,
                    'id_checklist_assunto' => $config->id_checklist_assunto,
                ], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('ixc_finalizacao_config_assuntos');
    }
};
