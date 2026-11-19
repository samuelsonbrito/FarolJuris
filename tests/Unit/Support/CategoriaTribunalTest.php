<?php

use App\Support\CategoriaTribunal;

it('classifica cada sigla na sua categoria', function (string $sigla, string $rotulo) {
    expect(CategoriaTribunal::rotulo($sigla))->toBe($rotulo);
})->with([
    ['STJ', 'Tribunais superiores'],
    ['TSE', 'Tribunais superiores'],
    ['STM', 'Tribunais superiores'],
    ['TJAM', 'Justiça Estadual'],
    ['TJMA', 'Justiça Estadual'],
    ['TJMG', 'Justiça Estadual'],
    ['TJMS', 'Justiça Estadual'],
    ['TJMT', 'Justiça Estadual'],
    ['TJDFT', 'Justiça Estadual'],
    ['TRF6', 'Justiça Federal'],
    ['TRT11', 'Justiça do Trabalho'],
    ['TRE-AM', 'Justiça Eleitoral'],
    ['TJMMG', 'Justiça Militar Estadual'],
    ['TJMSP', 'Justiça Militar Estadual'],
    ['CNJ', 'Conselhos e sistemas'],
    ['CJF', 'Conselhos e sistemas'],
    ['SEEU', 'Conselhos e sistemas'],
]);

it('lista todos os tribunais em ordem de categoria', function () {
    $tribunais = config('tribunais');
    $ordem = array_keys(CategoriaTribunal::ROTULOS);
    $indices = array_map(fn (string $s) => array_search(CategoriaTribunal::de($s), $ordem, true), array_keys($tribunais));

    $ordenados = $indices;
    sort($ordenados);

    expect($indices)->toBe($ordenados)
        ->and($tribunais)->toHaveCount(4 + 27 + 6 + 24 + 27 + 3 + 3)
        ->and($tribunais)->toHaveKeys(['TSE', 'CNJ', 'TRE-AM', 'TRE-DF', 'TJDFT', 'TRT24', 'TRF6'])
        ->and($tribunais)->not->toHaveKey('STF');
});
