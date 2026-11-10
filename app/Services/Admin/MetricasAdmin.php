<?php

namespace App\Services\Admin;

use App\Models\Atividade;
use App\Models\ChamadaDjen;
use App\Models\MonitoringTarget;
use App\Models\RegistroBusca;
use App\Support\Admin\Periodo;
use App\Support\Rastreio\TipoEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Números do dashboard administrativo. Tudo é calculado no PostgreSQL,
 * agrupando por dia/hora no fuso de exibição (Periodo::fuso()).
 * Robôs (User-Agent de bot) ficam de fora das contagens de uso.
 */
final class MetricasAdmin
{
    public const ROTULOS_CRITERIO = [
        'oab' => 'OAB',
        'parte' => 'Nome da parte',
        'advogado' => 'Nome do advogado',
        'texto' => 'Palavra-chave no teor',
        'processo' => 'Número do processo',
        'tribunal' => 'Só tribunal',
    ];

    public const ROTULOS_STATUS = [
        'ok' => 'Com resultados',
        'vazio' => 'Sem resultados',
        'erro' => 'Erro no CNJ',
        'invalido' => 'Filtro inválido',
    ];

    public const ROTULOS_DISPOSITIVO = [
        'desktop' => 'Computador',
        'celular' => 'Celular',
        'tablet' => 'Tablet',
        'bot' => 'Robô',
        'outro' => 'Outro',
    ];

    public const ROTULOS_RESULTADO_CNJ = [
        'sucesso' => 'Sucesso',
        'limitado' => 'Limite (429)',
        'bloqueado' => 'Bloqueado (403)',
        'indisponivel' => 'Indisponível',
        'recusado' => 'Recusado (4xx)',
        'invalido' => 'Resposta inválida',
    ];

    public const ROTULOS_ORIGEM_CNJ = [
        'consulta' => 'Consulta',
        'monitoramento' => 'Monitoramento',
        'exportacao' => 'Exportação',
        'comando' => 'Comandos',
        'outro' => 'Outro',
    ];

    /** @return array<string, mixed> */
    public function dashboard(Periodo $p): array
    {
        return [
            'indicadores' => $this->indicadores($p),
            'porDia' => $this->porDia($p),
            'buscasPorHora' => $this->buscasPorHora($p),
            'buscasPorCriterio' => $this->distribuicao($this->buscas($p), 'criterio', self::ROTULOS_CRITERIO),
            'buscasPorStatus' => $this->distribuicao($this->buscas($p), 'status', self::ROTULOS_STATUS),
            'tribunais' => $this->distribuicao($this->buscas($p)->whereNotNull('tribunal'), 'tribunal', limite: 10),
            'ufs' => $this->distribuicao($this->buscas($p)->whereNotNull('uf'), 'uf', limite: 10),
            'dispositivos' => $this->distribuicao($this->acessos($p), 'dispositivo', self::ROTULOS_DISPOSITIVO),
            'navegadores' => $this->distribuicao($this->acessos($p)->whereNotNull('navegador'), 'navegador', limite: 6),
            'sistemas' => $this->distribuicao($this->acessos($p)->whereNotNull('sistema'), 'sistema', limite: 6),
            'referencias' => $this->distribuicao($this->acessos($p)->whereNotNull('referer_host'), 'referer_host', limite: 8),
            'paginas' => $this->distribuicao($this->acessos($p)->whereNotNull('caminho'), 'caminho', limite: 8),
            'termos' => $this->termos($p),
            'cnj' => $this->cnj($p),
        ];
    }

