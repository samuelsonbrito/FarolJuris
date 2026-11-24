<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raiox_relatorios', function (Blueprint $table) {
            $table->id();
            // Dono do relatório (quem solicitou ou para quem o admin gerou). LGPD: some com a conta.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('oab', 12);
            $table->string('uf', 2);
            $table->string('nome_advogado')->nullable();
            $table->date('inicio');
            $table->date('fim');
            $table->string('status', 20)->default('solicitado')->index();
            $table->string('etapa')->nullable();
            $table->unsignedInteger('publicacoes_total')->default(0);
            $table->unsignedInteger('processos_total')->default(0);
            $table->decimal('custo_mensal', 12, 2)->nullable();
            $table->decimal('custo_variavel', 12, 2)->nullable();
            $table->decimal('honorario_medio', 12, 2)->nullable();
            $table->string('ia_modelo')->nullable();
            $table->unsignedBigInteger('ia_tokens_entrada')->default(0);
            $table->unsignedBigInteger('ia_tokens_saida')->default(0);
            $table->text('erro')->nullable();
            $table->timestamp('gerado_em')->nullable();
            $table->timestamp('liberado_em')->nullable();
            $table->foreignId('liberado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['oab', 'uf']);
        });

        // Publicações coletadas do DJEN (base da classificação e da conferência). Somem com o relatório.
        Schema::create('raiox_publicacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatorio_id')->constrained('raiox_relatorios')->cascadeOnDelete();
            $table->string('hash');
            $table->string('numero_processo', 20)->index();
            $table->date('data_disponibilizacao');
            $table->string('tribunal', 20)->nullable();
            $table->string('orgao')->nullable();
            $table->string('tipo_comunicacao')->nullable();
            $table->string('tipo_documento')->nullable();
            $table->string('classe')->nullable();
            $table->jsonb('destinatarios')->nullable();
            $table->jsonb('advogados')->nullable();
            $table->text('texto');
            $table->timestamps();

            $table->unique(['relatorio_id', 'hash']);
        });

        Schema::create('raiox_processos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relatorio_id')->constrained('raiox_relatorios')->cascadeOnDelete();
            $table->string('numero_processo', 20);
            $table->string('tribunal', 20)->nullable();
            $table->unsignedInteger('publicacoes')->default(0);
            $table->date('ultima_publicacao')->nullable();
            // Havia publicação com cara de decisão (sentença, acórdão, acordo, extinção)?
            $table->boolean('candidato')->default(false);
            $table->string('resultado', 20)->nullable();
            $table->boolean('extincao_sem_merito')->default(false);
            $table->string('instancia', 20)->nullable();
            $table->string('orgao_julgador')->nullable();
            $table->string('vara_origem')->nullable();
            $table->string('grupo_origem', 30)->nullable();
            $table->string('tema', 40)->nullable();
            $table->string('parte_contraria')->nullable();
            $table->string('polo_cliente', 10)->nullable();
            $table->date('data_decisao')->nullable();
            $table->string('confianca', 10)->nullable();
            $table->text('justificativa')->nullable();
            $table->string('hash_decisao')->nullable();
            $table->text('texto_decisao')->nullable();
            $table->boolean('precisa_conferencia')->default(false)->index();
            $table->timestamp('conferido_em')->nullable();
            $table->foreignId('conferido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['relatorio_id', 'numero_processo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raiox_processos');
        Schema::dropIfExists('raiox_publicacoes');
        Schema::dropIfExists('raiox_relatorios');
    }
};
