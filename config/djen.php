<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Comunica API (DJEN / CNJ)
    |--------------------------------------------------------------------------
    |
    | Toda a comunicação com a API passa por App\Services\Djen\DjenClient.
    | A consulta pública (GET /comunicacao) não exige autenticação, mas a
    | API bloqueia requisições de IPs fora do Brasil.
    |
    */

    // Fonte das comunicações: "api" (Comunica API) ou "fake" (dados de exemplo).
    'gateway' => env('DJEN_GATEWAY', 'api'),

    'base_url' => rtrim((string) env('DJEN_BASE_URL', 'https://comunicaapi.pje.jus.br/api/v1'), '/'),

    'user_agent' => env('DJEN_USER_AGENT', 'FarolJuris/1.0'),

    // Segundos.
    'timeout' => (int) env('DJEN_TIMEOUT', 20),
    'connect_timeout' => (int) env('DJEN_CONNECT_TIMEOUT', 5),

    // Tentativas extras em erros de conexão, 429 e 5xx, com backoff exponencial.
    'retry' => [
        'times' => (int) env('DJEN_RETRY_TIMES', 3),
        'sleep_ms' => (int) env('DJEN_RETRY_SLEEP_MS', 500),
        // Teto de espera entre tentativas (inclusive quando a API manda Retry-After).
        'max_sleep_ms' => (int) env('DJEN_RETRY_MAX_SLEEP_MS', 10000),
    ],

    'itens_por_pagina' => (int) env('DJEN_ITENS_POR_PAGINA', 20),

    // A API ignora o tipo de comunicação (visto em produção, 10/11/2026): o filtro por tipo
    // é feito aqui, sobre até `max_paginas` páginas de `itens_por_pagina` comunicações.
    'filtro_tipo' => [
        'itens_por_pagina' => (int) env('DJEN_FILTRO_TIPO_ITENS_POR_PAGINA', 100),
        'max_paginas' => (int) env('DJEN_FILTRO_TIPO_MAX_PAGINAS', 10),
    ],

    // Segundos que uma consulta fica em cache (chave baseada nos filtros).
    'cache_ttl' => (int) env('DJEN_CACHE_TTL', 600),

    'rate_limit' => [
        // Chamadas ao CNJ por minuto feitas pelos Jobs (middleware RateLimited).
        'per_minute' => (int) env('DJEN_RATE_LIMIT_PER_MINUTE', 15),
        // Consultas públicas por minuto, por IP.
        'public_per_minute' => (int) env('DJEN_PUBLIC_RATE_LIMIT_PER_MINUTE', 30),
    ],

    'monitoring' => [
        // Horário diário (HH:MM) em que o Job de monitoramento roda.
        'time' => env('DJEN_MONITORING_TIME', '07:00'),
        'timezone' => env('DJEN_MONITORING_TIMEZONE', 'America/Manaus'),
        // Quantos dias para trás cada execução consulta.
        'lookback_days' => (int) env('DJEN_MONITORING_LOOKBACK_DAYS', 3),
        // Paginação usada pelo Job (por alvo).
        'itens_por_pagina' => (int) env('DJEN_MONITORING_ITENS_POR_PAGINA', 100),
        'max_paginas' => (int) env('DJEN_MONITORING_MAX_PAGINAS', 5),
        // Máximo de alvos por usuário.
        'max_alvos' => (int) env('DJEN_MONITORING_MAX_ALVOS', 50),
    ],

    'exportacao' => [
        // Disco privado onde os arquivos ficam até expirarem.
        'disk' => env('DJEN_EXPORT_DISK', 'local'),
        // Até este número de itens selecionados, o arquivo é gerado na hora.
        'limite_sincrono' => (int) env('DJEN_EXPORT_LIMITE_SINCRONO', 100),
        // Máximo de comunicações por exportação.
        'max_itens' => (int) env('DJEN_EXPORT_MAX_ITENS', 2000),
        // LGPD: arquivos e registros são apagados depois de N dias.
        'dias_retencao' => (int) env('DJEN_EXPORT_DIAS_RETENCAO', 7),
    ],

    'rastreio' => [
        // Registra buscas, chamadas ao CNJ e atividade para a área administrativa.
        'ativo' => (bool) env('DJEN_RASTREIO_ATIVO', true),
        // LGPD: registros mais antigos que isso são apagados pelo model:prune diário.
        'dias_retencao' => (int) env('DJEN_RASTREIO_DIAS_RETENCAO', 180),
    ],

    'whatsapp' => [
        // Drivers disponíveis: log. (Evolution API, Z-API e Meta Cloud API: a implementar.)
        'driver' => env('DJEN_WHATSAPP_DRIVER', 'log'),
    ],

];
