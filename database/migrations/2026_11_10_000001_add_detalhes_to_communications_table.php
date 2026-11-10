<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Campos exibidos no painel lateral da comunicação (todos opcionais). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->unsignedBigInteger('cnj_id')->nullable()->after('hash');
            $table->string('numero_comunicacao', 30)->nullable()->after('cnj_id');
            $table->char('meio', 1)->nullable()->after('numero_comunicacao');
            $table->string('meio_completo', 100)->nullable()->after('meio');
            $table->string('classe')->nullable()->after('tipo_documento');
            $table->string('codigo_classe', 20)->nullable()->after('classe');
        });
    }

    public function down(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->dropColumn(['cnj_id', 'numero_comunicacao', 'meio', 'meio_completo', 'classe', 'codigo_classe']);
        });
    }
};
