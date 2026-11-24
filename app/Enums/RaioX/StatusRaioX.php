<?php

namespace App\Enums\RaioX;

/** Ciclo do Raio-X: o usuário solicita, o admin gera, confere os ambíguos e libera. */
enum StatusRaioX: string
{
    case Solicitado = 'solicitado';
    case Processando = 'processando';
    case Conferencia = 'conferencia';
    case Pronto = 'pronto';
    case Liberado = 'liberado';
    case Falhou = 'falhou';

    public function rotulo(): string
    {
        return match ($this) {
            self::Solicitado => 'Solicitado',
            self::Processando => 'Em processamento',
            self::Conferencia => 'Em conferência',
            self::Pronto => 'Pronto para liberar',
            self::Liberado => 'Disponível',
            self::Falhou => 'Falhou',
        };
    }
}
