<?php

use App\Data\Djen\FiltroConsulta;
use App\Data\Djen\ResultadoConsulta;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Exceptions\DjenBlockedException;
use App\Services\Djen\Exceptions\DjenRateLimitedException;
use App\Services\Djen\Exceptions\DjenUnavailableException;
use App\Services\Djen\Gateways\DjenApiGateway;
use App\Services\Djen\Gateways\FakeComunicacaoGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->fake = new FakeComunicacaoGateway(CarbonImmutable::parse('2026-11-06'));
    $this->app->instance(ComunicacaoGateway::class, $this->fake);
    config(['djen.itens_por_pagina' => 10]);
    RateLimiter::clear('djen-consulta');
});

it('é pública e mostra o estado inicial sem chamar o gateway', function () {
    $gateway = Mockery::mock(ComunicacaoGateway::class);
    $gateway->shouldReceive('tribunais')->andReturn($this->fake->tribunais());
    $gateway->shouldNotReceive('buscar');
    $this->app->instance(ComunicacaoGateway::class, $gateway);

    $this->get(route('consulta.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Consulta/Index')
            ->where('resultados', null)
            ->where('erro', null)
            ->has('tribunais', 5)
            ->where('tribunais.0', ['sigla' => 'TJAM', 'nome' => 'Tribunal de Justiça do Amazonas'])
            ->has('ufs', 27)
            ->where('filtros.oab', null));
});

