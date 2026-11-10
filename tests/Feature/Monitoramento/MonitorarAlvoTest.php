<?php

use App\Enums\TipoAlvo;
use App\Models\Alerta;
use App\Models\Communication;
use App\Models\MonitoringTarget;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Monitoramento\MonitorarAlvo;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\Support\GatewayEmMemoria;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-06 07:00', 'America/Manaus'));
    config([
        'djen.monitoring.timezone' => 'America/Manaus',
        'djen.monitoring.lookback_days' => 3,
        'djen.monitoring.itens_por_pagina' => 2,
        'djen.monitoring.max_paginas' => 5,
    ]);
    $this->gateway = new GatewayEmMemoria;
    $this->app->instance(ComunicacaoGateway::class, $this->gateway);
});

function monitorar(MonitoringTarget $alvo): int
{
    return app(MonitorarAlvo::class)->executar($alvo);
}

it('monta o filtro conforme o tipo do alvo e a janela de dias', function (string $tipo, array $esperado) {
    $alvo = MonitoringTarget::factory()->create([
        'tipo' => $tipo,
        'valor' => $tipo === 'parte' ? 'Joana Alves' : ($tipo === 'oab' ? '12345' : '00012345620248040001'),
        'uf' => $tipo === 'oab' ? 'AM' : null,
    ]);

    monitorar($alvo);

    $f = $this->gateway->filtros[0];
    expect([
        'oab' => $f->numeroOab, 'uf' => $f->ufOab, 'parte' => $f->nomeParte, 'processo' => $f->numeroProcesso,
    ])->toBe($esperado)
        ->and($f->dataInicio->toDateString())->toBe('2026-11-03')
        ->and($f->dataFim->toDateString())->toBe('2026-11-06')
        ->and($f->porPagina)->toBe(2)
        ->and($f->pagina)->toBe(1);
})->with([
    'OAB' => ['oab', ['oab' => '12345', 'uf' => 'AM', 'parte' => null, 'processo' => null]],
    'parte' => ['parte', ['oab' => null, 'uf' => null, 'parte' => 'Joana Alves', 'processo' => null]],
    'processo' => ['processo', ['oab' => null, 'uf' => null, 'parte' => null, 'processo' => '00012345620248040001']],
]);

it('percorre todas as páginas até o limite configurado', function () {
    foreach (range(1, 5) as $i) {
        $this->gateway->adicionar("h{$i}");
    }

    expect(monitorar(MonitoringTarget::factory()->create()))->toBe(5)
        ->and(array_map(fn ($f) => $f->pagina, $this->gateway->filtros))->toBe([1, 2, 3]);

    config(['djen.monitoring.max_paginas' => 1]);
    $this->gateway->filtros = [];
    monitorar(MonitoringTarget::factory()->create());

    expect($this->gateway->filtros)->toHaveCount(1);
});

it('salva as comunicações e cria um alerta por comunicação', function () {
    $this->gateway->adicionar('h1');
    $this->gateway->adicionar('h2');
    $alvo = MonitoringTarget::factory()->create();

    expect(monitorar($alvo))->toBe(2)
        ->and(Communication::count())->toBe(2)
        ->and($alvo->alertas()->count())->toBe(2)
        ->and($alvo->fresh()->ultima_verificacao_em)->not->toBeNull();

    $c = Communication::firstWhere('hash', 'h1');
    expect($c->tribunal)->toBe('TJAM')
        ->and($c->destinatarios)->toEqual([['nome' => 'Parte Fictícia', 'polo' => null]])
        // jsonb do Postgres não preserva a ordem das chaves.
        ->and($c->advogados)->toEqual([['nome' => 'Advogada Fictícia', 'numeroOab' => '12345', 'ufOab' => 'AM']])
        ->and($c->toData()->numeroProcessoFormatado())->toBe('0001234-56.2024.8.04.0001');
});

it('deduplica: uma segunda execução não cria alertas repetidos', function () {
    $this->gateway->adicionar('h1');
    $alvo = MonitoringTarget::factory()->create();

    expect(monitorar($alvo))->toBe(1)
        ->and(monitorar($alvo))->toBe(0);

    $this->gateway->adicionar('h2');

    expect(monitorar($alvo))->toBe(1)
        ->and(Alerta::count())->toBe(2)
        ->and(Communication::count())->toBe(2);
});

it('guarda a comunicação uma vez só, mas alerta cada alvo que a encontra', function () {
    $this->gateway->adicionar('compartilhada');
    $a = MonitoringTarget::factory()->create();
    $b = MonitoringTarget::factory()->parte('Parte Fictícia')->create();

    expect(monitorar($a))->toBe(1)
        ->and(monitorar($b))->toBe(1)
        ->and(Communication::count())->toBe(1)
        ->and(Alerta::count())->toBe(2);
});

it('tem índices únicos que impedem duplicatas mesmo sem o serviço', function () {
    $this->gateway->adicionar('h1');
    $alvo = MonitoringTarget::factory()->create();
    monitorar($alvo);

    // Cada tentativa num savepoint: no Postgres, um erro aborta a transação do teste.
    expect(fn () => DB::transaction(fn () => Communication::create(Communication::atributosDe($this->gateway->comunicacoes[0]))))
        ->toThrow(UniqueConstraintViolationException::class);

    expect(fn () => DB::transaction(fn () => Alerta::create([
        'communication_id' => Communication::first()->id,
        'monitoring_target_id' => $alvo->id,
    ])))->toThrow(UniqueConstraintViolationException::class);
});

it('descreve os alvos', function () {
    expect(MonitoringTarget::factory()->oab('12345', 'AM')->make()->descricao())->toBe('OAB 12345/AM')
        ->and(MonitoringTarget::factory()->parte('Joana')->make()->descricao())->toBe('Parte: Joana')
        ->and(MonitoringTarget::factory()->processo('00012345620248040001')->make()->descricao())->toBe('Processo 0001234-56.2024.8.04.0001')
        ->and(MonitoringTarget::factory()->make(['apelido' => 'Escritório'])->descricao())->toBe('Escritório')
        ->and(TipoAlvo::Oab->rotulo())->toBe('OAB');
});
