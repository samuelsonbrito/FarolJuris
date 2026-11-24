<?php

namespace App\Data\RaioX;

/** Consumo de uma chamada à IA (para o custo). */
final readonly class UsoIA
{
    public function __construct(
        public string $modelo,
        /** Tokens de entrada cobrados a preço cheio (fora do cache). */
        public int $entrada = 0,
        public int $cacheLeitura = 0,
        public int $cacheEscrita = 0,
        public int $saida = 0,
        public ?int $duracaoMs = null,
        /** ok | recusa | invalido | erro */
        public string $resultado = 'ok',
    ) {}

    public function totalEntrada(): int
    {
        return $this->entrada + $this->cacheLeitura + $this->cacheEscrita;
    }

    public function comResultado(string $resultado): self
    {
        return new self($this->modelo, $this->entrada, $this->cacheLeitura, $this->cacheEscrita, $this->saida, $this->duracaoMs, $resultado);
    }
}
