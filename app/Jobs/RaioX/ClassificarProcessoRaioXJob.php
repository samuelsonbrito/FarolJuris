<?php

namespace App\Jobs\RaioX;

use App\Models\RaioX\ProcessoRaioX;
use App\Services\RaioX\GeradorRaioX;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Classifica a decisão de um processo (uma chamada à IA). Falhas viram conferência humana. */
class ClassificarProcessoRaioXJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 2;

    public int $timeout = 85;

    /** @var list<int> */
    public array $backoff = [30];

    public function __construct(public int $processoId) {}

    public function handle(GeradorRaioX $gerador): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $processo = ProcessoRaioX::query()->find($this->processoId);

        if ($processo !== null) {
            $gerador->classificarProcesso($processo);
        }
    }
}
