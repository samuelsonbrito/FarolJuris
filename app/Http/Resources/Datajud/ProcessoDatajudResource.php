<?php

namespace App\Http\Resources\Datajud;

use App\Data\Datajud\MovimentoData;
use App\Data\Datajud\ProcessoDatajudData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Espelhado em resources/js/types/datajud.ts (ProcessoDatajud).
 *
 * @mixin ProcessoDatajudData
 */
class ProcessoDatajudResource extends JsonResource
{
    /** Graus do DataJud com rótulo conhecido; os demais aparecem como vieram. */
    private const GRAUS = [
        'G1' => '1º grau',
        'G2' => '2º grau',
        'JE' => 'Juizado Especial',
        'TR' => 'Turma Recursal',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProcessoDatajudData $p */
        $p = $this->resource;

        return [
            'tribunal' => $p->tribunal,
            'grau' => $p->grau,
            'grauRotulo' => $p->grau !== null ? (self::GRAUS[$p->grau] ?? $p->grau) : null,
            'numeroProcesso' => $p->numeroProcesso,
            'classe' => $p->classe,
            'codigoClasse' => $p->codigoClasse,
            'assuntos' => $p->assuntos,
            'orgaoJulgador' => $p->orgaoJulgador,
            'dataAjuizamento' => $p->dataAjuizamento?->toIso8601ZuluString(),
            'sistema' => $p->sistema,
            'formato' => $p->formato,
            'atualizadoEm' => $p->atualizadoEm?->toIso8601ZuluString(),
            'movimentos' => array_map(fn (MovimentoData $m): array => [
                'dataHora' => $m->dataHora?->toIso8601ZuluString(),
                'codigo' => $m->codigo,
                'nome' => $m->nome,
                'complementos' => $m->complementos,
                'orgaoJulgador' => $m->orgaoJulgador,
            ], $p->movimentos),
        ];
    }
}
