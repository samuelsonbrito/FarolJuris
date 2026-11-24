<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raiox_relatorios', function (Blueprint $table) {
            // Progresso e tempos de cada etapa (barra de progresso e médias).
            $table->timestamp('iniciado_em')->nullable()->after('erro');
            $table->timestamp('coleta_concluida_em')->nullable()->after('iniciado_em');
            $table->unsignedInteger('paginas_total')->default(0)->after('coleta_concluida_em');
            $table->unsignedInteger('paginas_coletadas')->default(0)->after('paginas_total');
            $table->unsignedInteger('classificar_total')->default(0)->after('paginas_coletadas');
            $table->unsignedInteger('classificados')->default(0)->after('classificar_total');
        });
    }

    public function down(): void
    {
        Schema::table('raiox_relatorios', function (Blueprint $table) {
            $table->dropColumn(['iniciado_em', 'coleta_concluida_em', 'paginas_total', 'paginas_coletadas', 'classificar_total', 'classificados']);
        });
    }
};
