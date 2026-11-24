<?php

use App\Data\RaioX\RespostaModelo;
use App\Data\RaioX\UsoIA;
use App\Models\IA\ChamadaIA;
use App\Models\User;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Gateways\FakeComunicacaoGateway;
use App\Services\IA\CustoIA;
use App\Services\RaioX\ClassificadorIA;
use App\Services\RaioX\Contracts\ClassificadorDecisoes;
use App\Services\RaioX\Contracts\ModeloLinguagem;
use App\Services\RaioX\GeradorRaioX;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['raiox.custos.cotacao_usd_brl' => 5.0]);
});

it('calcula o custo em dólar e real pela tabela do modelo', function () {
    // Opus 5.5: entrada 4, saída 20, cache leitura 0,20, escrita 5 (US$ por milhão).
    $c = app(CustoIA::class)->calcular(new UsoIA('claude-opus-5-5', entrada: 2000, cacheLeitura: 1500, cacheEscrita: 1500, saida: 300));

    // (2000×4 + 1500×0,2 + 1500×5 + 300×20) / 1e6 = 0,0218
    expect($c['usd'])->toBe(0.0218)
        ->and($c['brl'])->toBe(0.109)
        ->and($c['cotacao'])->toBe(5.0);
});

it('registra tokens sem valor quando o modelo não tem preço', function () {
    $c = app(CustoIA::class)->calcular(new UsoIA('modelo-desconhecido', entrada: 1000));

    expect($c['usd'])->toBeNull()->and($c['brl'])->toBeNull();
});

it('grava uma chamada com custo para cada processo classificado pela IA', function () {
    config(['queue.default' => 'sync', 'raiox.coleta.pausa_ms' => 0]);
    $this->app->instance(ComunicacaoGateway::class, new FakeComunicacaoGateway(CarbonImmutable::parse('2026-11-06')));

    $modelo = new class implements ModeloLinguagem
    {
        public function gerarJson(string $sistema, string $usuario, array $esquema): RespostaModelo
        {
            return new RespostaModelo(
                ['resultado' => 'exito', 'extincao_sem_merito' => false, 'instancia' => 'sentenca', 'orgao_julgador' => '', 'vara_origem' => '',
                    'grupo_origem' => 'nao_identificada', 'tema' => 'outros', 'parte_contraria' => '', 'polo_cliente' => 'ativo',
                    'data_decisao' => '', 'hash_decisao' => '', 'confianca' => 'alta', 'justificativa' => 'ok'],
                'end_turn', tokensEntrada: 1000, tokensSaida: 200, tokensCacheLeitura: 3000, tokensCacheEscrita: 0, duracaoMs: 1500,
            );
        }

        public function nome(): string
        {
            return 'claude-opus-5-5';
        }
    };
    $this->app->instance(ClassificadorDecisoes::class, new ClassificadorIA($modelo));

    $r = User::factory()->create()->relatoriosRaioX()->create(['oab' => '54321', 'uf' => 'AM', 'inicio' => '2025-11-06', 'fim' => '2026-11-06']);
    app(GeradorRaioX::class)->iniciar($r);

    $candidatos = $r->processos()->where('candidato', true)->count();
    $chamada = ChamadaIA::query()->where('relatorio_id', $r->id)->first();

    // (1000×4 + 3000×0,2 + 200×20) / 1e6 = 0,0086 dólar por chamada
    expect(ChamadaIA::query()->where('relatorio_id', $r->id)->count())->toBe($candidatos)
        ->and($candidatos)->toBeGreaterThan(0)
        ->and((float) $chamada->custo_usd)->toBe(0.0086)
        ->and((float) $chamada->custo_brl)->toBe(0.043)
        ->and($chamada->tokens_cache_leitura)->toBe(3000)
        ->and($chamada->duracao_ms)->toBe(1500)
        ->and($chamada->resultado)->toBe('ok')
        ->and($r->fresh()->ia_tokens_entrada)->toBe(4000 * $candidatos);
});

it('registra a chamada que falhou, sem tokens', function () {
    $modelo = new class implements ModeloLinguagem
    {
        public function gerarJson(string $sistema, string $usuario, array $esquema): RespostaModelo
        {
            return new RespostaModelo(null, 'refusal', tokensEntrada: 800, tokensSaida: 5);
        }

        public function nome(): string
        {
            return 'claude-opus-5-5';
        }
    };

    $c = (new ClassificadorIA($modelo))->classificar(new App\Data\RaioX\ContextoProcesso('1', null, '1', 'AM', null, null, []));

    expect($c->uso?->resultado)->toBe('recusa')
        ->and($c->uso?->entrada)->toBe(800)
        ->and($c->precisaConferencia())->toBeTrue();
});

it('mostra o painel de custos só para o admin, com totais em dólar e real', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $r = User::factory()->create()->relatoriosRaioX()->create(['oab' => '54321', 'uf' => 'AM', 'inicio' => '2025-11-06', 'fim' => '2026-11-06']);
    $custo = app(CustoIA::class);
    $custo->registrar(new UsoIA('claude-opus-5-5', entrada: 1_000_000, saida: 100_000), $r->id); // US$ 6
    $custo->registrar(new UsoIA('claude-opus-5-5', resultado: 'erro'), $r->id);

    $this->actingAs(User::factory()->create())->get(route('admin.custos-ia'))->assertForbidden();

    $this->actingAs($admin)->get(route('admin.custos-ia', ['periodo' => '7d']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/CustosIA')
            ->where('resumo.chamadas', 2)
            ->where('resumo.relatorios', 1)
            ->where('resumo.usd', 6)
            ->where('resumo.brl', 30)
            ->where('resumo.falhas', 1)
            ->where('resumo.mediaBrlPorRelatorio', 30)
            ->where('cotacao', 5)
            ->where('porRelatorio.0.oab', '54321/AM')
            ->where('porRelatorio.0.brl', 30)
            ->has('chamadas.data', 2)
            ->has('porDia', 7));

    $this->actingAs($admin)->get(route('admin.raiox.index'))
        ->assertInertia(fn (Assert $page) => $page->where('relatorios.0.ia.custoBrl', 30)->where('relatorios.0.ia.custoUsd', 6));
});

it('mantém o histórico de custo quando o Raio-X é excluído', function () {
    $r = User::factory()->create()->relatoriosRaioX()->create(['oab' => '1', 'uf' => 'AM', 'inicio' => '2025-11-06', 'fim' => '2026-11-06']);
    app(CustoIA::class)->registrar(new UsoIA('claude-opus-5-5', entrada: 100), $r->id);

    $r->delete();

    expect(ChamadaIA::query()->count())->toBe(1)
        ->and(ChamadaIA::query()->first()->relatorio_id)->toBeNull();
});
