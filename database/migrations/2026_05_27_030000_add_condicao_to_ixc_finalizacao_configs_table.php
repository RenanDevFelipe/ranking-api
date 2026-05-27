<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Schema::getIndexes('ixc_finalizacao_configs') as $index) {
            if (
                ($index['unique'] ?? false)
                && in_array($index['columns'] ?? [], [
                    ['id_checklist_assunto'],
                    ['id_assunto_ixc'],
                ], true)
            ) {
                Schema::table('ixc_finalizacao_configs', function (Blueprint $table) use ($index) {
                    $table->dropUnique($index['name']);
                });
            }
        }

        Schema::table('ixc_finalizacao_configs', function (Blueprint $table) {
            $table->unsignedBigInteger('id_item_condicao')->nullable()->after('id_assunto_ixc');
            $table->json('resposta_condicao')->nullable()->after('id_item_condicao');
        });
    }

    public function down(): void
    {
        Schema::table('ixc_finalizacao_configs', function (Blueprint $table) {
            $table->dropColumn(['id_item_condicao', 'resposta_condicao']);
        });
    }
};
