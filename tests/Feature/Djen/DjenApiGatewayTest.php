<?php

use App\Data\Djen\FiltroConsulta;
use App\Models\Alerta;
use App\Models\MonitoringTarget;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\DjenClient;
use App\Services\Djen\Exceptions\DjenBlockedException;
use App\Services\Djen\Gateways\DjenApiGateway;
use App\Services\Monitoramento\MonitorarAlvo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;

const API = 'https://comunicaapi.pje.jus.br/api/v1';

beforeEach(function () {
    config(['djen.gateway' => 'api', 'djen.base_url' => API, 'djen.retry.times' => 0, 'djen.itens_por_pagina' => 20]);
    app()->forgetInstance(DjenClient::class);
    app()->forgetInstance(ComunicacaoGateway::class);
    Http::preventStrayRequests();
    Sleep::fake();
    $this->fixture = json_decode(file_get_contents(base_path('tests/Fixtures/djen/comunicacao.json')), true);
});

function queryDe(Request $request): array
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

    return $q;
}

it('é o gateway padrão quando DJEN_GATEWAY=api', function () {
    expect(app(ComunicacaoGateway::class))->toBeInstanceOf(DjenApiGateway::class);
});

it('envia todos os filtros com os nomes da API e lê a resposta real', function () {
    Http::fake([API.'/comunicacao*' => Http::response($this->fixture)]);

    $r = app(ComunicacaoGateway::class)->buscar(new FiltroConsulta(
        numeroOab: '20413-O', ufOab: 'MT', nomeParte: 'Joana', nomeAdvogado: 'Helena',
        numeroProcesso: '00012345620248040001', tribunal: 'TJAM',
        dataInicio: CarbonImmutable::parse('2026-11-01'), dataFim: CarbonImmutable::parse('2026-11-06'),
        pagina: 3, porPagina: 20,
    ));

    Http::assertSent(fn (Request $req) => str_starts_with($req->url(), API.'/comunicacao?') && queryDe($req) === [
        'numeroOab' => '20413-O', 'ufOab' => 'MT', 'nomeParte' => 'Joana', 'nomeAdvogado' => 'Helena',
        'numeroProcesso' => '00012345620248040001', 'siglaTribunal' => 'TJAM',
        'dataDisponibilizacaoInicio' => '2026-11-01', 'dataDisponibilizacaoFim' => '2026-11-06',
        'pagina' => '3', 'itensPorPagina' => '20',
    ]);
    expect($r->comunicacoes)->toHaveCount(8)
        ->and($r->total)->toBe(10000)
        ->and($r->pagina)->toBe(3);
});

it('omite filtros vazios', function () {
    Http::fake([API.'/comunicacao*' => Http::response($this->fixture)]);

    app(ComunicacaoGateway::class)->buscar(new FiltroConsulta(tribunal: 'TJSP', pagina: 1, porPagina: 5));

    Http::assertSent(fn (Request $req) => queryDe($req) === ['siglaTribunal' => 'TJSP', 'pagina' => '1', 'itensPorPagina' => '5']);
});

it('usa o cache do DjenClient para a mesma busca', function () {
    Http::fake([API.'/comunicacao*' => Http::response($this->fixture)]);
    $gateway = app(ComunicacaoGateway::class);

    $gateway->buscar(new FiltroConsulta(tribunal: 'TJSP'));
    $gateway->buscar(new FiltroConsulta(tribunal: 'TJSP'));

    Http::assertSentCount(1);
});

it('propaga o bloqueio geográfico', function () {
    Http::fake([API.'/comunicacao*' => Http::response('<html>blocked</html>', 403)]);

    expect(fn () => app(ComunicacaoGateway::class)->buscar(new FiltroConsulta(tribunal: 'TJSP')))
        ->toThrow(DjenBlockedException::class);
});

it('lista os tribunais da configuração, sem chamar a API', function () {
    $tribunais = app(ComunicacaoGateway::class)->tribunais();
    $siglas = array_map(fn ($t) => $t->sigla, $tribunais);

    expect($siglas)->toContain('TJMG', 'TJDFT', 'TRF6', 'TRT24', 'TJMMG', 'STJ', 'SEEU')
        ->and(count($siglas))->toBe(count(array_unique($siglas)));
    Http::assertNothingSent();
});

