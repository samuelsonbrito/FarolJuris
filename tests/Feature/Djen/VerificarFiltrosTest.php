<?php

use App\Services\Djen\DjenClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    config(['djen.base_url' => 'https://djen.test/api/v1', 'djen.retry.times' => 0]);
    app()->forgetInstance(DjenClient::class);
    Http::preventStrayRequests();
    Sleep::fake();
    $this->itens = json_decode(file_get_contents(base_path('tests/Fixtures/djen/comunicacao.json')), true)['items'];
});

/** Item da fixture ajustado para satisfazer o filtro. */
function itemFiltrado(array $base, array $q, int $n): array
{
    $i = $base;
    $i['hash'] = 'h'.($q['pagina'] ?? 1).'-'.$n;
    $i['siglaTribunal'] = $q['siglaTribunal'] ?? $i['siglaTribunal'];
    $i['data_disponibilizacao'] = $q['dataDisponibilizacaoInicio'] ?? $i['data_disponibilizacao'];
    $i['numero_processo'] = $q['numeroProcesso'] ?? $i['numero_processo'];
    if (isset($q['numeroOab'])) {
        $i['destinatarioadvogados'][0]['advogado']['numero_oab'] = $q['numeroOab'];
        $i['destinatarioadvogados'][0]['advogado']['uf_oab'] = $q['ufOab'];
    }
    if (isset($q['texto'])) {
        $i['texto'] = 'Conteúdo sobre '.mb_strtoupper($q['texto']).' no teor.';
    }
    if (isset($q['meio'])) {
        $i['meio'] = $q['meio'];
    }
    if (isset($q['numeroComunicacao'])) {
        $i['numeroComunicacao'] = (int) $q['numeroComunicacao'];
    }
    if (isset($q['nomeParte'])) {
        $i['destinatarios'][0]['nome'] = mb_strtoupper($q['nomeParte']).' DA SILVA';
    }

    return $i;
}

it('aprova quando a API respeita os filtros', function () {
    Http::fake(function (Request $req) {
        parse_str(parse_url($req->url(), PHP_URL_QUERY), $q);

        return Http::response(['count' => 5, 'items' => array_map(fn ($n) => itemFiltrado($this->itens[0], $q, $n), range(1, 3))]);
    });

    $this->artisan('djen:verificar-filtros', [
        '--tribunal' => 'TJAM', '--data' => '2026-11-05', '--oab' => '20413-O', '--uf' => 'MT',
        '--processo' => '0001234-56.2024.8.04.0001', '--parte' => 'Joana',
    ])
        ->expectsOutputToContain('Os filtros testados funcionam')
        ->assertSuccessful();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'dataDisponibilizacaoInicio=2026-11-05') && str_contains($r->url(), 'itensPorPagina=5'));
});

it('reprova o parâmetro que a API ignora', function () {
    // Simula a API ignorando numeroOab: devolve itens de outro advogado.
    Http::fake(function (Request $req) {
        parse_str(parse_url($req->url(), PHP_URL_QUERY), $q);
        unset($q['numeroOab'], $q['ufOab']);

        return Http::response(['count' => 5, 'items' => array_map(fn ($n) => itemFiltrado($this->itens[0], $q, $n), range(1, 3))]);
    });

    $this->artisan('djen:verificar-filtros', ['--tribunal' => 'TJAM', '--data' => '2026-11-05', '--oab' => '20413-O', '--uf' => 'MT'])
        ->expectsOutputToContain('FALHOU')
        ->expectsOutputToContain('1 verificação(ões) falharam')
        ->assertFailed();
});

it('trata resultado vazio como inconclusivo, não como falha', function () {
    Http::fake(fn () => Http::response(['count' => 0, 'items' => []]));

    $this->artisan('djen:verificar-filtros', ['--tribunal' => 'TJAC', '--data' => '2026-11-05'])
        ->expectsOutputToContain('SEM DADOS')
        ->assertSuccessful();
});

it('acusa erro quando o servidor está bloqueado', function () {
    Http::fake(fn () => Http::response('blocked', 403));

    $this->artisan('djen:verificar-filtros', ['--data' => '2026-11-05'])
        ->expectsOutputToContain('fora do Brasil')
        ->expectsOutputToContain('Nada foi verificado')
        ->doesntExpectOutputToContain('Ajuste os nomes')
        ->assertFailed();
});

it('confere palavra-chave, meio e nº da comunicação', function () {
    Http::fake(function (Request $req) {
        parse_str(parse_url($req->url(), PHP_URL_QUERY), $q);

        return Http::response(['count' => 3, 'items' => array_map(fn ($n) => itemFiltrado($this->itens[0], $q, $n), range(1, 3))]);
    });

    $this->artisan('djen:verificar-filtros', [
        '--tribunal' => 'TJAM', '--data' => '2026-11-05',
        '--texto' => 'alienação fiduciária', '--meio' => 'e', '--comunicacao' => '5645',
    ])
        ->expectsOutputToContain('texto')
        ->expectsOutputToContain('Os filtros testados funcionam')
        ->assertSuccessful();

    Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()), 'texto=alienação fiduciária'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'meio=E'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'numeroComunicacao=5645'));
});

it('acusa quando a API ignora a palavra-chave', function () {
    Http::fake(fn () => Http::response(['count' => 3, 'items' => [$this->itens[0], $this->itens[1]]]));

    $this->artisan('djen:verificar-filtros', ['--tribunal' => 'TJAM', '--data' => '2026-11-05', '--texto' => 'xyzinexistente'])
        ->expectsOutputToContain('FALHOU')
        ->assertFailed();
});
