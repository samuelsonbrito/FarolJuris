<?php

namespace App\Jobs\RaioX;

use App\Enums\RaioX\StatusRaioX;
use App\Models\RaioX\RelatorioRaioX;
use App\Services\Djen\Exceptions\DjenException;
use App\Services\Djen\Exceptions\DjenRateLimitedException;
use App\Services\RaioX\GeradorRaioX;
use App\Services\Rastreio\Rastreador;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Coleta um bloco de páginas do DJEN e se reencadeia até o fim; então monta os processos.
 * Cada tarefa fica bem abaixo do retry_after da fila (90 s).
 */
class ColetarRaioXJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public int $timeout = 85;

    /** Páginas por tarefa (cada uma espera ~3 s para respeitar o limite da Comunica API). */
    public const PAGINAS_POR_TAREFA = 15;

    public function __construct(public int $relatorioId, public int $pagina) {}

    public function handle(GeradorRaioX $gerador): void
    {
        $relatorio = RelatorioRaioX::query()->find($this->relatorioId);

        if ($relatorio === null || $relatorio->status !== StatusRaioX::Processando) {
            return;
        }

        Rastreador::origem('raiox');

        try {
            $proxima = $gerador->coletar($relatorio, $this->pagina, self::PAGINAS_POR_TAREFA);
        } catch (DjenRateLimitedException $e) {
            $this->release($e->retryAfterSeconds ?? 60);

            return;
        } catch (DjenException $e) {
            report($e);
            $gerador->falhar($this->relatorioId, 'Não foi possível coletar as publicações no DJEN: '.$e->getMessage());

            return;
        }

        if ($proxima !== null) {
            self::dispatch($this->relatorioId, $proxima);

            return;
        }

        $relatorio->update(['etapa' => 'Separando os processos']);
        $gerador->montar($relatorio->fresh());
    }

    public function failed(?Throwable $e): void
    {
        app(GeradorRaioX::class)->falhar($this->relatorioId, 'A coleta falhou: '.($e ? class_basename($e) : 'erro desconhecido'));
    }
}
