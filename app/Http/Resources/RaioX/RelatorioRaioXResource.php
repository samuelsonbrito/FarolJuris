<?php

namespace App\Http\Resources\RaioX;

use App\Models\RaioX\RelatorioRaioX;
use App\Services\RaioX\ProgressoRaioX;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Espelhado em resources/js/types/raiox.ts (RelatorioRaioX).
 *
 * @mixin RelatorioRaioX
 */
class RelatorioRaioXResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var RelatorioRaioX $r */
        $r = $this->resource;
        $admin = (bool) $request->user()?->is_admin;

        return [
            'id' => $r->id,
            'oab' => $r->oab,
            'uf' => $r->uf,
            'nomeAdvogado' => $r->nome_advogado,
            'inicio' => $r->inicio->toDateString(),
            'fim' => $r->fim->toDateString(),
            'status' => $r->status->value,
            'statusRotulo' => $r->status->rotulo(),
            'etapa' => $r->etapa,
            'publicacoes' => $r->publicacoes_total,
            'processos' => $r->processos_total,
            'custoMensal' => $r->custo_mensal !== null ? (float) $r->custo_mensal : null,
            'custoVariavel' => $r->custo_variavel !== null ? (float) $r->custo_variavel : null,
            'honorarioMedio' => $r->honorario_medio !== null ? (float) $r->honorario_medio : null,
            'solicitadoEm' => $r->created_at->toIso8601String(),
            'geradoEm' => $r->gerado_em?->toIso8601String(),
            'liberadoEm' => $r->liberado_em?->toIso8601String(),
            // Barra de progresso enquanto processa (nulo nos demais status).
            'progresso' => app(ProgressoRaioX::class)->de($r),
            'duracaoSegundos' => $r->iniciado_em && $r->gerado_em ? (int) $r->iniciado_em->diffInSeconds($r->gerado_em) : null,
            'usuario' => $this->when($admin, fn () => ['id' => $r->user->id, 'nome' => $r->user->name, 'email' => $r->user->email]),
            'pendentes' => $this->when($admin, fn () => $r->processos()->where('precisa_conferencia', true)->count()),
            'erro' => $this->when($admin, fn () => $r->erro),
            'ia' => $this->when($admin, fn () => ['modelo' => $r->ia_modelo, 'tokensEntrada' => $r->ia_tokens_entrada, 'tokensSaida' => $r->ia_tokens_saida]),
        ];
    }
}
