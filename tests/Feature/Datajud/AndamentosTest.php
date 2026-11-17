<?php

use App\Services\Datajud\DatajudClient;
use App\Services\Datajud\DatajudException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const DATAJUD = 'https://api-publica.datajud.cnj.jus.br';
const NUMERO = '01234567820268041000';

function fixtureDatajud(string $nome): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/datajud/{$nome}")), true);
}

beforeEach(function () {
    config(['datajud.base_url' => DATAJUD, 'datajud.api_key' => 'chave-teste', 'datajud.ativo' => true]);
    app()->forgetInstance(DatajudClient::class);
    Http::preventStrayRequests();
});

it('busca o processo no índice do tribunal com a chave pública', function () {
    Http::fake([DATAJUD.'/api_publica_tjam/_search' => Http::response(fixtureDatajud('processo.json'))]);

    $processos = app(DatajudClient::class)->processos('tjam', NUMERO);

    Http::assertSent(fn (Request $r) => $r->method() === 'POST'
        && $r->url() === DATAJUD.'/api_publica_tjam/_search'
        && $r->header('Authorization') === ['APIKey chave-teste']
        && $r['query'] === ['match' => ['numeroProcesso' => NUMERO]]);

    $p = $processos[0];
    expect($processos)->toHaveCount(1)
        ->and($p->tribunal)->toBe('TJAM')
        ->and($p->grau)->toBe('JE')
        ->and($p->classe)->toBe('Procedimento do Juizado Especial da Fazenda Pública')
        ->and($p->codigoClasse)->toBe(14695)
        ->and($p->assuntos)->toBe(['Obrigação de Fazer / Não Fazer'])
        ->and($p->orgaoJulgador)->toBe('1º Juizado Especial da Fazenda Pública Estadual e Municipal')
        ->and($p->dataAjuizamento?->format('Y-m-d H:i:s'))->toBe('2025-07-14 18:53:27')
        ->and($p->sistema)->toBe('Eproc')
        ->and($p->movimentos)->toHaveCount(57);

    // Do mais recente para o mais antigo; a distribuição (com o complemento "sorteio") é das primeiras.
    $datas = array_map(fn ($m) => $m->dataHora->getTimestamp(), $p->movimentos);
    $ordenadas = $datas;
    rsort($ordenadas);
    expect($datas)->toBe($ordenadas);

    $distribuicao = collect($p->movimentos)->firstWhere('nome', 'Distribuição');
    expect($distribuicao->complementos)->toBe(['sorteio'])
        ->and($distribuicao->codigo)->toBe(26);
});

it('ignora registros de outro número que o match por texto trouxer', function () {
    Http::fake([DATAJUD.'/*' => Http::response(fixtureDatajud('processos.json'))]);

    expect(app(DatajudClient::class)->processos('TJAM', NUMERO))->toHaveCount(1);
});

it('guarda os andamentos em cache', function () {
    Http::fake([DATAJUD.'/*' => Http::response(fixtureDatajud('processo.json'))]);

    app(DatajudClient::class)->processos('TJAM', NUMERO);
    app(DatajudClient::class)->processos('TJAM', NUMERO);

    Http::assertSentCount(1);
});

it('traduz a sobrecarga do DataJud', function () {
    Http::fake([DATAJUD.'/*' => Http::response(fixtureDatajud('sobrecarga-429.json'), 429)]);

    expect(fn () => app(DatajudClient::class)->processos('TJAM', NUMERO))
        ->toThrow(fn (DatajudException $e) => expect($e->sobrecarga)->toBeTrue());
});

it('recusa tribunal não habilitado sem chamar a API', function () {
    expect(fn () => app(DatajudClient::class)->processos('TJSP', NUMERO))->toThrow(DatajudException::class);

    Http::assertNothingSent();
});

it('devolve os andamentos em JSON para o painel', function () {
    Http::fake([DATAJUD.'/*' => Http::response(fixtureDatajud('processo.json'))]);

    $this->getJson(route('processos.andamentos', ['numero' => NUMERO, 'tribunal' => 'TJAM']))
        ->assertOk()
        ->assertJsonPath('data.0.grau', 'JE')
        ->assertJsonPath('data.0.grauRotulo', 'Juizado Especial')
        ->assertJsonPath('data.0.dataAjuizamento', '2025-07-14T18:53:27Z')
        ->assertJsonPath('data.0.movimentos.0.dataHora', fn ($d) => is_string($d))
        ->assertJsonCount(57, 'data.0.movimentos');
});

it('devolve lista vazia quando o DataJud não tem o processo', function () {
    Http::fake([DATAJUD.'/*' => Http::response(['hits' => ['total' => ['value' => 0, 'relation' => 'eq'], 'hits' => []]])]);

    $this->getJson(route('processos.andamentos', ['numero' => NUMERO, 'tribunal' => 'TJAM']))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('responde 503 com mensagem quando o DataJud falha', function () {
    Http::fake([DATAJUD.'/*' => Http::response(fixtureDatajud('sobrecarga-429.json'), 429)]);

    $this->getJson(route('processos.andamentos', ['numero' => NUMERO, 'tribunal' => 'TJAM']))
        ->assertStatus(503)
        ->assertJsonPath('erro.mensagem', 'O DataJud (CNJ) está sobrecarregado agora. Tente de novo em alguns minutos.');
});

it('responde 404 para tribunal sem andamentos', function () {
    $this->getJson(route('processos.andamentos', ['numero' => NUMERO, 'tribunal' => 'TJSP']))
        ->assertNotFound()
        ->assertJsonPath('erro.tipo', 'nao_suportado');

    Http::assertNothingSent();
});

it('não aceita número de processo fora do padrão', function () {
    $this->getJson('/processos/123/andamentos?tribunal=TJAM')->assertNotFound();
});

it('compartilha os tribunais habilitados com o front', function () {
    $this->get(route('consulta.index'))
        ->assertInertia(fn ($page) => $page->where('datajudTribunais', ['TJAM']));

    config(['datajud.ativo' => false]);

    $this->get(route('consulta.index'))
        ->assertInertia(fn ($page) => $page->where('datajudTribunais', []));
});
