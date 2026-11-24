<?php

use App\Models\RaioX\RelatorioRaioX;
use App\Models\User;
use App\Services\RaioX\MetricasRaioX;

function relatorioComProcessos(array $processos, array $extra = []): RelatorioRaioX
{
    $r = User::factory()->create()->relatoriosRaioX()->create([
        'oab' => '12345', 'uf' => 'AM', 'inicio' => '2025-11-22', 'fim' => '2026-11-22', 'status' => 'pronto', ...$extra,
    ]);

    foreach ($processos as $i => $p) {
        $r->processos()->create(['numero_processo' => str_pad((string) $i, 20, '0', STR_PAD_LEFT), 'candidato' => true, ...$p]);
    }

    return $r;
}

it('calcula a taxa de êxito sobre os decididos, fora os em conferência', function () {
    $r = relatorioComProcessos([
        ['resultado' => 'exito', 'instancia' => 'sentenca'],
        ['resultado' => 'exito_parcial', 'instancia' => 'sentenca'],
        ['resultado' => 'acordo', 'instancia' => 'sentenca'],
        ['resultado' => 'insucesso', 'instancia' => 'recurso_cliente', 'extincao_sem_merito' => true],
        ['resultado' => 'exito', 'instancia' => 'recurso_cliente', 'precisa_conferencia' => true],
        ['resultado' => 'sem_decisao', 'candidato' => false],
    ]);

    $m = app(MetricasRaioX::class)->calcular($r);
    $vg = $m['visaoGeral'];

    expect($vg['decididos'])->toBe(4)
        ->and($vg['favoraveis'])->toBe(2)
        ->and($vg['taxa'])->toBe(0.5)
        ->and($vg['pendentes'])->toBe(1)
        ->and($vg['extincoes'])->toBe(1)
        ->and($vg['ativos'])->toBe(6)
        ->and($vg['semDecisao'])->toBe(1)
        ->and($m['conferencia'])->toHaveCount(1);

    $sentenca = collect($m['porInstancia'])->firstWhere('chave', 'sentenca');
    expect($sentenca['decididos'])->toBe(3)
        ->and($sentenca['taxa'])->toBe(round(2 / 3, 4));
});

it('rotula a origem pela capital da UF e separa as varas da capital', function () {
    $r = relatorioComProcessos([
        ['resultado' => 'exito', 'grupo_origem' => 'civel_capital', 'vara_origem' => '22ª Vara Cível'],
        ['resultado' => 'exito', 'grupo_origem' => 'civel_capital', 'vara_origem' => '2ª Vara Cível'],
        ['resultado' => 'insucesso', 'grupo_origem' => 'juizado_capital', 'vara_origem' => '19º JEC'],
        ['resultado' => 'insucesso', 'grupo_origem' => 'interior'],
        ['resultado' => 'insucesso'],
    ]);

    $m = app(MetricasRaioX::class)->calcular($r);

    expect(collect($m['porOrigem'])->pluck('rotulo')->all())->toBe([
        'Varas cíveis de Manaus', 'Juizados Especiais Cíveis de Manaus', 'Interior do Amazonas', 'Vara não identificada',
    ])
        ->and(collect($m['varasCapital']['civeis'])->pluck('rotulo')->all())->toBe(['2ª Vara Cível', '22ª Vara Cível'])
        ->and($m['varasCapital']['capital'])->toBe('Manaus');
});

it('junta grafias da mesma parte e resume a cauda em "Demais partes contrárias"', function () {
    $processos = [
        ['resultado' => 'exito', 'parte_contraria' => 'BRADESCO'],
        ['resultado' => 'insucesso', 'parte_contraria' => 'Bradesco '],
    ];

    foreach (range(1, 11) as $i) {
        $processos[] = ['resultado' => 'insucesso', 'parte_contraria' => "Empresa {$i}"];
    }

    $m = app(MetricasRaioX::class)->calcular(relatorioComProcessos($processos));

    expect($m['porParte'][0])->toMatchArray(['rotulo' => 'Bradesco', 'decididos' => 2, 'taxa' => 0.5])
        ->and($m['porParte'])->toHaveCount(11)
        ->and(end($m['porParte'])['rotulo'])->toBe('Demais partes contrárias')
        ->and(end($m['porParte'])['decididos'])->toBe(2);
});

it('calcula quanto cada êxito precisa pagar com o custo informado', function () {
    $processos = array_merge(
        array_fill(0, 33, ['resultado' => 'exito']),
        array_fill(0, 256, ['resultado' => 'sem_decisao', 'candidato' => false]),
    );

    $m = app(MetricasRaioX::class)->calcular(relatorioComProcessos($processos, ['custo_mensal' => 16000, 'honorario_medio' => 7000]));
    $c = $m['custo'];

    // O caso do modelo: 289 ativos, 33 êxitos, R$ 16 mil/mês.
    expect($c['ativos'])->toBe(289)
        ->and($c['processosPorExito'])->toBe(8.8)
        ->and($c['custoTotal'])->toEqual(192000)
        ->and($c['custoPorProcesso'])->toEqual(664.36)
        ->and($c['honorarioMinimo'])->toEqual(5818.18)
        ->and($c['margem'])->toEqual(1181.82);
});

it('sem custo informado, a calculadora fica em branco', function () {
    $c = app(MetricasRaioX::class)->calcular(relatorioComProcessos([['resultado' => 'exito']]))['custo'];

    expect($c['custoTotal'])->toBeNull()
        ->and($c['honorarioMinimo'])->toBeNull()
        ->and($c['processosPorExito'])->toEqual(1.0);
});

it('aponta onde vai melhor e pior só com amostra mínima', function () {
    config(['raiox.minimo_para_destaque' => 3]);
    $r = relatorioComProcessos([
        ...array_fill(0, 3, ['resultado' => 'exito', 'instancia' => 'sentenca', 'tema' => 'consignado']),
        ...array_fill(0, 3, ['resultado' => 'insucesso', 'instancia' => 'recurso_cliente', 'tema' => 'tarifas']),
        ['resultado' => 'exito', 'instancia' => 'outra', 'tema' => 'penal'],
    ]);

    $d = app(MetricasRaioX::class)->calcular($r)['destaques'];

    expect($d['melhor'])->toContain('Sentença (1º grau): 100% de êxito (3 de 3).')
        ->and($d['pior'])->toContain('Recurso do cliente: 0% de êxito (0 de 3).')
        ->and(implode(' ', [...$d['melhor'], ...$d['pior']]))->not->toContain('Penal');
});
