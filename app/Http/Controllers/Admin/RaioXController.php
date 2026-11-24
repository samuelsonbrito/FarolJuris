<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RaioX\GrupoOrigem;
use App\Enums\RaioX\InstanciaDecisao;
use App\Enums\RaioX\ResultadoDecisao;
use App\Enums\RaioX\StatusRaioX;
use App\Enums\RaioX\TemaRaioX;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RaioX\RaioXController as RaioXUsuarioController;
use App\Http\Requests\ConsultaRequest;
use App\Http\Requests\RaioX\ConferirProcessoRequest;
use App\Http\Requests\RaioX\StoreRaioXRequest;
use App\Http\Resources\RaioX\ProcessoRaioXResource;
use App\Http\Resources\RaioX\RelatorioRaioXResource;
use App\Models\RaioX\ProcessoRaioX;
use App\Models\RaioX\RelatorioRaioX;
use App\Models\User;
use App\Notifications\RaioX\RaioXLiberado;
use App\Services\RaioX\GeradorRaioX;
use App\Services\Rastreio\Rastreador;
use App\Support\CapitaisUf;
use App\Support\Rastreio\TipoEvento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Admin: pedidos de Raio-X, geração (custo de IA), conferência dos ambíguos e liberação. */
class RaioXController extends Controller
{
    public function index(Request $request): Response
    {
        $relatorios = RelatorioRaioX::query()->with('user')->latest()->paginate(30);

        return Inertia::render('Admin/RaioX/Index', [
            'relatorios' => RelatorioRaioXResource::collection($relatorios->items())->resolve($request),
            'paginacao' => ['pagina' => $relatorios->currentPage(), 'ultimaPagina' => $relatorios->lastPage(), 'total' => $relatorios->total()],
            'ufs' => ConsultaRequest::UFS,
            'iaDriver' => (string) config('raiox.ia.driver'),
            'iaConfigurada' => config('raiox.ia.driver') === 'fake' || filled(config('raiox.ia.api_key')),
        ]);
    }

    /** Cria um Raio-X em nome de uma conta (pelo e-mail). */
    public function store(StoreRaioXRequest $request): RedirectResponse
    {
        $dados = $request->validated();

        if (empty($dados['email'])) {
            return back()->withErrors(['email' => 'Informe o e-mail da conta que vai receber o Raio-X.']);
        }

        $user = User::query()->where('email', $dados['email'])->firstOrFail();
        RaioXUsuarioController::criar($user, $dados['oab'], $dados['uf']);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Raio-X criado para {$user->name}."]);

        return back();
    }

    public function gerar(Request $request, RelatorioRaioX $relatorio, GeradorRaioX $gerador, Rastreador $rastreador): RedirectResponse
    {
        if (! $relatorio->podeGerar()) {
            return back()->withErrors(['relatorio' => 'Este Raio-X já está em processamento.']);
        }

        $gerador->iniciar($relatorio);
        $rastreador->evento(TipoEvento::RAIOX_GERADO, ['relatorio_id' => $relatorio->id], $request->user(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Geração iniciada. Acompanhe o andamento nesta lista.']);

        return back();
    }

    public function conferencia(Request $request, RelatorioRaioX $relatorio): Response
    {
        $filtro = in_array($request->query('filtro'), ['pendentes', 'decididos', 'todos'], true) ? $request->query('filtro') : 'pendentes';

        $processos = $relatorio->processos()
            ->when($filtro === 'pendentes', fn ($q) => $q->where('precisa_conferencia', true))
            ->when($filtro === 'decididos', fn ($q) => $q->where('precisa_conferencia', false)->where('resultado', '!=', ResultadoDecisao::SemDecisao->value))
            ->orderByDesc('precisa_conferencia')
            ->orderByDesc('data_decisao')
            ->get();

        $opcoes = fn (array $casos, callable $rotulo) => array_map(fn ($c) => ['valor' => $c->value, 'rotulo' => $rotulo($c)], $casos);

        return Inertia::render('Admin/RaioX/Conferencia', [
            'relatorio' => (new RelatorioRaioXResource($relatorio->load('user')))->resolve($request),
            'processos' => ProcessoRaioXResource::collection($processos)->resolve($request),
            'filtro' => $filtro,
            'opcoes' => [
                'resultados' => $opcoes(ResultadoDecisao::cases(), fn (ResultadoDecisao $c) => $c->rotulo()),
                'instancias' => $opcoes(InstanciaDecisao::cases(), fn (InstanciaDecisao $c) => $c->rotulo()),
                'grupos' => $opcoes(GrupoOrigem::cases(), fn (GrupoOrigem $c) => $c->rotulo(CapitaisUf::capital($relatorio->uf), CapitaisUf::estado($relatorio->uf))),
                'temas' => $opcoes(TemaRaioX::cases(), fn (TemaRaioX $c) => $c->rotulo()),
            ],
        ]);
    }

    public function conferir(ConferirProcessoRequest $request, ProcessoRaioX $processo, GeradorRaioX $gerador): RedirectResponse
    {
        $d = $request->validated();

        $processo->update([
            'resultado' => $d['resultado'],
            'extincao_sem_merito' => (bool) ($d['extincaoSemMerito'] ?? false),
            'instancia' => $d['instancia'] ?? null,
            'orgao_julgador' => $d['orgaoJulgador'] ?? null,
            'vara_origem' => $d['varaOrigem'] ?? null,
            'grupo_origem' => $d['grupoOrigem'] ?? null,
            'tema' => $d['tema'] ?? null,
            'parte_contraria' => $d['parteContraria'] ?? null,
            'polo_cliente' => $d['poloCliente'] ?? $processo->polo_cliente,
            'precisa_conferencia' => false,
            'conferido_em' => now(),
            'conferido_por' => $request->user()->id,
        ]);

        $gerador->atualizarStatusAposConferencia($processo->relatorio);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Classificação conferida.']);

        return back();
    }

    public function liberar(Request $request, RelatorioRaioX $relatorio, Rastreador $rastreador): RedirectResponse
    {
        if (! $relatorio->podeLiberar()) {
            return back()->withErrors(['relatorio' => 'Só dá para liberar depois de gerar e conferir todos os casos ambíguos.']);
        }

        $relatorio->update([
            'status' => StatusRaioX::Liberado,
            'liberado_em' => now(),
            'liberado_por' => $request->user()->id,
        ]);

        $relatorio->user->notify(new RaioXLiberado($relatorio));
        $rastreador->evento(TipoEvento::RAIOX_LIBERADO, ['relatorio_id' => $relatorio->id], $request->user(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Raio-X liberado para {$relatorio->user->name}."]);

        return back();
    }

    public function destroy(RelatorioRaioX $relatorio): RedirectResponse
    {
        $relatorio->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Raio-X excluído.']);

        return to_route('admin.raiox.index');
    }
}
