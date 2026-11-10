<?php

namespace App\Services\Rastreio;

use App\Data\Djen\FiltroConsulta;
use App\Data\Djen\ResultadoConsulta;
use App\Models\Atividade;
use App\Models\ChamadaDjen;
use App\Models\RegistroBusca;
use App\Models\User;
use App\Support\Rastreio\Origem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Throwable;

/**
 * Grava o rastreio usado pela área administrativa: buscas da Consulta,
 * chamadas ao CNJ e a trilha de atividade.
 *
 * Nunca derruba a requisição: qualquer falha ao gravar é só reportada.
 * Desligável por DJEN_RASTREIO_ATIVO=false.
 */
final class Rastreador
{
    /** Chave do Context que diz quem originou uma chamada ao CNJ. */
    public const ORIGEM = 'djen_origem';

    /** Marca a origem das próximas chamadas ao CNJ neste processo/job. */
    public static function origem(string $origem): void
    {
        Context::add(self::ORIGEM, $origem);
    }

    /**
     * @param  'ok'|'vazio'|'erro'|'invalido'  $status
     */
    public function busca(
        Request $request,
        FiltroConsulta $filtro,
        string $status,
        ?ResultadoConsulta $resultado = null,
        ?string $erroTipo = null,
        ?int $duracaoMs = null,
    ): void {
        $this->gravar(fn () => RegistroBusca::create([
            'user_id' => $request->user()?->id,
            ...Origem::de($request),
            'criterio' => self::criterio($filtro),
            'oab' => $filtro->numeroOab,
            'uf' => $filtro->ufOab,
            'parte' => $filtro->nomeParte,
            'advogado' => $filtro->nomeAdvogado,
            'processo' => $filtro->numeroProcesso,
            'tribunal' => $filtro->tribunal,
            'texto' => $filtro->texto !== null ? mb_substr($filtro->texto, 0, 200) : null,
            'meio' => $filtro->meio,
            'numero_comunicacao' => $filtro->numeroComunicacao,
            'data_inicio' => $filtro->dataInicio?->toDateString(),
            'data_fim' => $filtro->dataFim?->toDateString(),
            'pagina' => min($filtro->pagina, 65535),
            'status' => $status,
            'erro_tipo' => $erroTipo,
            'total_resultados' => $resultado?->total,
            'duracao_ms' => $duracaoMs,
            'do_cache' => $resultado?->doCache,
        ]));
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function evento(string $tipo, array $dados = [], ?User $user = null, ?Request $request = null): void
    {
        $this->gravar(fn () => Atividade::create([
            'user_id' => $user?->id ?? $request?->user()?->id,
            'tipo' => $tipo,
            'dados' => $dados === [] ? null : $dados,
            ...($request ? [
                ...Origem::de($request),
                'rota' => $request->route()?->getName(),
                'caminho' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
            ] : []),
        ]));
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    public function chamadaDjen(
        string $endpoint,
        array $parametros,
        ?int $status,
        string $resultado,
        int $duracaoMs,
        ?int $itens = null,
        ?int $ratelimitRestante = null,
        ?string $erro = null,
    ): void {
        $this->gravar(fn () => ChamadaDjen::create([
            'origem' => (string) (Context::get(self::ORIGEM) ?? 'outro'),
            'endpoint' => mb_substr($endpoint, 0, 60),
            'parametros' => $parametros,
            'status' => $status,
            'resultado' => $resultado,
            'duracao_ms' => $duracaoMs,
            'itens' => $itens,
            'ratelimit_restante' => $ratelimitRestante,
            'erro' => $erro !== null ? mb_strimwidth($erro, 0, 255, '…') : null,
        ]));
    }

    /** Critério principal de uma busca, do mais específico para o mais amplo. */
    public static function criterio(FiltroConsulta $f): ?string
    {
        return match (true) {
            $f->numeroProcesso !== null => 'processo',
            $f->numeroOab !== null => 'oab',
            $f->nomeParte !== null => 'parte',
            $f->nomeAdvogado !== null => 'advogado',
            $f->texto !== null => 'texto',
            $f->tribunal !== null => 'tribunal',
            default => null,
        };
    }

    private function gravar(callable $gravacao): void
    {
        if (! config('djen.rastreio.ativo')) {
            return;
        }

        try {
            $gravacao();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
