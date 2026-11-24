<?php

namespace App\Enums\RaioX;

/** Resultado da decisão mais recente do período, lido do ponto de vista do cliente. */
enum ResultadoDecisao: string
{
    case Exito = 'exito';
    case ExitoParcial = 'exito_parcial';
    case Acordo = 'acordo';
    case Insucesso = 'insucesso';
    /** Publicações sem decisão de mérito, acordo ou extinção no período. */
    case SemDecisao = 'sem_decisao';

    public function rotulo(): string
    {
        return match ($this) {
            self::Exito => 'Êxito',
            self::ExitoParcial => 'Êxito parcial',
            self::Acordo => 'Acordo',
            self::Insucesso => 'Insucesso',
            self::SemDecisao => 'Sem decisão',
        };
    }

    public function decidido(): bool
    {
        return $this !== self::SemDecisao;
    }

    public function favoravel(): bool
    {
        return $this === self::Exito || $this === self::ExitoParcial;
    }
}
