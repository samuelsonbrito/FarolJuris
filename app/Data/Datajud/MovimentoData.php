<?php

namespace App\Data\Datajud;

use Carbon\CarbonImmutable;

/** Um andamento (movimento) do processo no DataJud. */
final readonly class MovimentoData
{
    /**
     * @param  list<string>  $complementos  Nomes dos `complementosTabelados` (ex.: "sorteio", "para decisão").
     */
    public function __construct(
        public ?CarbonImmutable $dataHora,
        public ?int $codigo,
        public string $nome,
        public array $complementos,
        public ?string $orgaoJulgador,
    ) {}
}
