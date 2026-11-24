<?php

namespace App\Services\RaioX;

use App\Models\RaioX\PublicacaoRaioX;

/**
 * Pré-filtro barato: só os processos com alguma publicação "com cara de decisão" vão para a IA.
 * Os demais entram no Raio-X como ativos sem decisão no período (sem custo de IA).
 */
final class DetectorDecisao
{
    private const PADRAO = '/julg(o|a|ou|ar|ado|ada|amos)\s+(totalmente\s+|parcialmente\s+)?(im)?procedente|procedentes?\s+(em\s+parte|os\s+pedidos|o\s+pedido)'
        .'|(dar|dou|deu|dão|deram|negar|nego|negou|negaram|dando|negando)\s+(parcial\s+)?provimento|provimento\s+(ao|do)\s+recurso'
        .'|n[ãa]o\s+conhe(ço|cer|ceu|cido)|homolog\w*\s+(o\s+|a\s+|por\s+senten[çc]a\s+)?(acordo|transa[çc][ãa]o)'
        .'|extin(to|ta|go|ção|guindo|guir)\b|sem\s+resolu[çc][ãa]o\s+(do\s+)?m[ée]rito|resolu[çc][ãa]o\s+do\s+m[ée]rito'
        .'|ac[óo]rd[ãa]o|ementa\s*:|senten[çc]a\s*(:|\.|$)|dispositivo\s*:|resultado\s+do\s+julgamento|por\s+unanimidade|por\s+maioria/iu';

    private const TIPOS = ['sentença', 'sentenca', 'acórdão', 'acordao', 'acórdao', 'ementa'];

    public static function pareceDecisao(PublicacaoRaioX $p): bool
    {
        $tipo = mb_strtolower(trim((string) $p->tipo_documento));

        foreach (self::TIPOS as $t) {
            if ($tipo !== '' && str_contains($tipo, $t)) {
                return true;
            }
        }

        return (bool) preg_match(self::PADRAO, $p->texto);
    }
}