it('as siglas da resposta real existem na lista de tribunais', function () {
    $siglas = array_keys(config('tribunais'));

    foreach ($this->fixture['items'] as $item) {
        expect($siglas)->toContain($item['siglaTribunal']);
    }
});

it('a Consulta mostra as comunicações reais', function () {
    Http::fake([API.'/comunicacao*' => Http::response($this->fixture)]);

    $this->get(route('consulta.index', ['oab' => '95187', 'uf' => 'MG']))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->where('erro', null)
            ->has('resultados.data', 8)
            ->where('resultados.meta.total', 10000)
            ->where('resultados.data.0.tribunal', 'TJMG')
            ->where('resultados.data.0.orgaoJulgador', 'TJMG - 6ª CÂMARA CÍVEL')
            ->where('resultados.data.0.numeroProcesso', '5000567-19.2022.8.13.0395')
            ->has('tribunais', count(config('tribunais'))));

    Http::assertSent(fn (Request $req) => queryDe($req)['numeroOab'] === '95187' && queryDe($req)['ufOab'] === 'MG');
});

it('o monitoramento grava as comunicações reais e deduplica', function () {
    Notification::fake();
    Http::fake([API.'/comunicacao*' => Http::response($this->fixture)]);
    config(['djen.monitoring.itens_por_pagina' => 100, 'djen.monitoring.max_paginas' => 1]);
    $alvo = MonitoringTarget::factory()->oab('95187', 'MG')->create();

    $novos = app(MonitorarAlvo::class)->executar($alvo);
    $denovo = app(MonitorarAlvo::class)->executar($alvo);

    expect($novos)->toBe(8)
        ->and($denovo)->toBe(0)
        ->and(Alerta::count())->toBe(8);
    Http::assertSent(fn (Request $req) => queryDe($req)['itensPorPagina'] === '100');
});

it('envia palavra-chave, meio e nº da comunicação com os nomes da API', function () {
    Http::fake([API.'/comunicacao*' => Http::response($this->fixture)]);

    app(ComunicacaoGateway::class)->buscar(new FiltroConsulta(
        tribunal: 'TJAM', nomeParte: 'bradesco', texto: 'alienação fiduciária', meio: 'D', numeroComunicacao: '5645',
        dataInicio: CarbonImmutable::parse('2026-11-04'), dataFim: CarbonImmutable::parse('2026-11-10'), porPagina: 100,
    ));

    // Mesmo formato do request real visto no navegador (10/11/2026).
    Http::assertSent(fn (Request $req) => queryDe($req) === [
        'nomeParte' => 'bradesco', 'siglaTribunal' => 'TJAM', 'texto' => 'alienação fiduciária',
        'meio' => 'D', 'numeroComunicacao' => '5645',
        'dataDisponibilizacaoInicio' => '2026-11-04', 'dataDisponibilizacaoFim' => '2026-11-10',
        'pagina' => '1', 'itensPorPagina' => '100',
    ]);
});

it('lê id, nº da comunicação, meio, classe e polo da resposta real', function () {
    Http::fake([API.'/comunicacao*' => Http::response($this->fixture)]);

    $c = app(ComunicacaoGateway::class)->buscar(new FiltroConsulta(tribunal: 'TJMG'))->comunicacoes[0];

    expect($c->idCnj)->toBe(900000001)
        ->and($c->numeroComunicacao)->toBe('1')
        ->and($c->meio)->toBe('D')
        ->and($c->meioCompleto)->toBe('Diário de Justiça Eletrônico Nacional')
        // A API manda "APELAçãO / REMESSA NECESSáRIA": normalizado em maiúsculas.
        ->and($c->classe)->toBe('APELAÇÃO / REMESSA NECESSÁRIA')
        ->and($c->codigoClasse)->toBe('1728')
        ->and($c->destinatarios[0]->polo)->toBe('A');
});

it('envia o tipo de comunicação como tipoComunicacao', function () {
    Http::fake([API.'/comunicacao*' => Http::response($this->fixture)]);

    app(ComunicacaoGateway::class)->buscar(new FiltroConsulta(numeroOab: '5055', ufOab: 'AM', tipoComunicacao: 'Intimação'));

    Http::assertSent(fn (Request $req) => (queryDe($req)['tipoComunicacao'] ?? null) === 'Intimação');
});
