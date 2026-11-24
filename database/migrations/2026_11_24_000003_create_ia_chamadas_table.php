<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uma linha por chamada à IA (tokens e custo). Sem texto nem dado pessoal: só números.
        Schema::create('ia_chamadas', function (Blueprint $table) {
            $table->id();
            // O histórico de custo fica mesmo se o relatório for excluído.
            $table->foreignId('relatorio_id')->nullable()->constrained('raiox_relatorios')->nullOnDelete();
            $table->string('finalidade', 40)->default('raiox_classificacao');
            $table->string('modelo', 60);
            $table->unsignedInteger('tokens_entrada')->default(0);
            $table->unsignedInteger('tokens_cache_leitura')->default(0);
            $table->unsignedInteger('tokens_cache_escrita')->default(0);
            $table->unsignedInteger('tokens_saida')->default(0);
            $table->decimal('custo_usd', 14, 6)->nullable();
            $table->decimal('cotacao_usd_brl', 8, 4);
            $table->decimal('custo_brl', 14, 6)->nullable();
            // ok | recusa | invalido | erro
            $table->string('resultado', 20)->default('ok');
            $table->unsignedInteger('duracao_ms')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_chamadas');
    }
};
