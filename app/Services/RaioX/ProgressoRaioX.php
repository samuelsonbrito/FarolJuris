<?php

namespace App\Services\RaioX;

use App\Enums\RaioX\StatusRaioX;
use App\Models\RaioX\RelatorioRaioX;

/**
 * Progresso de um Raio-X em processamento e tempos médios dos já gerados.
 *
 * A estimativa usa o ritmo medido nos relatórios anteriores (segundos por página coletada e por
 * processo classificado, já contando os workers em paralelo); sem histórico, valores iniciais.
 */
final class ProgressoRaioX
{
    /** Sem histórico: ~3,5 s por página (limite da Comunica API) e ~5 s por processo (2 workers). */
    private const SEGUNDOS_POR_PAGINA = 3.5;

    private const SEGUNDOS_POR_PROCESSO = 5.0;

    /** Fração das publicações que vira processo a classificar (para estimar durante a coleta). */
    private const CANDIDATOS_POR_PUBLICACAO = 0.06;

    /** @var array{porPagina: float, porProcesso: float, candidatos: float, amostras: int, mediaTotal: int|null, mediaColeta: int|null, mediaClassificacao: int|null}|null */
    private ?array $ritmo = null;

    /**
     * @return array{etapa: string, percentual: int, paginas: int, paginasTotal: int, classificados: int, classificarTotal: int, decorridoSegundos: int|null, restanteSegundos: int|null}|null
     */
    public function de(RelatorioRaioX $r): ?array
    {
        if ($r->status !== StatusRaioX::Processando || $r->iniciado_em === null) {
            return null;
        }

        $ritmo = $this->ritmo();
        $coletando = $r->coleta_concluida_em === null;

        $paginasTotal = max($r->paginas_total, $r->paginas_coletadas, 1);
        $classificarTotal = $coletando
            ? (int) round($paginasTotal * (int) config('raiox.coleta.itens_por_pagina', 100) * $ritmo['candidatos'])
            : $r->classificar_total;

        $trabalhoTotal = $paginasTotal * $ritmo['porPagina'] + $classificarTotal * $ritmo['porProcesso'];
        $feito = ($coletando ? $r->paginas_coletadas : $paginasTotal) * $ritmo['porPagina'] + $r->classificados * $ritmo['porProcesso'];
        $percentual = $trabalhoTotal > 0 ? (int) floor($feito / $trabalhoTotal * 100) : 0;

        return [
            'etapa' => $coletando ? 'coleta' : 'classificacao',
            // Nunca 100% enquanto não concluir; e mostra algum avanço desde o início.
            'percentual' => max(1, min(99, $percentual)),
            'paginas' => $r->paginas_coletadas,
            'paginasTotal' => $r->paginas_total,
            'classificados' => $r->classificados,
            'classificarTotal' => $coletando ? 0 : $r->classificar_total,
            'decorridoSegundos' => (int) $r->iniciado_em->diffInSeconds(now()),
            'restanteSegundos' => (int) max(0, round($trabalhoTotal - $feito)),
        ];
    }

    /**
     * Médias dos relatórios gerados (para o admin e para as estimativas).
     *
     * @return array{porPagina: float, porProcesso: float, candidatos: float, amostras: int, mediaTotal: int|null, mediaColeta: int|null, mediaClassificacao: int|null}
     */
    public function ritmo(): array
    {
        if ($this->ritmo !== null) {
            return $this->ritmo;
        }

        $gerados = RelatorioRaioX::query()
            ->whereNotNull('iniciado_em')->whereNotNull('coleta_concluida_em')->whereNotNull('gerado_em')
            ->where('paginas_coletadas', '>', 0)
            ->latest('gerado_em')->limit(20)
            ->get(['iniciado_em', 'coleta_concluida_em', 'gerado_em', 'paginas_coletadas', 'classificar_total', 'publicacoes_total']);

        if ($gerados->isEmpty()) {
            return $this->ritmo = [
                'porPagina' => self::SEGUNDOS_POR_PAGINA, 'porProcesso' => self::SEGUNDOS_POR_PROCESSO,
                'candidatos' => self::CANDIDATOS_POR_PUBLICACAO, 'amostras' => 0,
                'mediaTotal' => null, 'mediaColeta' => null, 'mediaClassificacao' => null,
            ];
        }

        $coleta = $gerados->map(fn ($r) => max(0, $r->iniciado_em->diffInSeconds($r->coleta_concluida_em)));
        $classificacao = $gerados->map(fn ($r) => max(0, $r->coleta_concluida_em->diffInSeconds($r->gerado_em)));
        $paginas = max(1, $gerados->sum('paginas_coletadas'));
        $processos = $gerados->sum('classificar_total');
        $publicacoes = max(1, $gerados->sum('publicacoes_total'));

        return $this->ritmo = [
            'porPagina' => max(0.5, $coleta->sum() / $paginas),
            'porProcesso' => $processos > 0 ? max(0.5, $classificacao->sum() / $processos) : self::SEGUNDOS_POR_PROCESSO,
            'candidatos' => $processos > 0 ? $processos / $publicacoes : self::CANDIDATOS_POR_PUBLICACAO,
            'amostras' => $gerados->count(),
            'mediaTotal' => (int) round($coleta->avg() + $classificacao->avg()),
            'mediaColeta' => (int) round($coleta->avg()),
            'mediaClassificacao' => (int) round($classificacao->avg()),
        ];
    }
}
