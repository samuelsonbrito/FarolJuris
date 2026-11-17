<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\Datajud\AndamentosController;
use App\Http\Controllers\ExportacaoController;
use App\Http\Controllers\Monitoramento\AlertaController;
use App\Http\Controllers\Monitoramento\AlvoController;
use App\Http\Controllers\Monitoramento\MonitoramentoController;
use App\Http\Controllers\Monitoramento\PreferenciasController;
use App\Http\Controllers\ProcessoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/consulta')->name('home');

Route::get('consulta', ConsultaController::class)
    ->middleware('throttle:djen-consulta')
    ->name('consulta.index');

// Página pública do processo (aceita o número com máscara e redireciona para só dígitos).
Route::get('processos/{numero}', ProcessoController::class)
    ->where('numero', '[0-9.\-]{20,25}')
    ->middleware('throttle:djen-consulta')
    ->name('processos.show');

// Andamentos do processo no DataJud (painel da comunicação). Público, como a Consulta.
Route::get('processos/{numero}/andamentos', AndamentosController::class)
    ->where('numero', '[0-9]{20}')
    ->middleware('throttle:datajud')
    ->name('processos.andamentos');

Route::middleware(['auth', 'verified'])->group(function () {
    // O kit chama a página inicial logada de "dashboard"; aqui é o monitoramento.
    Route::redirect('dashboard', '/monitoramento')->name('dashboard');

    Route::prefix('monitoramento')->name('monitoramento.')->group(function () {
        Route::get('/', MonitoramentoController::class)->name('index');

        Route::post('alvos', [AlvoController::class, 'store'])->name('alvos.store');
        Route::patch('alvos/{alvo}', [AlvoController::class, 'update'])->name('alvos.update');
        Route::delete('alvos/{alvo}', [AlvoController::class, 'destroy'])->name('alvos.destroy');
        Route::post('alvos/{alvo}/verificar', [AlvoController::class, 'verificar'])
            ->middleware('throttle:6,1')
            ->name('alvos.verificar');

        Route::patch('alertas/{alerta}', [AlertaController::class, 'update'])->name('alertas.update');
        Route::post('alertas/marcar-lidas', [AlertaController::class, 'marcarTodasLidas'])->name('alertas.marcar-lidas');

        Route::put('notificacoes', [PreferenciasController::class, 'notificacoes'])->name('notificacoes.update');
        Route::delete('dados', [PreferenciasController::class, 'excluirDados'])->name('dados.destroy');
    });

    Route::prefix('exportacoes')->name('exportacoes.')->controller(ExportacaoController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
        Route::get('{exportacao}/download', 'download')->name('download');
        Route::delete('{exportacao}', 'destroy')->name('destroy');
    });
});

// Área administrativa interna: uso, rastreio e saúde da integração com o CNJ.
Route::middleware(['auth', 'verified', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('buscas', Admin\BuscasController::class)->name('buscas');
    Route::get('atividade', Admin\AtividadeController::class)->name('atividade');
    Route::get('usuarios', Admin\UsuariosController::class)->name('usuarios');
    Route::get('cnj', Admin\ChamadasCnjController::class)->name('cnj');
});

require __DIR__.'/settings.php';