    /** @return array<string, array{valor: int|float|null, anterior: int|float|null}> */
    public function indicadores(Periodo $p): array
    {
        $a = $p->anterior();
        $par = fn (callable $f) => ['valor' => $f($p), 'anterior' => $f($a)];

        $respondidas = fn (Periodo $x) => $this->buscas($x)->whereIn('status', ['ok', 'vazio']);

        return [
            'buscas' => $par(fn (Periodo $x) => $this->buscas($x)->count()),
            'visitantes' => $par(fn (Periodo $x) => $this->visitantes($x)->count()),
            'taxaResultado' => $par(fn (Periodo $x) => self::percentual(
                $respondidas($x)->where('status', 'ok')->count(),
                $respondidas($x)->count(),
            )),
            'tempoMedioMs' => $par(fn (Periodo $x) => self::inteiro($respondidas($x)->avg('duracao_ms'))),
            'taxaCache' => $par(fn (Periodo $x) => self::percentual(
                $respondidas($x)->where('do_cache', true)->count(),
                $respondidas($x)->count(),
            )),
            'cadastros' => $par(fn (Periodo $x) => DB::table('users')->whereBetween('created_at', [$x->inicioUtc(), $x->fimUtc()])->count()),
            'usuariosAtivos' => $par(fn (Periodo $x) => $this->usuariosAtivos($x)),
            'alertas' => $par(fn (Periodo $x) => DB::table('communication_target')->whereBetween('created_at', [$x->inicioUtc(), $x->fimUtc()])->count()),
            'notificacoes' => $par(fn (Periodo $x) => $this->eventos($x, TipoEvento::NOTIFICACAO_ENVIADA)->count()),
            'exportacoes' => $par(fn (Periodo $x) => DB::table('exports')->whereBetween('created_at', [$x->inicioUtc(), $x->fimUtc()])->count()),
            'chamadasCnj' => $par(fn (Periodo $x) => $this->chamadas($x)->count()),
            'falhasCnj' => $par(fn (Periodo $x) => self::percentual(
                $this->chamadas($x)->where('resultado', '!=', 'sucesso')->count(),
                $this->chamadas($x)->count(),
            )),
            'alvosAtivos' => ['valor' => MonitoringTarget::where('ativo', true)->count(), 'anterior' => null],
        ];
    }

