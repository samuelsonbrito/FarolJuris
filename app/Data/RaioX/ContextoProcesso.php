<?php

namespace App\Data\RaioX;

use App\Models\RaioX\PublicacaoRaioX;

/** O que a classificação recebe sobre um processo: o advogado e as publicações mais relevantes. */
final readonly class ContextoProcesso
{
    /**
     * @param  list<PublicacaoRaioX>  $publicacoes  Da mais recente para a mais antiga.
     */
    public function __construct(
        public string $numeroProcesso,
        public ?string $tribunal,
        public string $oab,
        public string $uf,
        public ?string $nomeAdvogado,
        /** Capital da UF da OAB (para separar capital e interior). */
        public ?string $capital,
        public array $publicacoes,
    ) {}
}
