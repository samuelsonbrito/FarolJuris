<?php

namespace App\Services\Datajud;

use RuntimeException;
use Throwable;

/** Falha ao consultar o DataJud (rede, timeout, sobrecarga ou resposta inesperada). */
class DatajudException extends RuntimeException
{
    public function __construct(
        string $message,
        /** O DataJud respondeu 429: está sobrecarregado (visto em 17/11/2026). */
        public readonly bool $sobrecarga = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
