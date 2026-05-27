<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ixc_finalizacao_configs', 'id_checklist_assunto')) {
            Schema::table('ixc_finalizacao_configs', function (Blueprint $table) {
                $table->unsignedBigInteger('id_checklist_assunto')->nullable()->after('id');
            });

            DB::table('ixc_finalizacao_configs')->get()->each(function ($config) {
                $idChecklistAssunto = DB::table('checklist_assuntos')
                    ->where('id_assunto_ixc', $config->id_assunto_ixc)
                    ->value('id');

                if ($idChecklistAssunto) {
                    DB::table('ixc_finalizacao_configs')
                        ->where('id', $config->id)
                        ->update(['id_checklist_assunto' => $idChecklistAssunto]);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ixc_finalizacao_configs', 'id_checklist_assunto')) {
            Schema::table('ixc_finalizacao_configs', function (Blueprint $table) {
                $table->dropColumn('id_checklist_assunto');
            });
        }
    }
};
