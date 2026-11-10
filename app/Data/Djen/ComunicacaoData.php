<?php

namespace App\Data\Djen;

use Carbon\CarbonImmutable;

/**
 * Comunicação processual no formato do app.
 *
 * Os campos seguem o que a interface precisa exibir. A tradução a partir da
 * resposta da Comunica API fica em ComunicacaoApiMapper; nada aqui assume
 * nomes de campos da API.
 */
final readonly class ComunicacaoData
{
    /**
     * @param  string  $hash  Identificador único usado para deduplicação.
     * @param  list<DestinatarioData>  $destinatarios
     * @param  list<AdvogadoData>  $advogados
     */
    public function __construct(
        public string $hash,
        public string $tribunal,
        public ?string $orgaoJulgador,
        public ?string $tipoComunicacao,
        public ?string $tipoDocumento,
        public CarbonImmutable $dataDisponibilizacao,
        public string $numeroProcesso,
        public array $destinatarios,
        public array $advogados,
        public string $textoHtml,
        public ?string $linkDocumento = null,
        /** Identificador da comunicação na Comunica API (`id`). */
        public ?int $idCnj = null,
        public ?string $numeroComunicacao = null,
        /** `D` (Diário de Justiça Eletrônico Nacional) ou `E` (Plataforma Nacional de Editais). */
        public ?string $meio = null,
        public ?string $meioCompleto = null,
        public ?string $classe = null,
        public ?string $codigoClasse = null,
    ) {}

    /** Texto puro, sem HTML e com espaços normalizados. */
    public function textoPuro(): string
    {
        // strip_tags mantém o conteúdo de <script>/<style>: remove esses blocos inteiros antes.
        $html = (string) preg_replace('#<(script|style|noscript|template)\b[^>]*>.*?</\1\s*>#is', ' ', $this->textoHtml);
        // Quebras e fins de bloco viram espaço (senão células de tabela vizinhas grudam).
        $html = (string) preg_replace('#<br\s*/?>|</(p|div|li|td|th|tr|table|h[1-6]|section|article|header|footer|center|blockquote|pre)\s*>#i', ' ', $html);
        $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    public function trecho(int $limite = 280): string
    {
        return mb_strimwidth($this->textoPuro(), 0, $limite, '…');
    }

    /** Número do processo formatado no padrão CNJ, quando tiver 20 dígitos. */
    public function numeroProcessoFormatado(): string
    {
        $digitos = preg_replace('/\D/', '', $this->numeroProcesso) ?? '';

        if (strlen($digitos) !== 20) {
            return $this->numeroProcesso;
        }

        return sprintf(
            '%s-%s.%s.%s.%s.%s',
            substr($digitos, 0, 7),
            substr($digitos, 7, 2),
            substr($digitos, 9, 4),
            substr($digitos, 13, 1),
            substr($digitos, 14, 2),
            substr($digitos, 16, 4),
        );
    }
}