    /** @return list<array{data: string, buscas: int, visitantes: int}> */
    public function porDia(Periodo $p): array
    {
        $tz = Periodo::fuso();
        $dia = "to_char((created_at AT TIME ZONE 'UTC') AT TIME ZONE ?, 'YYYY-MM-DD')";

        $buscas = $this->buscas($p)->toBase()
            ->selectRaw("{$dia} as dia, count(*) as total", [$tz])
            ->groupByRaw('1')
            ->pluck('total', 'dia');

        $uniao = $this->buscas($p)->toBase()->selectRaw("visitante, {$dia} as dia", [$tz])
            ->union($this->acessos($p)->toBase()->selectRaw("visitante, {$dia} as dia", [$tz]));

        $visitantes = DB::query()->fromSub($uniao, 'v')
            ->selectRaw('dia, count(*) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        return array_map(fn (string $data) => [
            'data' => $data,
            'buscas' => (int) ($buscas[$data] ?? 0),
            'visitantes' => (int) ($visitantes[$data] ?? 0),
        ], $p->datas());
    }

    /** @return list<array{hora: int, total: int}> */
    public function buscasPorHora(Periodo $p): array
    {
        $totais = $this->buscas($p)->toBase()
            ->selectRaw("extract(hour from (created_at AT TIME ZONE 'UTC') AT TIME ZONE ?)::int as hora, count(*) as total", [Periodo::fuso()])
            ->groupByRaw('1')
            ->pluck('total', 'hora');

        return array_map(fn (int $h) => ['hora' => $h, 'total' => (int) ($totais[$h] ?? 0)], range(0, 23));
    }

    /** @return array<string, list<array{chave: string, rotulo: string, total: int}>> */
    public function termos(Periodo $p): array
    {
        $top = function (string $expressao, string $coluna) use ($p): array {
            return $this->buscas($p)->toBase()
                ->whereNotNull($coluna)
                ->selectRaw("{$expressao} as termo, count(*) as total")
                ->groupByRaw('1')
                ->orderByDesc('total')
                ->orderBy('termo')
                ->limit(10)
                ->get()
                ->map(fn ($r) => ['chave' => (string) $r->termo, 'rotulo' => (string) $r->termo, 'total' => (int) $r->total])
                ->all();
        };

        return [
            'oab' => $top("oab || '/' || coalesce(uf, '')", 'oab'),
            'parte' => $top('upper(parte)', 'parte'),
            'advogado' => $top('upper(advogado)', 'advogado'),
            'processo' => $top('processo', 'processo'),
            'texto' => $top('lower(texto)', 'texto'),
        ];
    }

    /** @return array<string, mixed> */
    public function cnj(Periodo $p): array
    {
        $latencia = $this->chamadas($p)->toBase()
            ->selectRaw('avg(duracao_ms) as media, percentile_cont(0.95) within group (order by duracao_ms) as p95')
            ->first();

        $ultima = ChamadaDjen::query()->whereNotNull('ratelimit_restante')->latest('id')->first();

        return [
            'porResultado' => $this->distribuicao($this->chamadas($p), 'resultado', self::ROTULOS_RESULTADO_CNJ),
            'porOrigem' => $this->distribuicao($this->chamadas($p), 'origem', self::ROTULOS_ORIGEM_CNJ),
            'latenciaMediaMs' => self::inteiro($latencia?->media),
            'latenciaP95Ms' => self::inteiro($latencia?->p95),
            'ratelimitRestante' => $ultima?->ratelimit_restante,
            'ratelimitEm' => $ultima?->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, string>  $rotulos
     * @return list<array{chave: string, rotulo: string, total: int}>
     */
    public function distribuicao(Builder $query, string $coluna, array $rotulos = [], ?int $limite = null): array
    {
        return $query->toBase()
            ->selectRaw("{$coluna} as chave, count(*) as total")
            ->groupBy($coluna)
            ->orderByDesc('total')
            ->orderBy($coluna)
            ->when($limite, fn (QueryBuilder $q, int $l) => $q->limit($l))
            ->get()
            ->map(fn ($r) => [
                'chave' => (string) ($r->chave ?? ''),
                'rotulo' => $rotulos[(string) $r->chave] ?? (string) ($r->chave ?? '—'),
                'total' => (int) $r->total,
            ])
            ->all();
    }

    /** @return Builder<RegistroBusca> Buscas de pessoas (sem robôs) no período. */
    public function buscas(Periodo $p): Builder
    {
        return RegistroBusca::query()->where('bot', false)->whereBetween('created_at', [$p->inicioUtc(), $p->fimUtc()]);
    }

    /** @return Builder<Atividade> Páginas vistas por pessoas no período. */
    public function acessos(Periodo $p): Builder
    {
        return $this->eventos($p, TipoEvento::PAGINA_VISTA)->where('bot', false)->whereNotNull('visitante');
    }

    /** @return Builder<Atividade> */
    public function eventos(Periodo $p, string $tipo): Builder
    {
        return Atividade::query()->where('tipo', $tipo)->whereBetween('created_at', [$p->inicioUtc(), $p->fimUtc()]);
    }

    /** @return Builder<ChamadaDjen> */
    public function chamadas(Periodo $p): Builder
    {
        return ChamadaDjen::query()->whereBetween('created_at', [$p->inicioUtc(), $p->fimUtc()]);
    }

    /** Visitantes únicos: quem buscou ou viu alguma página. */
    private function visitantes(Periodo $p): QueryBuilder
    {
        return DB::query()->fromSub(
            $this->buscas($p)->toBase()->select('visitante')
                ->union($this->acessos($p)->toBase()->select('visitante')),
            'v',
        );
    }

    private function usuariosAtivos(Periodo $p): int
    {
        $entre = [$p->inicioUtc(), $p->fimUtc()];

        return DB::query()->fromSub(
            DB::table('activity_logs')->select('user_id')->whereNotNull('user_id')->whereBetween('created_at', $entre)
                ->union(DB::table('search_logs')->select('user_id')->whereNotNull('user_id')->whereBetween('created_at', $entre)),
            'u',
        )->count();
    }

    private static function percentual(int $parte, int $total): ?float
    {
        return $total > 0 ? round($parte * 100 / $total, 1) : null;
    }

    private static function inteiro(mixed $valor): ?int
    {
        return is_numeric($valor) ? (int) round((float) $valor) : null;
    }
}
