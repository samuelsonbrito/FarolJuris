<?php

use App\Support\NumeroCnj;

it('normaliza o número com ou sem máscara', function () {
    expect(NumeroCnj::normalizar('0293096-02.2026.8.04.1000'))->toBe('02930960220268041000')
        ->and(NumeroCnj::normalizar('02930960220268041000'))->toBe('02930960220268041000')
        ->and(NumeroCnj::normalizar('123'))->toBeNull();
});

it('aplica a máscara CNJ', function () {
    expect(NumeroCnj::mascara('02930960220268041000'))->toBe('0293096-02.2026.8.04.1000');
});

it('deduz o tribunal de J e TR', function (string $numero, ?string $sigla) {
    expect(NumeroCnj::tribunal($numero))->toBe($sigla);
})->with([
    'TJAM' => ['02930960220268041000', 'TJAM'],
    'TJDFT' => ['00000000020268070000', 'TJDFT'],
    'TJSP' => ['00000000020268260000', 'TJSP'],
    'TRF1' => ['00000000020264010000', 'TRF1'],
    'TRT11' => ['00000000020265110000', 'TRT11'],
    'STJ (J = 3) não mapeado' => ['00000000020263000000', null],
    'TR estadual inexistente' => ['00000000020268990000', null],
]);
