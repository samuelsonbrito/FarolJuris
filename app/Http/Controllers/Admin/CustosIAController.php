<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IA\ChamadaIA;
use App\Support\Admin\Periodo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Custos de IA: tokens gastos e valores em dólar e em real, por dia, por Raio-X e por chamada. */
class CustosIAController extends Controller
{
    use Concerns;

    public const POR_PAGINA = 50;

    public function __invoke(Request $request): Response
    {
        $periodo = Periodo::deRequest($request);
        $base = fn (): Builder => ChamadaIA::query()->whereBetween('ia_chamadas.created_at', [$periodo->inicioUtc(), $periodo->fimUtc()]);

        $t = $base()->toBase()->selectRaw('
            count(*) as chamadas,
            count(distinct relatorio_id) as relatorios,
            coalesce(sum(tokens_entrada), 0) as entrada,
            coalesce(sum(tokens_cache_leitura), 0) as cache_leitura,
            coalesce(sum(tokens_cache_escrita), 0) as cache_escrita,
            coalesce(sum(tokens_saida), 0) as saida,
            coalesce(sum(custo_usd), 0) as usd,
            coalesce(sum(custo_brl), 0) as brl,
            count(*) filter (where resultado <> \'ok\') as falhas,
            count(*) filter (where custo_usd is null) as sem_preco,
            avg(duracao_ms) as duracao_media
        ')->first();

        $chamadas = (int) $t->chamadas;
        $relatorios = (int) $t->relatorios;

        $tz = Periodo::fuso();
        $porDia = $base()->toBase()
            ->selectRaw("to_char((created_at AT TIME ZONE 'UTC') AT TIME ZONE ?, 'YYYY-MM-DD') as dia, count(*) as chamadas, coalesce(sum(custo_usd), 0) as usd, coalesce(sum(custo_brl), 0) as brl", [$tz])
            ->groupByRaw('1')
            ->get()
            ->keyBy('dia');

        $porRelatorio = $base()->toBase()
            ->leftJoin('raiox_relatorios as r', 'r.id', '=', 'ia_chamadas.relatorio_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->selectRaw('ia_chamadas.relatorio_id, r.oab, r.uf, u.name as conta, count(*) as chamadas,
                sum(ia_chamadas.tokens_entrada + ia_chamadas.tokens_cache_leitura + ia_chamadas.tokens_cache_escrita) as tokens_entrada,
                sum(ia_chamadas.tokens_saida) as tokens_saida, coalesce(sum(ia_chamadas.custo_usd), 0) as usd, coalesce(sum(ia_chamadas.custo_brl), 0) as brl')
            ->groupBy('ia_chamadas.relatorio_id', 'r.oab', 'r.uf', 'u.name')
            ->orderByDesc(DB::raw('coalesce(sum(ia_chamadas.custo_usd), 0)'))
            ->limit(30)
            ->get();

        $lista = $base()->with('relatorio:id,oab,uf')->latest('id')->paginate(self::POR_PAGINA, pageName: 'pagina')->withQueryString();

        return Inertia::render('Admin/CustosIA', [
            'periodo' => $periodo->toArray(),
            'presets' => self::presets(),
            'cotacao' => (float) config('raiox.custos.cotacao_usd_brl'),
            'precos' => config('raiox.custos.precos'),
            'modeloAtual' => (string) config('raiox.ia.modelo'),
            'resumo' => [
                'chamadas' => $chamadas,
                'relatorios' => $relatorios,
                'usd' => round((float) $t->usd, 4),
                'brl' => round((float) $t->brl, 4),
                'tokens' => [
                    'entrada' => (int) $t->entrada,
                    'cacheLeitura' => (int) $t->cache_leitura,
                    'cacheEscrita' => (int) $t->cache_escrita,
                    'saida' => (int) $t->saida,
                ],
                'mediaUsdPorChamada' => $chamadas > 0 ? round((float) $t->usd / $chamadas, 6) : null,
                'mediaBrlPorChamada' => $chamadas > 0 ? round((float) $t->brl / $chamadas, 6) : null,
                'mediaBrlPorRelatorio' => $relatorios > 0 ? round((float) $t->brl / $relatorios, 4) : null,
                'falhas' => (int) $t->falhas,
                'semPreco' => (int) $t->sem_preco,
                'duracaoMediaMs' => $t->duracao_media !== null ? (int) round((float) $t->duracao_media) : null,
            ],
            'porDia' => array_map(fn (string $dia) => [
                'data' => $dia,
                'chamadas' => (int) ($porDia[$dia]->chamadas ?? 0),
                'usd' => round((float) ($porDia[$dia]->usd ?? 0), 4),
                'brl' => round((float) ($porDia[$dia]->brl ?? 0), 4),
            ], $periodo->datas()),
            'porRelatorio' => $porRelatorio->map(fn ($r) => [
                'relatorioId' => $r->relatorio_id,
                'oab' => $r->oab ? "{$r->oab}/{$r->uf}" : null,
                'conta' => $r->conta,
                'chamadas' => (int) $r->chamadas,
                'tokensEntrada' => (int) $r->tokens_entrada,
                'tokensSaida' => (int) $r->tokens_saida,
                'usd' => round((float) $r->usd, 4),
                'brl' => round((float) $r->brl, 4),
            ])->all(),
            'chamadas' => [
                'data' => $lista->getCollection()->map(fn (ChamadaIA $c) => [
                    'id' => $c->id,
                    'quando' => self::quando($c->created_at),
                    'relatorio' => $c->relatorio ? ['id' => $c->relatorio->id, 'oab' => "{$c->relatorio->oab}/{$c->relatorio->uf}"] : null,
                    'modelo' => $c->modelo,
                    'tokensEntrada' => $c->tokens_entrada,
                    'tokensCacheLeitura' => $c->tokens_cache_leitura,
                    'tokensCacheEscrita' => $c->tokens_cache_escrita,
                    'tokensSaida' => $c->tokens_saida,
                    'usd' => $c->custo_usd !== null ? (float) $c->custo_usd : null,
                    'brl' => $c->custo_brl !== null ? (float) $c->custo_brl : null,
                    'cotacao' => (float) $c->cotacao_usd_brl,
                    'resultado' => $c->resultado,
                    'duracaoMs' => $c->duracao_ms,
                ])->all(),
                'meta' => self::meta($lista),
            ],
        ]);
    }
}
