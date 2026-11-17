<?php

use App\Data\Djen\FiltroConsulta;
use App\Enums\TipoAlvo;
use App\Models\RegistroBusca;
use App\Models\User;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Exceptions\DjenUnavailableException;
use App\Services\Djen\Gateways\FakeComunicacaoGateway;
use App\Support\NumeroCnj;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

// Processo do TJAM no gateway de exemplo.
const PROCESSO = '10079190120258040001';

beforeEach(function () {
    $this->fake = new FakeComunicacaoGateway(CarbonImmutable::parse('2026-11-06'));
    $this->app->instance(ComunicacaoGateway::class, $this->fake);
    config(['djen.itens_por_pagina' => 10, 'datajud.ativo' => true]);
    RateLimiter::clear('djen-consulta');
});

it('é pública e mostra as publicações do processo', function () {
    $this->get(route('processos.show', PROCESSO))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Processo/Show')
            ->where('processo.numero', PROCESSO)
            ->where('processo.numeroMascara', NumeroCnj::mascara(PROCESSO))
            ->where('processo.tribunal', 'TJAM')
            ->where('processo.tribunalNome', config('tribunais.TJAM'))
            ->where('processo.andamentos', true)
            ->where('alvo', null)
            ->where('erro', null)
            ->where('resultados.data.0.numeroProcessoDigitos', PROCESSO)
            ->where('resultados.meta.pagina', 1));
});

it('busca só o processo pedido e registra a busca com origem própria', function () {
    $gateway = Mockery::mock(ComunicacaoGateway::class);
    $gateway->shouldReceive('buscar')->once()
        ->withArgs(fn (FiltroConsulta $f) => $f->numeroProcesso === PROCESSO && $f->pagina === 2 && $f->temCriterio())
        ->andReturn($this->fake->buscar(new FiltroConsulta(numeroProcesso: PROCESSO)));
    $this->app->instance(ComunicacaoGateway::class, $gateway);

    $this->get(route('processos.show', ['numero' => PROCESSO, 'pagina' => 2]))->assertOk();

    expect(RegistroBusca::query()->latest('id')->first())
        ->criterio->toBe('processo')
        ->processo->toBe(PROCESSO);
});

it('redireciona o número com máscara para o endereço só com dígitos', function () {
    $this->get('/processos/'.NumeroCnj::mascara(PROCESSO).'?pagina=2')
        ->assertRedirect(route('processos.show', ['numero' => PROCESSO, 'pagina' => 2]))
        ->assertStatus(301);
});

it('responde 404 para número que não tem 20 dígitos', function () {
    $this->get('/processos/1234567-89.2026.8.04.100')->assertNotFound();
    $this->get('/processos/abc')->assertNotFound();
});

it('não mostra andamentos para tribunal sem DataJud', function () {
    // J = 8, TR = 26: TJSP.
    $numero = '00000011220268260000';

    $this->get(route('processos.show', $numero))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('processo.tribunal', 'TJSP')
            ->where('processo.andamentos', false));
});

it('mostra a falha do CNJ sem quebrar a página', function () {
    $gateway = Mockery::mock(ComunicacaoGateway::class);
    $gateway->shouldReceive('buscar')->andThrow(new DjenUnavailableException('fora do ar'));
    $this->app->instance(ComunicacaoGateway::class, $gateway);

    $this->get(route('processos.show', PROCESSO))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resultados', null)
            ->where('erro.tipo', 'indisponivel'));
});

it('indica quando o usuário já monitora o processo', function () {
    $user = User::factory()->create();
    $alvo = $user->monitoringTargets()->create(['tipo' => TipoAlvo::Processo, 'valor' => PROCESSO, 'ativo' => false]);

    $this->actingAs($user)
        ->get(route('processos.show', PROCESSO))
        ->assertInertia(fn (Assert $page) => $page->where('alvo', ['id' => $alvo->id, 'ativo' => false]));

    // O alvo de outra pessoa não conta.
    $this->actingAs(User::factory()->create())
        ->get(route('processos.show', PROCESSO))
        ->assertInertia(fn (Assert $page) => $page->where('alvo', null));
});
