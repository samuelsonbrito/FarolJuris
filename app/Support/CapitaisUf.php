<?php

namespace App\Support;

/** Capital e nome (com preposição) de cada UF, para os rótulos "Varas cíveis de Manaus", "Interior do Amazonas". */
final class CapitaisUf
{
    public const CAPITAIS = [
        'AC' => ['Rio Branco', 'do Acre'], 'AL' => ['Maceió', 'de Alagoas'], 'AM' => ['Manaus', 'do Amazonas'],
        'AP' => ['Macapá', 'do Amapá'], 'BA' => ['Salvador', 'da Bahia'], 'CE' => ['Fortaleza', 'do Ceará'],
        'DF' => ['Brasília', 'do Distrito Federal'], 'ES' => ['Vitória', 'do Espírito Santo'], 'GO' => ['Goiânia', 'de Goiás'],
        'MA' => ['São Luís', 'do Maranhão'], 'MG' => ['Belo Horizonte', 'de Minas Gerais'], 'MS' => ['Campo Grande', 'de Mato Grosso do Sul'],
        'MT' => ['Cuiabá', 'de Mato Grosso'], 'PA' => ['Belém', 'do Pará'], 'PB' => ['João Pessoa', 'da Paraíba'],
        'PE' => ['Recife', 'de Pernambuco'], 'PI' => ['Teresina', 'do Piauí'], 'PR' => ['Curitiba', 'do Paraná'],
        'RJ' => ['Rio de Janeiro', 'do Rio de Janeiro'], 'RN' => ['Natal', 'do Rio Grande do Norte'], 'RO' => ['Porto Velho', 'de Rondônia'],
        'RR' => ['Boa Vista', 'de Roraima'], 'RS' => ['Porto Alegre', 'do Rio Grande do Sul'], 'SC' => ['Florianópolis', 'de Santa Catarina'],
        'SE' => ['Aracaju', 'de Sergipe'], 'SP' => ['São Paulo', 'de São Paulo'], 'TO' => ['Palmas', 'do Tocantins'],
    ];

    public static function capital(string $uf): ?string
    {
        return self::CAPITAIS[strtoupper($uf)][0] ?? null;
    }

    /** "do Amazonas", "de São Paulo". */
    public static function estado(string $uf): ?string
    {
        return self::CAPITAIS[strtoupper($uf)][1] ?? null;
    }
}
