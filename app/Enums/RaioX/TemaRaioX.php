<?php

namespace App\Enums\RaioX;

/** Temas do Raio-X (os mesmos do modelo de relatório). A IA escolhe um por processo. */
enum TemaRaioX: string
{
    case BancarioNaoInformado = 'bancario_nao_informado';
    case DescontosEmConta = 'descontos_em_conta';
    case Consignado = 'consignado';
    case CivelContratos = 'civel_contratos';
    case SeguroCapitalizacao = 'seguro_capitalizacao';
    case CartaoConsignado = 'cartao_consignado';
    case Tarifas = 'tarifas';
    case ConsumoOutros = 'consumo_outros';
    case DescontosAssociacao = 'descontos_associacao';
    case FraudeGolpe = 'fraude_golpe';
    case EnergiaTelefonia = 'energia_telefonia';
    case TransporteTurismo = 'transporte_turismo';
    case Trabalhista = 'trabalhista';
    case Tributario = 'tributario';
    case Penal = 'penal';
    case Outros = 'outros';

    public function rotulo(): string
    {
        return match ($this) {
            self::BancarioNaoInformado => 'Bancário – tema não informado',
            self::DescontosEmConta => 'Descontos em conta (outros)',
            self::Consignado => 'Empréstimo consignado / refinanciamento',
            self::CivelContratos => 'Cível – contratos, imóveis e cobrança',
            self::SeguroCapitalizacao => 'Seguro / capitalização não contratados',
            self::CartaoConsignado => 'Cartão consignado (RMC/RCC)',
            self::Tarifas => 'Tarifas e cestas bancárias',
            self::ConsumoOutros => 'Consumo – outros (varejo, apps)',
            self::DescontosAssociacao => 'Descontos de associação',
            self::FraudeGolpe => 'Bloqueio de conta / fraude / golpe',
            self::EnergiaTelefonia => 'Energia e telefonia',
            self::TransporteTurismo => 'Transporte aéreo / turismo',
            self::Trabalhista => 'Trabalhista',
            self::Tributario => 'Tributário / execução fiscal',
            self::Penal => 'Penal',
            self::Outros => 'Outros',
        };
    }
}
