<?php

namespace App\Http\Resources\Djen;

use App\Data\Djen\ResultadoConsulta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Espelhado em resources/js/types/djen.ts (ResultadoConsulta).
 *
 * @mixin ResultadoConsulta
 */
class ResultadoConsultaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ResultadoConsulta $r */
        $r = $this->resource;

        return [
            'data' => ComunicacaoResource::collection($r->comunicacoes)->resolve($request),
            'meta' => [
                'pagina' => $r->pagina,
                'porPagina' => $r->porPagina,
                'total' => $r->total,
                'ultimaPagina' => $r->ultimaPagina(),
                'filtroParcial' => $r->filtroParcial,
            ],
        ];
    }
}
