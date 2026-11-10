<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Filtros novos da Consulta: palavra-chave no teor, meio e nº da comunicação. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_logs', function (Blueprint $table) {
            $table->string('texto', 200)->nullable()->after('tribunal');
            $table->char('meio', 1)->nullable()->after('texto');
            $table->string('numero_comunicacao', 12)->nullable()->after('meio');
        });
    }

    public function down(): void
    {
        Schema::table('search_logs', function (Blueprint $table) {
            $table->dropColumn(['texto', 'meio', 'numero_comunicacao']);
        });
    }
};
