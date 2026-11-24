<?php

use App\Data\Djen\FiltroConsulta;
use App\Data\Djen\ResultadoConsulta;
use App\Enums\RaioX\StatusRaioX;
use App\Jobs\RaioX\ColetarRaioXJob;
use App\Models\RaioX\ProcessoRaioX;
use App\Models\RaioX\PublicacaoRaioX;
use App\Models\RaioX\RelatorioRaioX;
use App\Models\User;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Exceptions\DjenUnavailableException;
use App\Services\Djen\Gateways\FakeComunicacaoGateway;
use App\Services\RaioX\GeradorRaioX;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['queue.default' => 'sync', 'raiox.ia.driver' => 'fake', 'raiox.coleta.pausa_ms' => 0, 'raiox.coleta.itens_por_pagina' => 100]);
    $this->fake = new FakeComunicacaoGateway(CarbonImmutable::parse('2026-11-06'));
    $this->app->instance(ComunicacaoGateway::class, $this->fake);
    $this->user = User::factory()->create();
});

function novoRelatorio(User $user, array $extra = []): RelatorioRaioX
{
    return $user->relatoriosRaioX()->create([
        'oab' => '54321', 'uf' => 'AM', 'inicio' => '2025-11-06', 'fim' => '2026-11-06', ...$extra,
    ]);
}

it('coleta as publicações da OAB, separa os processos e classifica os que têm decisão', function () {
    $r = novoRelatorio($this->user);

    app(GeradorRaioX::class)->iniciar($r);
    $r->refresh();

    $esperadas = $this->fake->buscar(new FiltroConsulta(numeroOab: '54321', ufOab: 'AM', porPagina: 100))->total;

    expect($r->status)->toBeIn([StatusRaioX::Conferencia, StatusRaioX::Pronto])
        ->and($r->publicacoes_total)->toBe($esperadas)
        ->and($r->processos_total)->toBe($r->processos()->count())
        ->and($r->nome_advogado)->not->toBeNull()
        ->and($r->ia_modelo)->toBe('regras')
        ->and($r->gerado_em)->not->toBeNull()
        ->and($r->processos()->where('candidato', true)->whereNull('resultado')->count())->toBe(0)
        ->and($r->processos()->where('candidato', false)->where('resultado', '!=', 'sem_decisao')->count())->toBe(0);

    if ($r->processos()->where('precisa_conferencia', true)->exists()) {
        expect($r->status)->toBe(StatusRaioX::Conferencia);
    }
});

it('coleta em várias tarefas encadeadas quando há muitas páginas', function () {
    config(['raiox.coleta.itens_por_pagina' => 1]);
    $r = novoRelatorio($this->user);

    app(GeradorRaioX::class)->iniciar($r);

    $esperadas = $this->fake->buscar(new FiltroConsulta(numeroOab: '54321', ufOab: 'AM', porPagina: 100))->total;
    expect($r->fresh()->publicacoes_total)->toBe($esperadas)
        ->and($esperadas)->toBeGreaterThan(ColetarRaioXJob::PAGINAS_POR_TAREFA);
});

it('respeita o teto de páginas da coleta', function () {
    config(['raiox.coleta.itens_por_pagina' => 1, 'raiox.coleta.max_paginas' => 3]);
    $r = novoRelatorio($this->user);

    app(GeradorRaioX::class)->iniciar($r);

    expect($r->fresh()->publicacoes_total)->toBe(3);
});

it('marca o relatório como falho quando o DJEN não responde', function () {
    $gateway = Mockery::mock(ComunicacaoGateway::class);
    $gateway->shouldReceive('buscar')->andThrow(new DjenUnavailableException('fora do ar'));
    $this->app->instance(ComunicacaoGateway::class, $gateway);
    $r = novoRelatorio($this->user);

    app(GeradorRaioX::class)->iniciar($r);
    $r->refresh();

    expect($r->status)->toBe(StatusRaioX::Falhou)
        ->and($r->erro)->toContain('DJEN');
});

it('fica pronto direto quando nenhum processo tem decisão', function () {
    $gateway = Mockery::mock(ComunicacaoGateway::class);
    $gateway->shouldReceive('buscar')->andReturn(new ResultadoConsulta([], 0, 1, 100));
    $this->app->instance(ComunicacaoGateway::class, $gateway);
    $r = novoRelatorio($this->user);

    app(GeradorRaioX::class)->iniciar($r);

    expect($r->fresh()->status)->toBe(StatusRaioX::Pronto)
        ->and($r->fresh()->processos_total)->toBe(0);
});

it('gerar de novo apaga a coleta e a classificação anteriores', function () {
    $r = novoRelatorio($this->user);
    app(GeradorRaioX::class)->iniciar($r);
    $antes = $r->processos()->count();

    app(GeradorRaioX::class)->iniciar($r->fresh());

    expect($r->processos()->count())->toBe($antes)
        ->and($r->publicacoes()->count())->toBe($r->fresh()->publicacoes_total);
});

it('apaga tudo do Raio-X junto com a conta (LGPD)', function () {
    $r = novoRelatorio($this->user);
    app(GeradorRaioX::class)->iniciar($r);

    $this->user->delete();

    expect(RelatorioRaioX::count())->toBe(0)
        ->and(ProcessoRaioX::count())->toBe(0)
        ->and(PublicacaoRaioX::count())->toBe(0);
});
