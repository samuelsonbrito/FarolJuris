<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Pública do DataJud (CNJ)
    |--------------------------------------------------------------------------
    |
    | Metadados e andamentos (movimentos) dos processos, por tribunal. Toda a
    | comunicação passa por App\Services\Datajud\DatajudClient. Não traz partes,
    | advogados nem processos em segredo de justiça, e não é em tempo real (o CNJ
    | atualiza o índice em lotes). Termos de uso: https://www.cnj.jus.br/sistemas/datajud/api-publica/
    |
    */

    'ativo' => (bool) env('DATAJUD_ATIVO', true),

    'base_url' => rtrim((string) env('DATAJUD_BASE_URL', 'https://api-publica.datajud.cnj.jus.br'), '/'),

    // Chave pública divulgada pelo CNJ em https://datajud-wiki.cnj.jus.br/api-publica/acesso
    // (o CNJ pode trocá-la: atualize DATAJUD_API_KEY).
    'api_key' => env('DATAJUD_API_KEY', 'cDZHYzlZa0JadVREZDJCendQbXY6SkJlTzNjLV9TRENyQk1RdnFKZGRQdw=='),

    'user_agent' => env('DJEN_USER_AGENT', 'FarolJuris/1.0'),

    // A API pode levar dezenas de segundos para responder.
    'timeout' => (int) env('DATAJUD_TIMEOUT', 55),
    'connect_timeout' => (int) env('DATAJUD_CONNECT_TIMEOUT', 10),

    // Segundos que os andamentos de um processo ficam em cache.
    'cache_ttl' => (int) env('DATAJUD_CACHE_TTL', 21600),

    // Consultas de andamentos por minuto, por IP.
    'rate_limit_por_minuto' => (int) env('DATAJUD_RATE_LIMIT_POR_MINUTO', 10),

    // Tribunais habilitados (sigla => índice do DataJud). Começamos pelo TJAM.
    'tribunais' => [
        'TJAM' => 'api_publica_tjam',
    ],

];
