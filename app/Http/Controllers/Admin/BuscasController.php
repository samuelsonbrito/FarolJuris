<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegistroBusca;
use App\Services\Admin\MetricasAdmin;
use App\Support\Admin\Periodo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Todas as buscas feitas na Consulta, com filtros. */
class BuscasController extends Controller
{
    use Concerns;

    public const POR_PAGINA = 50;

    public function __invoke(Request $request): Response
    {
        $periodo = Periodo::deRequest($request);
        $f = $request->validate([
            'criterio' => ['nullable', Rule::in(array_keys(MetricasAdmin::ROTULOS_CRITERIO))],
            'status' => ['nullable', Rule::in(array_keys(MetricasAdmin::ROTULOS_STATUS))],
            'quem' => ['nullable', Rule::in(['usuarios', 'visitantes'])],
            'usuario' => ['nullable', 'integer'],
            'termo' => ['nullable', 'string', 'max:120'],
            'robos' => ['nullable', 'boolean'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $buscas = RegistroBusca::query()
            ->with('user:id,name,email')
            ->whereBetween('created_at', [$periodo->inicioUtc(), $periodo->fimUtc()])
            ->when(! ($f['robos'] ?? false), fn ($q) => $q->where('bot', false))
            ->when($f['criterio'] ?? null, fn ($q, $v) => $q->where('criterio', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when(($f['quem'] ?? null) === 'usuarios', fn ($q) => $q->whereNotNull('user_id'))
            ->when(($f['quem'] ?? null) === 'visitantes', fn ($q) => $q->whereNull('user_id'))
            ->when($f['usuario'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['termo'] ?? null, function ($q, string $termo) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termo).'%';
                $digitos = preg_replace('/\D/', '', $termo);
                $q->where(fn ($w) => $w
                    ->where('parte', 'ilike', $like)
                    ->orWhere('advogado', 'ilike', $like)
                    ->orWhere('oab', 'ilike', $like)
                    ->orWhere('tribunal', 'ilike', $like)
                    ->orWhere('texto', 'ilike', $like)
                    ->when($digitos !== '', fn ($d) => $d->orWhere('processo', 'like', "%{$digitos}%")));
            })
            ->latest('id')
            ->paginate(self::POR_PAGINA, pageName: 'pagina')
            ->withQueryString();

        return Inertia::render('Admin/Buscas', [
            'periodo' => $periodo->toArray(),
            'presets' => self::presets(),
            'filtros' => [
                'criterio' => $f['criterio'] ?? null,
                'status' => $f['status'] ?? null,
                'quem' => $f['quem'] ?? null,
                'usuario' => isset($f['usuario']) ? (int) $f['usuario'] : null,
                'termo' => $f['termo'] ?? null,
                'robos' => (bool) ($f['robos'] ?? false),
            ],
            'criterios' => MetricasAdmin::ROTULOS_CRITERIO,
            'status' => MetricasAdmin::ROTULOS_STATUS,
            'buscas' => [
                'data' => $buscas->getCollection()->map(fn (RegistroBusca $b) => [
                    'id' => $b->id,
                    'quando' => self::quando($b->created_at),
                    'criterio' => $b->criterio,
                    'filtros' => array_filter([
                        'OAB' => $b->oab ? "{$b->oab}/{$b->uf}" : null,
                        'Parte' => $b->parte,
                        'Advogado' => $b->advogado,
                        'Processo' => $b->processo,
                        'Tribunal' => $b->tribunal,
                        'Teor' => $b->texto,
                        'Meio' => $b->meio,
                        'Comunicação' => $b->numero_comunicacao,
                        'Tipo' => $b->tipo_comunicacao,
                        'De' => $b->data_inicio?->format('d/m/Y'),
                        'Até' => $b->data_fim?->format('d/m/Y'),
                        'Página' => $b->pagina > 1 ? (string) $b->pagina : null,
                    ]),
                    'status' => $b->status,
                    'erroTipo' => $b->erro_tipo,
                    'resultados' => $b->total_resultados,
                    'duracaoMs' => $b->duracao_ms,
                    'doCache' => $b->do_cache,
                    'usuario' => $b->user ? ['id' => $b->user->id, 'nome' => $b->user->name, 'email' => $b->user->email] : null,
                    'visitante' => $b->visitante,
                    'dispositivo' => $b->dispositivo,
                    'navegador' => $b->navegador,
                    'sistema' => $b->sistema,
                    'ip' => $b->ip_mascarado,
                    'referencia' => $b->referer_host,
                    'bot' => $b->bot,
                ])->all(),
                'meta' => self::meta($buscas),
            ],
        ]);
    }
}
