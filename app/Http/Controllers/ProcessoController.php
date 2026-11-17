<?php

namespace App\Http\Controllers;

use App\Data\Djen\FiltroConsulta;
use App\Enums\TipoAlvo;
use App\Http\Resources\Djen\ResultadoConsultaResource;
use App\Services\Datajud\DatajudClient;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Exceptions\DjenBlockedException;
use App\Services\Djen\Exceptions\DjenException;
use App\Services\Djen\Exceptions\DjenRateLimitedException;
use App\Services\Djen\Exceptions\DjenUnavailableException;
use App\Services\Rastreio\Rastreador;
use App\Support\NumeroCnj;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página pública de um processo: publicações no DJEN, andamentos do DataJud
 * (tribunais habilitados) e atalho para monitorar.
 */
class ProcessoController extends Controller
{
    public function __invoke(
        Request $request,
        string $numero,
        ComunicacaoGateway $gateway,
        DatajudClient $datajud,
        Rastreador $rastreador,
    ): Response|RedirectResponse {
        $digitos = NumeroCnj::normalizar($numero);

        abort_if($digitos === null, 404);

        // Link com máscara (ex.: copiado do diário): endereço canônico só com dígitos.
        if ($digitos !== $numero) {
            return redirect()->route('processos.show', ['numero' => $digitos, ...$request->query()], 301);
        }

        $tribunal = NumeroCnj::tribunal($digitos);
        $pagina = max(1, min(500, (int) $request->query('pagina', 1)));
        $filtro = new FiltroConsulta(
            numeroProcesso: $digitos,
            pagina: $pagina,
            porPagina: (int) config('djen.itens_por_pagina'),
        );

        $resultados = null;
        $erro = null;

        Rastreador::origem('processo');
        $inicio = hrtime(true);

        try {
            $resultado = $gateway->buscar($filtro);
            $resultados = (new ResultadoConsultaResource($resultado))->resolve($request);
            $rastreador->busca($request, $filtro, $resultado->total > 0 ? 'ok' : 'vazio', $resultado, duracaoMs: self::ms($inicio));
        } catch (DjenException $e) {
            report($e);
            $erro = match (true) {
                $e instanceof DjenRateLimitedException => ['tipo' => 'limite', 'mensagem' => 'Muitas consultas ao CNJ em pouco tempo. Aguarde um minuto e tente de novo.'],
                $e instanceof DjenBlockedException => ['tipo' => 'bloqueado', 'mensagem' => 'O CNJ recusou o acesso a partir deste servidor. Tente novamente mais tarde.'],
                $e instanceof DjenUnavailableException => ['tipo' => 'indisponivel', 'mensagem' => 'O serviço do CNJ está fora do ar ou muito lento no momento. Tente novamente em alguns minutos.'],
                default => ['tipo' => 'inesperado', 'mensagem' => 'Não foi possível concluir a consulta ao CNJ. Tente novamente.'],
            };
            $rastreador->busca($request, $filtro, 'erro', erroTipo: $erro['tipo'], duracaoMs: self::ms($inicio));
        }

        $alvo = $request->user()?->monitoringTargets()
            ->where('tipo', TipoAlvo::Processo->value)
            ->where('valor', $digitos)
            ->first(['id', 'ativo']);

        return Inertia::render('Processo/Show', [
            'processo' => [
                'numero' => $digitos,
                'numeroMascara' => NumeroCnj::mascara($digitos),
                'tribunal' => $tribunal,
                'tribunalNome' => $tribunal !== null ? config("tribunais.{$tribunal}") : null,
                'andamentos' => $tribunal !== null && config('datajud.ativo') && $datajud->suporta($tribunal),
            ],
            'alvo' => $alvo ? ['id' => $alvo->id, 'ativo' => (bool) $alvo->ativo] : null,
            'resultados' => $resultados,
            'erro' => $erro,
        ]);
    }

    private static function ms(int $inicio): int
    {
        return (int) round((hrtime(true) - $inicio) / 1_000_000);
    }
}
