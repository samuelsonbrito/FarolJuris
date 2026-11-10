<?php

namespace App\Data\Djen;

use Carbon\CarbonImmutable;

/**
 * Filtros da aba Consulta, já validados e normalizados.
 *
 * Os nomes são do app (e da query string da nossa URL). A conversão para os
 * parâmetros da Comunica API fica no gateway.
 */
final readonly class FiltroConsulta
{
    public function __construct(
        public ?string $numeroOab = null,
        public ?string $ufOab = null,
        public ?string $nomeParte = null,
        public ?string $nomeAdvogado = null,
        public ?string $numeroProcesso = null,
        public ?string $tribunal = null,
        public ?CarbonImmutable $dataInicio = null,
        public ?CarbonImmutable $dataFim = null,
        public int $pagina = 1,
        public int $porPagina = 20,
        /** Palavra-chave no teor (parâmetro `texto` da API). */
        public ?string $texto = null,
        /** `D` (Diário) ou `E` (Plataforma Nacional de Editais). */
        public ?string $meio = null,
        public ?string $numeroComunicacao = null,
    ) {}

    /** Há pelo menos um critério de busca (datas e página não contam). */
    public function temCriterio(): bool
    {
        return $this->numeroOab !== null
            || $this->nomeParte !== null
            || $this->nomeAdvogado !== null
            || $this->numeroProcesso !== null
            || $this->tribunal !== null
            || $this->texto !== null;
    }

    /**
     * Reconstrói a partir de toQuery() (ex.: parâmetros salvos de uma exportação).
     *
     * @param  array<string, mixed>  $q
     */
    public static function fromQuery(array $q, int $pagina = 1, int $porPagina = 20): self
    {
        $texto = fn (string $k): ?string => isset($q[$k]) && $q[$k] !== '' ? (string) $q[$k] : null;
        $data = fn (string $k): ?CarbonImmutable => $texto($k) !== null ? CarbonImmutable::createFromFormat('!Y-m-d', $texto($k)) : null;

        return new self(
            numeroOab: $texto('oab'),
            ufOab: $texto('uf'),
            nomeParte: $texto('parte'),
            nomeAdvogado: $texto('advogado'),
            numeroProcesso: $texto('processo'),
            tribunal: $texto('tribunal'),
            dataInicio: $data('inicio'),
            dataFim: $data('fim'),
            pagina: $pagina,
            porPagina: $porPagina,
            texto: $texto('texto'),
            meio: $texto('meio'),
            numeroComunicacao: $texto('comunicacao'),
        );
    }

    /**
     * Representação para a query string da nossa URL.
     *
     * @return array<string, string|int|null>
     */
    public function toQuery(): array
    {
        return [
            'oab' => $this->numeroOab,
            'uf' => $this->ufOab,
            'parte' => $this->nomeParte,
            'advogado' => $this->nomeAdvogado,
            'processo' => $this->numeroProcesso,
            'tribunal' => $this->tribunal,
            'inicio' => $this->dataInicio?->toDateString(),
            'fim' => $this->dataFim?->toDateString(),
            'texto' => $this->texto,
            'meio' => $this->meio,
            'comunicacao' => $this->numeroComunicacao,
            'pagina' => $this->pagina,
        ];
    }
}
