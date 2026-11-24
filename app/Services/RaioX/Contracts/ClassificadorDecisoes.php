<?php

namespace App\Services\RaioX\Contracts;

use App\Data\RaioX\ClassificacaoData;
use App\Data\RaioX\ContextoProcesso;

/** Lê as publicações de um processo e classifica a decisão do período (driver em config('raiox.ia.driver')). */
interface ClassificadorDecisoes
{
    public function classificar(ContextoProcesso $contexto): ClassificacaoData;

    /** Nome do modelo usado (registrado no relatório). */
    public function modelo(): string;
}
