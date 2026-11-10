<?php

namespace App\Data\Djen;

/** Destinatário (parte) de uma comunicação (contrato do app, não da API). */
final readonly class DestinatarioData
{
    /**
     * @param  string|null  $polo  Polo na API: A (ativo), P (passivo) e outros códigos.
     */
    public function __construct(
        public string $nome,
        public ?string $polo = null,
    ) {}
}
