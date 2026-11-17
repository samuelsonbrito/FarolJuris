<?php

namespace App\Support;

/**
 * Número único do processo (Resolução CNJ 65/2008): NNNNNNN-DD.AAAA.J.TR.OOOO.
 * J = segmento da Justiça; TR = tribunal dentro do segmento.
 */
final class NumeroCnj
{
    /** TR dos tribunais de justiça estaduais (J = 8), na ordem da tabela do CNJ. */
    private const TJS = [
        '01' => 'TJAC', '02' => 'TJAL', '03' => 'TJAP', '04' => 'TJAM', '05' => 'TJBA',
        '06' => 'TJCE', '07' => 'TJDFT', '08' => 'TJES', '09' => 'TJGO', '10' => 'TJMA',
        '11' => 'TJMT', '12' => 'TJMS', '13' => 'TJMG', '14' => 'TJPA', '15' => 'TJPB',
        '16' => 'TJPR', '17' => 'TJPE', '18' => 'TJPI', '19' => 'TJRJ', '20' => 'TJRN',
        '21' => 'TJRS', '22' => 'TJRO', '23' => 'TJRR', '24' => 'TJSC', '25' => 'TJSE',
        '26' => 'TJSP', '27' => 'TJTO',
    ];

    /** Só os dígitos, se forem 20; senão nulo. Aceita a máscara. */
    public static function normalizar(string $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', $valor) ?? '';

        return strlen($digitos) === 20 ? $digitos : null;
    }

    public static function mascara(string $digitos): string
    {
        return sprintf(
            '%s-%s.%s.%s.%s.%s',
            substr($digitos, 0, 7), substr($digitos, 7, 2), substr($digitos, 9, 4),
            substr($digitos, 13, 1), substr($digitos, 14, 2), substr($digitos, 16, 4),
        );
    }

    /**
     * Sigla do tribunal (formato de `siglaTribunal`, como em config/tribunais.php), deduzida
     * de J e TR. Nula para segmentos que não mapeamos (superiores, militar, eleitoral).
     */
    public static function tribunal(string $digitos): ?string
    {
        $j = substr($digitos, 13, 1);
        $tr = substr($digitos, 14, 2);

        $sigla = match ($j) {
            '8' => self::TJS[$tr] ?? null,
            '4' => 'TRF'.(int) $tr,
            '5' => 'TRT'.(int) $tr,
            default => null,
        };

        return $sigla !== null && array_key_exists($sigla, (array) config('tribunais')) ? $sigla : null;
    }
}
