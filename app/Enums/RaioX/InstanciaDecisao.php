<?php

namespace App\Enums\RaioX;

/** Em que momento a decisão foi tomada: sentença ou recurso (de quem recorreu). */
enum InstanciaDecisao: string
{
    case Sentenca = 'sentenca';
    case RecursoCliente = 'recurso_cliente';
    case RecursoContraria = 'recurso_contraria';
    case Outra = 'outra';

    public function rotulo(): string
    {
        return match ($this) {
            self::Sentenca => 'Sentença (1º grau)',
            self::RecursoCliente => 'Recurso do cliente',
            self::RecursoContraria => 'Recurso da parte contrária',
            self::Outra => 'Outras decisões',
        };
    }
}
