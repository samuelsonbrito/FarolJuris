<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Raio-X de desempenho processual
    |--------------------------------------------------------------------------
    |
    | Coleta as publicações do DJEN de uma OAB, classifica cada decisão com IA
    | (Claude, API da Anthropic) do ponto de vista do cliente e consolida as
    | métricas. Casos ambíguos vão para conferência do admin antes da liberação.
    |
    */

    // Avisos por e-mail (pedido para os admins, liberação para o dono). Desligados até o envio de
    // e-mails do servidor estar configurado; quando ligados, saem pela fila.
    'avisos_email' => (bool) env('RAIOX_AVISOS_EMAIL', false),

    // Período analisado (meses para trás, a partir de hoje).
    'meses' => (int) env('RAIOX_MESES', 12),

    // Coleta no DJEN: itens por página e teto de páginas (a API limita count a 10000).
    'coleta' => [
        'itens_por_pagina' => (int) env('RAIOX_ITENS_POR_PAGINA', 100),
        'max_paginas' => (int) env('RAIOX_MAX_PAGINAS', 60),
        // Espera entre páginas, para respeitar o limite da Comunica API (20/min).
        'pausa_ms' => (int) env('RAIOX_PAUSA_MS', 3100),
    ],

    'ia' => [
        // "claude" (API da Anthropic) ou "fake" (regras simples, para desenvolver/testar sem custo).
        'driver' => env('RAIOX_IA_DRIVER', 'claude'),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'modelo' => env('RAIOX_IA_MODELO', 'claude-opus-5-5'),
        // Classificação é tarefa curta: esforço baixo controla custo e tempo.
        'esforco' => env('RAIOX_IA_ESFORCO', 'low'),
        'max_tokens' => (int) env('RAIOX_IA_MAX_TOKENS', 4000),
        // Publicações enviadas por processo (as mais recentes) e tamanho máximo de cada texto.
        'publicacoes_por_processo' => (int) env('RAIOX_IA_PUBLICACOES', 6),
        'max_caracteres_publicacao' => (int) env('RAIOX_IA_MAX_CARACTERES', 6000),
    ],

    // Custo da IA (painel Administração → Custos de IA). Preços em dólar por milhão de tokens,
    // da tabela da Anthropic (confira em https://www.anthropic.com/pricing ao trocar de modelo).
    // Escrita em cache = 1,25 × entrada (cache de 5 minutos).
    'custos' => [
        // Cotação usada para converter em reais (gravada em cada chamada). Atualize no .env.
        'cotacao_usd_brl' => (float) env('RAIOX_COTACAO_USD_BRL', 5.50),
        'precos' => [
            'claude-opus-5-5' => ['entrada' => 4.00, 'saida' => 20.00, 'cache_leitura' => 0.20, 'cache_escrita' => 5.00],
            'claude-sonnet-5-5' => ['entrada' => 2.00, 'saida' => 10.00, 'cache_leitura' => 0.10, 'cache_escrita' => 2.50],
            'claude-haiku-5-5' => ['entrada' => 0.10, 'saida' => 0.50, 'cache_leitura' => 0.01, 'cache_escrita' => 0.125],
        ],
    ],

    // Abaixo deste número de decididos, um grupo não entra em "onde vai melhor/pior".
    'minimo_para_destaque' => (int) env('RAIOX_MINIMO_DESTAQUE', 5),

];
