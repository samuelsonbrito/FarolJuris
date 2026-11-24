<?php

namespace App\Enums\RaioX;

/** Onde o processo nasceu (vara de origem), para a tabela "por vara de origem". */
enum GrupoOrigem: string
{
    case CivelCapital = 'civel_capital';
    case JuizadoCapital = 'juizado_capital';
    case Interior = 'interior';
    case OutroEstado = 'outro_estado';
    case TrabalhoFederalFiscal = 'trabalho_federal_fiscal';
    case NaoIdentificada = 'nao_identificada';

    /** @param  string|null  $capital  Nome da capital da UF da OAB (ex.: Manaus). */
    public function rotulo(?string $capital = null, ?string $estado = null): string
    {
        $capital ??= 'da capital';

        return match ($this) {
            self::CivelCapital => "Varas cíveis de {$capital}",
            self::JuizadoCapital => "Juizados Especiais Cíveis de {$capital}",
            self::Interior => $estado ? "Interior {$estado}" : 'Interior do estado',
            self::OutroEstado => 'Outros estados',
            self::TrabalhoFederalFiscal => 'Trabalho, Federal e Dívida Ativa',
            self::NaoIdentificada => 'Vara não identificada',
        };
    }
}
