<?php

namespace App\Http\Resources\RaioX;

use App\Models\RaioX\ProcessoRaioX;
use App\Support\NumeroCnj;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Espelhado em resources/js/types/raiox.ts (ProcessoRaioX).
 *
 * @mixin ProcessoRaioX
 */
class ProcessoRaioXResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ProcessoRaioX $p */
        $p = $this->resource;

        return [
            'id' => $p->id,
            'numero' => $p->numero_processo,
            'numeroMascara' => strlen($p->numero_processo) === 20 ? NumeroCnj::mascara($p->numero_processo) : $p->numero_processo,
            'tribunal' => $p->tribunal,
            'publicacoes' => $p->publicacoes,
            'ultimaPublicacao' => $p->ultima_publicacao?->toDateString(),
            'resultado' => $p->resultado?->value,
            'extincaoSemMerito' => $p->extincao_sem_merito,
            'instancia' => $p->instancia?->value,
            'orgaoJulgador' => $p->orgao_julgador,
            'varaOrigem' => $p->vara_origem,
            'grupoOrigem' => $p->grupo_origem?->value,
            'tema' => $p->tema?->value,
            'parteContraria' => $p->parte_contraria,
            'poloCliente' => $p->polo_cliente,
            'dataDecisao' => $p->data_decisao?->toDateString(),
            'confianca' => $p->confianca?->value,
            'justificativa' => $p->justificativa,
            'textoDecisao' => $p->texto_decisao,
            'precisaConferencia' => $p->precisa_conferencia,
            'conferidoEm' => $p->conferido_em?->toIso8601String(),
        ];
    }
}