it('busca por OAB e UF e devolve cards serializados pelo Resource', function () {
    $this->get(route('consulta.index', ['oab' => '12.345', 'uf' => 'am']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Consulta/Index')
            ->where('filtros.oab', '12345')
            ->where('filtros.uf', 'AM')
            ->where('resultados.meta.pagina', 1)
            ->where('resultados.meta.porPagina', 10)
            ->where('resultados.meta.ultimaPagina', fn (int $u) => $u >= 2)
            ->has('resultados.data', 10, fn (Assert $c) => $c
                ->whereType('hash', 'string')
                ->whereType('id', 'integer')
                ->whereType('numeroComunicacao', 'string')
                ->where('meio', fn (string $m) => in_array($m, ['D', 'E'], true))
                ->whereType('meioCompleto', 'string')
                ->whereType('classe', 'string')
                ->whereType('codigoClasse', 'string')
                ->where('numeroProcessoDigitos', fn (string $d) => (bool) preg_match('/^\d{20}$/', $d))
                ->whereType('tribunal', 'string')
                ->whereType('orgaoJulgador', 'string|null')
                ->whereType('tipoComunicacao', 'string|null')
                ->whereType('tipoDocumento', 'string|null')
                ->where('dataDisponibilizacao', fn (string $d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))
                ->where('numeroProcesso', fn (string $n) => (bool) preg_match('/^\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}$/', $n))
                ->has('destinatarios.0', fn (Assert $d) => $d->whereType('nome', 'string')->where('polo', 'A'))
                ->has('advogados.0', fn (Assert $a) => $a->has('nome')->has('numeroOab')->has('ufOab'))
                ->whereType('trecho', 'string')
                ->where('textoHtml', fn (string $h) => ! str_contains($h, '<script'))
                ->whereType('linkDocumento', 'string|null')));
});

it('repassa os filtros normalizados ao gateway', function () {
    $gateway = Mockery::mock(ComunicacaoGateway::class);
    $gateway->shouldReceive('tribunais')->andReturn([]);
    $gateway->shouldReceive('buscar')->once()->withArgs(fn (FiltroConsulta $f) => $f->numeroProcesso === '00012345620248040001'
        && $f->nomeParte === 'Joana'
        && $f->tribunal === 'TJAM'
        && $f->dataInicio?->toDateString() === '2026-11-01'
        && $f->dataFim?->toDateString() === '2026-11-06'
        && $f->pagina === 3
        && $f->porPagina === 10)
        ->andReturn(new ResultadoConsulta([], 0, 3, 10));
    $this->app->instance(ComunicacaoGateway::class, $gateway);

    $this->get('/consulta?'.http_build_query([
        'processo' => '0001234-56.2024.8.04.0001',
        'parte' => '  Joana ',
        'tribunal' => 'tjam',
        'inicio' => '2026-11-01',
        'fim' => '2026-11-06',
        'pagina' => '3',
    ]))->assertOk();
});

it('pagina os resultados pela query string', function () {
    $this->get(route('consulta.index', ['tribunal' => 'TJAM', 'pagina' => 2]))
        ->assertInertia(fn (Assert $page) => $page->where('resultados.meta.pagina', 2));
});

it('mostra estado vazio quando nada é encontrado', function () {
    $this->get(route('consulta.index', ['parte' => 'Ninguém Com Este Nome']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('resultados.data', 0)
            ->where('resultados.meta.total', 0));
});

it('valida os filtros sem redirecionar e sem consultar', function (array $query, string $campo) {
    $gateway = Mockery::mock(ComunicacaoGateway::class);
    $gateway->shouldReceive('tribunais')->andReturn([]);
    $gateway->shouldNotReceive('buscar');
    $this->app->instance(ComunicacaoGateway::class, $gateway);

    $this->get(route('consulta.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has("errors.{$campo}")
            ->where('resultados', null));
})->with([
    'processo incompleto' => [['processo' => '123'], 'processo'],
    'OAB sem UF' => [['oab' => '12345'], 'uf'],
    'UF sem OAB' => [['uf' => 'AM'], 'oab'],
    'UF inválida' => [['oab' => '1', 'uf' => 'XX'], 'uf'],
    'nome curto' => [['parte' => 'Jo'], 'parte'],
    'datas invertidas' => [['tribunal' => 'TJAM', 'inicio' => '2026-11-06', 'fim' => '2026-11-01'], 'fim'],
    'data inválida' => [['tribunal' => 'TJAM', 'inicio' => '06/11/2026'], 'inicio'],
]);

it('mostra mensagem amigável quando a API do CNJ falha', function (Throwable $e, string $tipo) {
    $gateway = Mockery::mock(ComunicacaoGateway::class);
    $gateway->shouldReceive('tribunais')->andThrow($e);
    $gateway->shouldReceive('buscar')->andThrow($e);
    $this->app->instance(ComunicacaoGateway::class, $gateway);

    $this->get(route('consulta.index', ['tribunal' => 'TJAM']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('erro.tipo', $tipo)
            ->whereType('erro.mensagem', 'string')
            ->where('resultados', null)
            ->where('tribunais', []));
})->with([
    'indisponível' => [new DjenUnavailableException('timeout'), 'indisponivel'],
    'limite' => [new DjenRateLimitedException('429', 60), 'limite'],
    'bloqueado' => [new DjenBlockedException('403', 403), 'bloqueado'],
]);

it('limita as consultas por IP', function () {
    config(['djen.rate_limit.public_per_minute' => 2]);

    $this->get(route('consulta.index'))->assertOk();
    $this->get(route('consulta.index'))->assertOk();
    $this->get(route('consulta.index'))->assertTooManyRequests();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->get(route('consulta.index'))->assertOk();
});

it('usa o gateway configurado', function (string $driver, string $classe) {
    config(['djen.gateway' => $driver]);
    $this->app->forgetInstance(ComunicacaoGateway::class);

    expect(app(ComunicacaoGateway::class))->toBeInstanceOf($classe);
})->with([
    ['fake', FakeComunicacaoGateway::class],
    ['api', DjenApiGateway::class],
]);

it('repassa palavra-chave, meio e nº da comunicação e os devolve nos filtros', function () {
    $gateway = Mockery::mock(ComunicacaoGateway::class);
    $gateway->shouldReceive('tribunais')->andReturn([]);
    $gateway->shouldReceive('buscar')->once()->withArgs(fn (FiltroConsulta $f) => $f->texto === 'alienação fiduciária'
        && $f->meio === 'E'
        && $f->numeroComunicacao === '5645'
        && $f->tribunal === 'TJAM')
        ->andReturn(new ResultadoConsulta([], 0, 1, 10));
    $this->app->instance(ComunicacaoGateway::class, $gateway);

    $this->get(route('consulta.index', ['tribunal' => 'tjam', 'texto' => ' alienação fiduciária ', 'meio' => 'e', 'comunicacao' => '56.45']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtros.texto', 'alienação fiduciária')
            ->where('filtros.meio', 'E')
            ->where('filtros.comunicacao', '5645')
            ->where('meios', ['D' => 'Diário de Justiça Eletrônico Nacional', 'E' => 'Plataforma Nacional de Editais']));
});

it('palavra-chave sozinha já busca e filtra o teor', function () {
    $this->get(route('consulta.index', ['texto' => 'alienação fiduciária']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resultados.meta.total', fn (int $t) => $t > 0 && $t < 45)
            ->where('resultados.data', fn ($itens) => collect($itens)->every(fn ($c) => str_contains(mb_strtolower($c['textoHtml']), 'alienação fiduciária'))));
});

it('filtra pelo meio no modo de demonstração', function () {
    $this->get(route('consulta.index', ['tribunal' => 'TJAM', 'meio' => 'E']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resultados.data', fn ($itens) => count($itens) > 0 && collect($itens)->every(fn ($c) => $c['meio'] === 'E' && $c['tipoComunicacao'] === 'Edital')));
});

it('valida palavra-chave, meio e nº da comunicação', function (array $query, string $campo) {
    $this->get(route('consulta.index', ['tribunal' => 'TJAM', ...$query]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has("errors.{$campo}")->where('resultados', null));
})->with([
    'palavra curta' => [['texto' => 'ab'], 'texto'],
    'meio inexistente' => [['meio' => 'X'], 'meio'],
    'comunicação longa' => [['comunicacao' => '1234567890123'], 'comunicacao'],
]);

it('filtra por tipo de comunicação e oferece os tipos conhecidos', function () {
    $this->get(route('consulta.index', ['tribunal' => 'TJAM', 'tipo' => 'Edital']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtros.tipo', 'Edital')
            ->where('tipos', ['Intimação', 'Lista de distribuição', 'Edital', 'Pauta de julgamento'])
            ->where('resultados.data', fn ($itens) => count($itens) > 0 && collect($itens)->every(fn ($c) => $c['tipoComunicacao'] === 'Edital')));
});

it('recusa tipo de comunicação desconhecido', function () {
    $this->get(route('consulta.index', ['tribunal' => 'TJAM', 'tipo' => 'Qualquer']))
        ->assertInertia(fn (Assert $page) => $page->has('errors.tipo')->where('resultados', null));
});
