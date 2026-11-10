<?php

use App\Models\Atividade;
use App\Models\RegistroBusca;
use App\Models\User;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Exceptions\DjenUnavailableException;
use App\Services\Djen\Gateways\FakeComunicacaoGateway;
use App\Support\Rastreio\TipoEvento;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;

const UA_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36';

beforeEach(function () {
    $this->app->instance(ComunicacaoGateway::class, new FakeComunicacaoGateway(CarbonImmutable::parse('2026-11-06')));
    RateLimiter::clear('djen-consulta');
    $this->withServerVariables(['REMOTE_ADDR' => '177.12.34.56', 'HTTP_USER_AGENT' => UA_CHROME]);
});

it('registra a busca com critério, filtros, resultado, tempo e origem pseudonimizada', function () {
    $this->get(route('consulta.index', ['oab' => '12345', 'uf' => 'am', 'tribunal' => 'tjam']))->assertOk();

    $b = RegistroBusca::sole();

    expect($b->only(['user_id', 'criterio', 'oab', 'uf', 'tribunal', 'status', 'ip_mascarado', 'dispositivo', 'navegador', 'sistema', 'bot', 'do_cache']))
        ->toBe([
            'user_id' => null, 'criterio' => 'oab', 'oab' => '12345', 'uf' => 'AM', 'tribunal' => 'TJAM',
            'status' => 'ok', 'ip_mascarado' => '177.12.34.0', 'dispositivo' => 'desktop',
            'navegador' => 'Chrome', 'sistema' => 'Windows', 'bot' => false, 'do_cache' => false,
        ])
        ->and($b->total_resultados)->toBeGreaterThan(0)
        ->and($b->duracao_ms)->toBeInt()
        ->and($b->visitante)->toHaveLength(16);
});

it('liga a busca ao usuário logado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('consulta.index', ['parte' => 'Joana']))->assertOk();

    expect(RegistroBusca::sole()->only(['user_id', 'criterio', 'parte']))
        ->toBe(['user_id' => $user->id, 'criterio' => 'parte', 'parte' => 'Joana']);
});

it('registra buscas sem resultado, inválidas e com erro do CNJ', function () {
    $this->get(route('consulta.index', ['parte' => 'Ninguém Com Este Nome']))->assertOk();
    $this->get(route('consulta.index', ['oab' => '12345']))->assertOk(); // sem UF

    $gateway = Mockery::mock(ComunicacaoGateway::class);
    $gateway->shouldReceive('buscar')->andThrow(new DjenUnavailableException('fora do ar'));
    $gateway->shouldReceive('tribunais')->andReturn([]);
    $this->app->instance(ComunicacaoGateway::class, $gateway);
    $this->get(route('consulta.index', ['advogado' => 'Helena']))->assertOk();

    expect(RegistroBusca::orderBy('id')->get(['status', 'criterio', 'erro_tipo', 'total_resultados'])->toArray())->toBe([
        ['status' => 'vazio', 'criterio' => 'parte', 'erro_tipo' => null, 'total_resultados' => 0],
        // OAB sem UF: inválida, mas o que era válido no filtro fica registrado.
        ['status' => 'invalido', 'criterio' => 'oab', 'erro_tipo' => null, 'total_resultados' => null],
        ['status' => 'erro', 'criterio' => 'advogado', 'erro_tipo' => 'indisponivel', 'total_resultados' => null],
    ]);
});

it('não registra busca quando a página abre sem critério', function () {
    $this->get(route('consulta.index', ['inicio' => '2026-11-01']))->assertOk();

    expect(RegistroBusca::count())->toBe(0);
});

it('marca robôs pelo User-Agent', function () {
    $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])
        ->get(route('consulta.index', ['oab' => '12345', 'uf' => 'AM']))->assertOk();

    expect(RegistroBusca::sole()->only(['bot', 'dispositivo']))->toBe(['bot' => true, 'dispositivo' => 'bot']);
});

it('registra a página vista sem a query string', function () {
    $this->get(route('consulta.index', ['parte' => 'Joana']))->assertOk();

    $e = Atividade::sole();

    expect($e->only(['tipo', 'rota', 'caminho', 'ip_mascarado']))->toBe([
        'tipo' => TipoEvento::PAGINA_VISTA, 'rota' => 'consulta.index', 'caminho' => '/consulta', 'ip_mascarado' => '177.12.34.0',
    ]);
});

it('não registra recargas parciais, prefetch nem redirecionamentos', function () {
    $this->get('/')->assertRedirect();
    $this->get(route('consulta.index'), ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Consulta/Index', 'X-Inertia-Partial-Data' => 'tribunais']);
    $this->get(route('consulta.index'), ['Purpose' => 'prefetch']);

    expect(Atividade::count())->toBe(0);
});

it('desliga tudo com DJEN_RASTREIO_ATIVO=false', function () {
    config(['djen.rastreio.ativo' => false]);

    $this->get(route('consulta.index', ['oab' => '12345', 'uf' => 'AM']))->assertOk();

    expect(RegistroBusca::count() + Atividade::count())->toBe(0);
});

it('nunca derruba a consulta se gravar o rastreio falhar', function () {
    Schema::drop('search_logs');

    $this->get(route('consulta.index', ['oab' => '12345', 'uf' => 'AM']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('erro', null)->has('resultados.data'));
});

it('registra a palavra-chave, o meio e o nº da comunicação', function () {
    $this->get(route('consulta.index', ['texto' => 'alienação fiduciária', 'meio' => 'D', 'comunicacao' => '5645']))->assertOk();

    expect(RegistroBusca::sole()->only(['criterio', 'texto', 'meio', 'numero_comunicacao']))
        ->toBe(['criterio' => 'texto', 'texto' => 'alienação fiduciária', 'meio' => 'D', 'numero_comunicacao' => '5645']);
});

it('registra o tipo de comunicação filtrado', function () {
    $this->get(route('consulta.index', ['oab' => '12345', 'uf' => 'AM', 'tipo' => 'Intimação']))->assertOk();

    expect(RegistroBusca::sole()->tipo_comunicacao)->toBe('Intimação');
});
