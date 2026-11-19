<?php

namespace App\Http\Resources\Djen;

use App\Data\Djen\TribunalData;
use App\Support\CategoriaTribunal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Espelhado em resources/js/types/djen.ts (Tribunal).
 *
 * @mixin TribunalData
 */
class TribunalResource extends JsonResource
{
    /**
     * @return array{sigla: string, nome: string, categoria: string}
     */
    public function toArray(Request $request): array
    {
        /** @var TribunalData $t */
        $t = $this->resource;

        return ['sigla' => $t->sigla, 'nome' => $t->nome, 'categoria' => CategoriaTribunal::rotulo($t->sigla)];
    }
}
