<?php

namespace App\Data\RaioX;

final readonly class RespostaModelo
{
    /**
     * @param  array<string, mixed>|null  $dados  JSON decodificado (nulo se recusou ou veio inválido).
     * @param  int  $tokensEntrada  Entrada cobrada a preço cheio (fora do cache).
     */
    public function __construct(
        public ?array $dados,
        public ?string $motivoParada,
        public int $tokensEntrada = 0,
        public int $tokensSaida = 0,
        public int $tokensCacheLeitura = 0,
        public int $tokensCacheEscrita = 0,
        public ?int $duracaoMs = null,
    ) {}
}
