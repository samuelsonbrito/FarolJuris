<?php

namespace App\Services\Djen\Mapping;

use App\Data\Djen\AdvogadoData;
use App\Data\Djen\ComunicacaoData;
use App\Data\Djen\DestinatarioData;
use App\Data\Djen\ResultadoConsulta;
use App\Services\Djen\Exceptions\DjenUnexpectedResponseException;
use Carbon\CarbonImmutable;

/**
 * Traduz a resposta de GET /comunicacao (Comunica API) para os DTOs do app.
 *
 * Campos confirmados numa resposta real (tests/Fixtures/djen/README.md):
 * envelope {status, message, count, items[]}; item com hash, siglaTribunal,
 * nomeOrgao, tipoComunicacao, tipoDocumento, data_disponibilizacao (Y-m-d),
 * numero_processo (20 dígitos), texto (texto puro ou HTML), link (ou null),
 * destinatarios[{nome, polo}] e destinatarioadvogados[{advogado: {id, nome,
 * numero_oab, uf_oab}}].
 */
final class ComunicacaoApiMapper
{
    /**
     * @param  array<array-key, mixed>  $json
     *
     * @throws DjenUnexpectedResponseException
     */
    public function resultado(array $json, int $pagina, int $porPagina, bool $doCache = false): ResultadoConsulta
    {
        if (! isset($json['items']) || ! is_array($json['items'])) {
            throw new DjenUnexpectedResponseException('A API do CNJ respondeu sem a lista de comunicações (items).');
        }

        $comunicacoes = [];

        foreach ($json['items'] as $item) {
            if (is_array($item) && is_string($item['hash'] ?? null) && $item['hash'] !== '') {
                $comunicacoes[] = $this->comunicacao($item);
            }
        }

        $total = is_int($json['count'] ?? null) ? $json['count'] : count($comunicacoes);

        return new ResultadoConsulta($comunicacoes, $total, $pagina, $porPagina, $doCache);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function comunicacao(array $item): ComunicacaoData
    {
        return new ComunicacaoData(
            hash: (string) $item['hash'],
            tribunal: self::texto($item['siglaTribunal'] ?? null) ?? '—',
            orgaoJulgador: self::texto($item['nomeOrgao'] ?? null),
            tipoComunicacao: self::texto($item['tipoComunicacao'] ?? null),
            tipoDocumento: self::texto($item['tipoDocumento'] ?? null),
            dataDisponibilizacao: self::data($item['data_disponibilizacao'] ?? null),
            numeroProcesso: preg_replace('/\D/', '', (string) ($item['numero_processo'] ?? '')) ?: (string) ($item['numeroprocessocommascara'] ?? ''),
            destinatarios: self::destinatarios($item['destinatarios'] ?? []),
            advogados: self::advogados($item['destinatarioadvogados'] ?? []),
            textoHtml: self::html((string) ($item['texto'] ?? '')),
            linkDocumento: self::texto($item['link'] ?? null),
            idCnj: is_int($item['id'] ?? null) ? $item['id'] : null,
            numeroComunicacao: is_scalar($item['numeroComunicacao'] ?? null) ? (string) $item['numeroComunicacao'] : null,
            meio: self::texto($item['meio'] ?? null),
            meioCompleto: self::texto($item['meiocompleto'] ?? null),
            // A API devolve a classe com a caixa quebrada ("APELAçãO"): tudo em maiúsculas resolve.
            classe: ($classe = self::texto($item['nomeClasse'] ?? null)) !== null ? mb_strtoupper($classe) : null,
            codigoClasse: is_scalar($item['codigoClasse'] ?? null) && (string) $item['codigoClasse'] !== '' ? (string) $item['codigoClasse'] : null,
        );
    }

    private static function texto(mixed $valor): ?string
    {
        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }

    private static function data(mixed $valor): CarbonImmutable
    {
        $data = is_string($valor) ? CarbonImmutable::createFromFormat('!Y-m-d', $valor) : false;

        if ($data === false || $data === null) {
            throw new DjenUnexpectedResponseException('Data de disponibilização inválida na resposta do CNJ: '.json_encode($valor));
        }

        return $data;
    }

    /**
     * A API repete destinatários na mesma comunicação: mantém um de cada.
     *
     * @return list<DestinatarioData>
     */
    private static function destinatarios(mixed $lista): array
    {
        $vistos = [];

        foreach (is_array($lista) ? $lista : [] as $d) {
            $nome = self::texto($d['nome'] ?? null);

            if ($nome !== null) {
                $vistos[mb_strtoupper($nome)] ??= new DestinatarioData($nome, self::texto($d['polo'] ?? null));
            }
        }

        return array_values($vistos);
    }

    /**
     * A API repete o mesmo advogado (mesmo advogado.id) em vínculos diferentes.
     * O número da OAB pode ter letras e hífen (ex.: 11811N, 20413-O).
     *
     * @return list<AdvogadoData>
     */
    private static function advogados(mixed $lista): array
    {
        $vistos = [];

        foreach (is_array($lista) ? $lista : [] as $vinculo) {
            $a = is_array($vinculo['advogado'] ?? null) ? $vinculo['advogado'] : null;
            $nome = self::texto($a['nome'] ?? null);

            if ($nome === null) {
                continue;
            }

            $chave = $a['id'] ?? mb_strtoupper($nome).'|'.($a['numero_oab'] ?? '');
            $vistos[$chave] ??= new AdvogadoData($nome, self::texto($a['numero_oab'] ?? null), self::texto($a['uf_oab'] ?? null));
        }

        return array_values($vistos);
    }

    /**
     * `texto` vem como HTML (fragmento ou documento inteiro) ou como texto
     * puro com quebras de linha e, às vezes, entidades HTML (&nbsp;, &Aacute;).
     * Texto puro vira HTML seguro preservando as quebras; HTML segue para o
     * HtmlSanitizer na exibição.
     */
    private static function html(string $texto): string
    {
        // Tag de verdade: "<" colado ao nome (ou </, <!, <?). Evita tratar "x < y" como HTML.
        if (preg_match('/<(?:[a-z][a-z0-9]*(?:\s[^<>]*)?\/?|\/[a-z][a-z0-9]*\s*|!--|!doctype\b[^<>]*|\?xml\b[^<>]*)>/i', $texto)) {
            return $texto;
        }

        $decodificado = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return nl2br(e(trim($decodificado)), false);
    }
}
