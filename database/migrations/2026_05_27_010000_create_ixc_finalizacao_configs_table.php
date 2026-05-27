<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ixc_finalizacao_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_checklist_assunto');
            $table->unsignedBigInteger('id_assunto_ixc');
            $table->string('nome_assunto_ixc')->nullable();
            $table->boolean('ativo')->default(true);
            $table->char('finalizar_atendimento', 1)->default('N');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ixc_finalizacao_configs');
    }
};
