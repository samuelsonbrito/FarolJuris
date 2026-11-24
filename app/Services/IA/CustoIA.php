<?php

namespace App\Services\IA;

use App\Data\RaioX\UsoIA;
use App\Models\IA\ChamadaIA;

/** Converte tokens em dólar (tabela de preços em config/raiox.php) e em real (cotação configurada). */
final class CustoIA
{
    /** @return array{usd: float|null, brl: float|null, cotacao: float} */
    public function calcular(UsoIA $uso): array
    {
        $cotacao = (float) config('raiox.custos.cotacao_usd_brl', 5.5);
        $preco = config("raiox.custos.precos.{$uso->modelo}");

        if (! is_array($preco)) {
            // Modelo sem preço configurado: registra os tokens, sem valor.
            return ['usd' => null, 'brl' => null, 'cotacao' => $cotacao];
        }

        $usd = ($uso->entrada * $preco['entrada']
            + $uso->cacheLeitura * $preco['cache_leitura']
            + $uso->cacheEscrita * $preco['cache_escrita']
            + $uso->saida * $preco['saida']) / 1_000_000;

        return ['usd' => round($usd, 6), 'brl' => round($usd * $cotacao, 6), 'cotacao' => $cotacao];
    }

    public function registrar(UsoIA $uso, ?int $relatorioId, string $finalidade = 'raiox_classificacao'): ChamadaIA
    {
        $custo = $this->calcular($uso);

        return ChamadaIA::query()->create([
            'relatorio_id' => $relatorioId,
            'finalidade' => $finalidade,
            'modelo' => $uso->modelo,
            'tokens_entrada' => $uso->entrada,
            'tokens_cache_leitura' => $uso->cacheLeitura,
            'tokens_cache_escrita' => $uso->cacheEscrita,
            'tokens_saida' => $uso->saida,
            'custo_usd' => $custo['usd'],
            'cotacao_usd_brl' => $custo['cotacao'],
            'custo_brl' => $custo['brl'],
            'resultado' => $uso->resultado,
            'duracao_ms' => $uso->duracaoMs,
        ]);
    }
}
