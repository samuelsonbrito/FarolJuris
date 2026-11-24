<?php

use App\Enums\RaioX\StatusRaioX;
use App\Models\RaioX\RelatorioRaioX;
use App\Models\User;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Gateways\FakeComunicacaoGateway;
use App\Services\RaioX\GeradorRaioX;
use App\Services\RaioX\ProgressoRaioX;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['queue.default' => 'sync', 'raiox.ia.driver' => 'fake', 'raiox.coleta.pausa_ms' => 0, 'raiox.coleta.itens_por_pagina' => 100]);
    $this->app->instance(ComunicacaoGateway::class, new FakeComunicacaoGateway(CarbonImmutable::parse('2026-11-06')));
    $this->user = User::factory()->create();
});

function relatorioProgresso(User $user, array $dados = []): RelatorioRaioX
{
    return $user->relatoriosRaioX()->create(['oab' => '54321', 'uf' => 'AM', 'inicio' => '2025-11-06', 'fim' => '2026-11-06', ...$dados]);
}

it('grava os tempos e contadores de cada etapa na geração', function () {
    $r = relatorioProgresso($this->user);

    app(GeradorRaioX::class)->iniciar($r);
    $r->refresh();

    expect($r->iniciado_em)->not->toBeNull()
        ->and($r->coleta_concluida_em)->not->toBeNull()
        ->and($r->gerado_em)->not->toBeNull()
        ->and($r->paginas_coletadas)->toBe(1)
        ->and($r->paginas_total)->toBe(1)
        ->and($r->classificar_total)->toBe($r->processos()->where('candidato', true)->count())
        ->and($r->classificados)->toBe($r->classificar_total);
});

it('estima o progresso da coleta sem histórico', function () {
    $this->travelTo(now());
    $r = relatorioProgresso($this->user, [
        'status' => StatusRaioX::Processando, 'iniciado_em' => now()->subSeconds(20),
        'paginas_total' => 18, 'paginas_coletadas' => 6,
    ]);

    $p = app(ProgressoRaioX::class)->de($r);

    // 18 páginas × 3,5 s + (18 × 100 × 0,06 = 108 processos) × 5 s = 603 s; feito: 6 × 3,5 = 21 s.
    expect($p['etapa'])->toBe('coleta')
        ->and($p['percentual'])->toBe(3)
        ->and($p['restanteSegundos'])->toBe(582)
        ->and($p['decorridoSegundos'])->toBe(20);
});

it('estima o progresso da leitura das decisões', function () {
    $r = relatorioProgresso($this->user, [
        'status' => StatusRaioX::Processando, 'iniciado_em' => now()->subMinutes(3), 'coleta_concluida_em' => now()->subMinute(),
        'paginas_total' => 10, 'paginas_coletadas' => 10, 'classificar_total' => 50, 'classificados' => 25,
    ]);

    $p = app(ProgressoRaioX::class)->de($r);

    // Total: 10 × 3,5 + 50 × 5 = 285 s; feito: 35 + 125 = 160 s.
    expect($p['etapa'])->toBe('classificacao')
        ->and($p['percentual'])->toBe(56)
        ->and($p['classificados'])->toBe(25)
        ->and($p['classificarTotal'])->toBe(50)
        ->and($p['restanteSegundos'])->toBe(125);
});

it('usa o ritmo medido nos Raio-X já gerados', function () {
    $base = now()->subDay();
    relatorioProgresso($this->user, [
        'status' => StatusRaioX::Pronto, 'iniciado_em' => $base, 'coleta_concluida_em' => $base->copy()->addSeconds(40),
        'gerado_em' => $base->copy()->addSeconds(40 + 300), 'paginas_coletadas' => 10, 'paginas_total' => 10,
        'classificar_total' => 60, 'publicacoes_total' => 1000,
    ]);

    $ritmo = app(ProgressoRaioX::class)->ritmo();

    expect($ritmo['amostras'])->toBe(1)
        ->and($ritmo['porPagina'])->toEqual(4.0)
        ->and($ritmo['porProcesso'])->toEqual(5.0)
        ->and($ritmo['candidatos'])->toEqual(0.06)
        ->and($ritmo['mediaTotal'])->toBe(340)
        ->and($ritmo['mediaColeta'])->toBe(40)
        ->and($ritmo['mediaClassificacao'])->toBe(300);
});

it('não mostra progresso fora do processamento', function () {
    $r = relatorioProgresso($this->user, ['status' => StatusRaioX::Pronto, 'iniciado_em' => now()]);

    expect(app(ProgressoRaioX::class)->de($r))->toBeNull();
});

it('a tela do usuário e a do admin recebem o progresso', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    relatorioProgresso($this->user, ['status' => StatusRaioX::Processando, 'iniciado_em' => now(), 'paginas_total' => 4, 'paginas_coletadas' => 1]);

    $this->actingAs($this->user)->get(route('raiox.index'))
        ->assertInertia(fn ($page) => $page->where('relatorios.0.progresso.etapa', 'coleta')->where('relatorios.0.progresso.paginas', 1));

    $this->actingAs($admin)->get(route('admin.raiox.index'))
        ->assertInertia(fn ($page) => $page->where('relatorios.0.progresso.paginasTotal', 4)->where('tempos.amostras', 0));
});
