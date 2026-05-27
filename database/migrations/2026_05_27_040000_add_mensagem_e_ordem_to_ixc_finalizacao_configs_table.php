<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ixc_finalizacao_configs', function (Blueprint $table) {
            $table->string('origem_mensagem')->default('payload')->after('resposta_condicao');
            $table->unsignedInteger('ordem_execucao')->default(1)->after('origem_mensagem');
        });

        Schema::table('avaliacao_n3', function (Blueprint $table) {
            $table->json('mensagens_finalizacao')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ixc_finalizacao_configs', function (Blueprint $table) {
            $table->dropColumn(['origem_mensagem', 'ordem_execucao']);
        });

        Schema::table('avaliacao_n3', function (Blueprint $table) {
            $table->dropColumn('mensagens_finalizacao');
        });
    }
};
