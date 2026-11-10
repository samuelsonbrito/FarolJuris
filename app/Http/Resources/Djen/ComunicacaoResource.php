<?php

namespace App\Http\Resources\Djen;

use App\Data\Djen\AdvogadoData;
use App\Data\Djen\ComunicacaoData;
use App\Data\Djen\DestinatarioData;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Espelhado em resources/js/types/djen.ts (Comunicacao).
 *
 * @mixin ComunicacaoData
 */
class ComunicacaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ComunicacaoData $c */
        $c = $this->resource;

        return [
            'hash' => $c->hash,
            'id' => $c->idCnj,
            'numeroComunicacao' => $c->numeroComunicacao,
            'meio' => $c->meio,
            'meioCompleto' => $c->meioCompleto,
            'classe' => $c->classe,
            'codigoClasse' => $c->codigoClasse,
            'numeroProcessoDigitos' => preg_replace('/\D/', '', $c->numeroProcesso),
            'tribunal' => $c->tribunal,
            'orgaoJulgador' => $c->orgaoJulgador,
            'tipoComunicacao' => $c->tipoComunicacao,
            'tipoDocumento' => $c->tipoDocumento,
            'dataDisponibilizacao' => $c->dataDisponibilizacao->toDateString(),
            'numeroProcesso' => $c->numeroProcessoFormatado(),
            'destinatarios' => array_map(fn (DestinatarioData $d): array => ['nome' => $d->nome, 'polo' => $d->polo], $c->destinatarios),
            'advogados' => array_map(fn (AdvogadoData $a): array => [
                'nome' => $a->nome,
                'numeroOab' => $a->numeroOab,
                'ufOab' => $a->ufOab,
            ], $c->advogados),
            'trecho' => $c->trecho(),
            'textoHtml' => app(HtmlSanitizer::class)->sanitize($c->textoHtml),
            'linkDocumento' => self::linkSeguro($c->linkDocumento),
        ];
    }

    /** Só aceita http(s): o link vira href no front. */
    private static function linkSeguro(?string $url): ?string
    {
        if ($url === null || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
    }
}
