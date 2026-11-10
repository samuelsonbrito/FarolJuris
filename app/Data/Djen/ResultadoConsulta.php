<?php

namespace App\Data\Djen;

final readonly class ResultadoConsulta
{
    /**
     * @param  list<ComunicacaoData>  $comunicacoes
     */
    public function __construct(
        public array $comunicacoes,
        public int $total,
        public int $pagina,
        public int $porPagina,
        /** A resposta veio do cache do DjenClient (sem chamar o CNJ). */
        public bool $doCache = false,
        /**
         * Filtro por tipo feito no nosso servidor sobre só parte do resultado da API:
         * quantas comunicações foram analisadas e quantas a API tinha. Nulo = análise completa.
         *
         * @var array{analisadas: int, totalApi: int}|null
         */
        public ?array $filtroParcial = null,
    ) {}

    public function ultimaPagina(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->porPagina)));
    }
}
