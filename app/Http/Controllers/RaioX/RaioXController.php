<?php

namespace App\Http\Controllers\RaioX;

use App\Enums\RaioX\StatusRaioX;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultaRequest;
use App\Http\Requests\RaioX\StoreRaioXRequest;
use App\Http\Resources\RaioX\RelatorioRaioXResource;
use App\Models\RaioX\RelatorioRaioX;
use App\Models\User;
use App\Notifications\RaioX\RaioXSolicitado;
use App\Services\RaioX\MetricasRaioX;
use App\Services\RaioX\PlanilhaRaioX;
use App\Services\Rastreio\Rastreador;
use App\Support\CapitaisUf;
use App\Support\Rastreio\TipoEvento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Menu Raio-X do usuário logado: pede o relatório da OAB e vê os relatórios liberados. */
class RaioXController extends Controller
{
    public function index(Request $request): Response
    {
        $relatorios = $request->user()->relatoriosRaioX()->with('user')->latest()->get();

        return Inertia::render('RaioX/Index', [
            'relatorios' => RelatorioRaioXResource::collection($relatorios)->resolve($request),
            'ufs' => ConsultaRequest::UFS,
            'meses' => (int) config('raiox.meses'),
        ]);
    }

    public function store(StoreRaioXRequest $request, Rastreador $rastreador): RedirectResponse
    {
        $dados = $request->validated();
        $user = $request->user();

        $emAberto = $user->relatoriosRaioX()
            ->where('oab', $dados['oab'])
            ->where('uf', $dados['uf'])
            ->whereIn('status', [StatusRaioX::Solicitado, StatusRaioX::Processando, StatusRaioX::Conferencia, StatusRaioX::Pronto])
            ->exists();

        if ($emAberto) {
            return back()->withErrors(['oab' => 'Já existe um pedido em andamento para esta OAB.']);
        }

        $relatorio = self::criar($user, $dados['oab'], $dados['uf']);
        $rastreador->evento(TipoEvento::RAIOX_SOLICITADO, ['relatorio_id' => $relatorio->id], $user, $request);

        Notification::send(User::query()->where('is_admin', true)->get(), new RaioXSolicitado($relatorio));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pedido enviado. Avisaremos por e-mail quando o Raio-X estiver pronto.']);

        return back();
    }

    public static function criar(User $user, string $oab, string $uf): RelatorioRaioX
    {
        $hoje = now((string) config('djen.monitoring.timezone'))->startOfDay();

        return $user->relatoriosRaioX()->create([
            'oab' => $oab,
            'uf' => $uf,
            'inicio' => $hoje->copy()->subMonthsNoOverflow((int) config('raiox.meses'))->toDateString(),
            'fim' => $hoje->toDateString(),
        ]);
    }

    public function show(Request $request, RelatorioRaioX $relatorio, MetricasRaioX $metricas): Response
    {
        Gate::authorize('view', $relatorio);

        return Inertia::render('RaioX/Show', [
            'relatorio' => (new RelatorioRaioXResource($relatorio->load('user')))->resolve($request),
            'metricas' => $metricas->calcular($relatorio),
        ]);
    }

    /** Calculadora: custo do escritório informado pelo próprio advogado. */
    public function custo(Request $request, RelatorioRaioX $relatorio): RedirectResponse
    {
        Gate::authorize('update', $relatorio);

        $dados = $request->validate([
            'custoMensal' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'custoVariavel' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'honorarioMedio' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $relatorio->update([
            'custo_mensal' => $dados['custoMensal'] ?? null,
            'custo_variavel' => $dados['custoVariavel'] ?? null,
            'honorario_medio' => $dados['honorarioMedio'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Custos atualizados.']);

        return back();
    }

    public function planilha(RelatorioRaioX $relatorio, MetricasRaioX $metricas): BinaryFileResponse
    {
        Gate::authorize('view', $relatorio);

        return Excel::download(new PlanilhaRaioX($relatorio, $metricas->calcular($relatorio)), $this->arquivo($relatorio, 'xlsx'));
    }

    public function pdf(RelatorioRaioX $relatorio, MetricasRaioX $metricas): HttpResponse
    {
        Gate::authorize('view', $relatorio);

        return Pdf::loadView('exports.raiox', [
            'r' => $relatorio,
            'm' => $metricas->calcular($relatorio),
            'capital' => CapitaisUf::capital($relatorio->uf),
            'geradoEm' => now()->timezone((string) config('djen.monitoring.timezone')),
        ])
            ->setPaper('a4')
            ->setOption('enable_font_subsetting', true)
            ->download($this->arquivo($relatorio, 'pdf'));
    }

    private function arquivo(RelatorioRaioX $r, string $extensao): string
    {
        return "raio-x-oab-{$r->oab}-{$r->uf}-{$r->fim->format('Y-m-d')}.{$extensao}";
    }
}
