<?php

namespace App\Data\RaioX;

final readonly class RespostaModelo
{
    /**
     * @param  array<string, mixed>|null  $dados  JSON decodificado (nulo se recusou ou veio inválido).
     */
    public function __construct(
        public ?array $dados,
        public ?string $motivoParada,
        public int $tokensEntrada = 0,
        public int $tokensSaida = 0,
    ) {}
}
