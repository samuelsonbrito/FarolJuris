<?php

/*
| Tribunais para o filtro da Consulta, na ordem das categorias (App\Support\CategoriaTribunal).
| As siglas seguem o formato que a Comunica API usa em siglaTribunal, confirmado em respostas
| reais: TJMG, TRT3, TRF6, TJDFT, TJMMG, STJ, TST, STM, CJF, SEEU; TSE e CNJ (busca por
| siglaTribunal em produção, 19/11/2026); TRE-BA, TRE-PA, TRE-PE e TRE-PR (com hífen, vistos em
| resultados de 19/11/2026; os demais TREs seguem o mesmo formato). O STF não publica no DJEN.
*/

$estados = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AM' => 'Amazonas', 'AP' => 'Amapá', 'BA' => 'Bahia',
    'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
    'GO' => 'Goiás', 'MA' => 'Maranhão', 'MG' => 'Minas Gerais', 'MS' => 'Mato Grosso do Sul',
    'MT' => 'Mato Grosso', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PE' => 'Pernambuco', 'PI' => 'Piauí',
    'PR' => 'Paraná', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RO' => 'Rondônia',
    'RR' => 'Roraima', 'RS' => 'Rio Grande do Sul', 'SC' => 'Santa Catarina', 'SE' => 'Sergipe',
    'SP' => 'São Paulo', 'TO' => 'Tocantins',
];

$lista = [
    // Tribunais superiores
    'STJ' => 'Superior Tribunal de Justiça',
    'TST' => 'Tribunal Superior do Trabalho',
    'TSE' => 'Tribunal Superior Eleitoral',
    'STM' => 'Superior Tribunal Militar',
];

// Justiça Estadual (o do DF é TJDFT)
foreach ($estados as $uf => $nome) {
    $lista[$uf === 'DF' ? 'TJDFT' : "TJ{$uf}"] = $uf === 'DF'
        ? 'Tribunal de Justiça - Distrito Federal e Territórios'
        : "Tribunal de Justiça - {$nome}";
}

// Justiça Federal
foreach (range(1, 6) as $n) {
    $lista["TRF{$n}"] = "Tribunal Regional Federal da {$n}ª Região";
}

// Justiça do Trabalho
foreach (range(1, 24) as $n) {
    $lista["TRT{$n}"] = "Tribunal Regional do Trabalho da {$n}ª Região";
}

// Justiça Eleitoral
foreach ($estados as $uf => $nome) {
    $lista["TRE-{$uf}"] = "Tribunal Regional Eleitoral - {$nome}";
}

// Justiça Militar Estadual
$lista['TJMMG'] = 'Tribunal de Justiça Militar - Minas Gerais';
$lista['TJMRS'] = 'Tribunal de Justiça Militar - Rio Grande do Sul';
$lista['TJMSP'] = 'Tribunal de Justiça Militar - São Paulo';

// Conselhos e sistemas
$lista['CNJ'] = 'Conselho Nacional de Justiça';
$lista['CJF'] = 'Conselho da Justiça Federal';
$lista['SEEU'] = 'Sistema Eletrônico de Execução Unificado';

return $lista;
