<?php

namespace App\Support;

/** Categoria de um tribunal (pela sigla), para agrupar o filtro da Consulta. */
final class CategoriaTribunal
{
    /** Na ordem em que aparecem no filtro. */
    public const ROTULOS = [
        'superiores' => 'Tribunais superiores',
        'estadual' => 'Justiça Estadual',
        'federal' => 'Justiça Federal',
        'trabalho' => 'Justiça do Trabalho',
        'eleitoral' => 'Justiça Eleitoral',
        'militar' => 'Justiça Militar Estadual',
        'outros' => 'Conselhos e sistemas',
    ];

    public static function de(string $sigla): string
    {
        $sigla = strtoupper($sigla);

        return match (true) {
            in_array($sigla, ['STJ', 'TST', 'TSE', 'STM'], true) => 'superiores',
            str_starts_with($sigla, 'TRE-') => 'eleitoral',
            str_starts_with($sigla, 'TRF') => 'federal',
            str_starts_with($sigla, 'TRT') => 'trabalho',
            // TJMMG, TJMRS, TJMSP (cinco letras; TJMA, TJMG, TJMS e TJMT são estaduais).
            (bool) preg_match('/^TJM[A-Z]{2}$/', $sigla) => 'militar',
            str_starts_with($sigla, 'TJ') => 'estadual',
            default => 'outros',
        };
    }

    public static function rotulo(string $sigla): string
    {
        return self::ROTULOS[self::de($sigla)];
    }
}
