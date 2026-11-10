<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Filtro por tipo de comunicação na Consulta. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_logs', function (Blueprint $table) {
            $table->string('tipo_comunicacao', 40)->nullable()->after('numero_comunicacao');
        });
    }

    public function down(): void
    {
        Schema::table('search_logs', function (Blueprint $table) {
            $table->dropColumn('tipo_comunicacao');
        });
    }
};
