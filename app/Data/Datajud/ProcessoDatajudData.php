<?php

namespace App\Data\Datajud;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Um processo no DataJud. O mesmo número pode aparecer mais de uma vez (um
 * registro por grau: G1, G2, JE...).
 *
 * Campos da API (resposta real do índice api_publica_tjam, 16/11/2026): id, tribunal,
 * grau, numeroProcesso, dataAjuizamento (YmdHis), nivelSigilo, orgaoJulgador{codigo, nome},
 * classe{codigo, nome}, sistema{codigo, nome}, formato{codigo, nome},
 * dataHoraUltimaAtualizacao (ISO), assuntos[{codigo, nome}] e movimentos[{codigo, nome,
 * dataHora, complementosTabelados[{codigo, descricao, valor, nome}], orgaoJulgador{codigo, nome}}].
 */
final readonly class ProcessoDatajudData
{
    /**
     * @param  list<string>  $assuntos
     * @param  list<MovimentoData>  $movimentos  Do mais recente para o mais antigo.
     */
    public function __construct(
        public string $tribunal,
        public ?string $grau,
        public string $numeroProcesso,
        public ?string $classe,
        public ?int $codigoClasse,
        public array $assuntos,
        public ?string $orgaoJulgador,
        public ?CarbonImmutable $dataAjuizamento,
        public ?string $sistema,
        public ?string $formato,
        public ?CarbonImmutable $atualizadoEm,
        public array $movimentos,
    ) {}

    /**
     * @param  array<string, mixed>  $s  O `_source` de um hit.
     */
    public static function fromApi(array $s): self
    {
        $movimentos = [];

        foreach (is_array($s['movimentos'] ?? null) ? $s['movimentos'] : [] as $m) {
            if (! is_array($m) || self::texto($m['nome'] ?? null) === null) {
                continue;
            }

            $complementos = [];

            foreach (is_array($m['complementosTabelados'] ?? null) ? $m['complementosTabelados'] : [] as $c) {
                if (is_array($c) && ($nome = self::texto($c['nome'] ?? null)) !== null) {
                    $complementos[] = $nome;
                }
            }

            $movimentos[] = new MovimentoData(
                dataHora: self::data($m['dataHora'] ?? null),
                codigo: is_numeric($m['codigo'] ?? null) ? (int) $m['codigo'] : null,
                nome: (string) self::texto($m['nome']),
                complementos: $complementos,
                orgaoJulgador: self::texto($m['orgaoJulgador']['nome'] ?? null),
            );
        }

        usort($movimentos, fn (MovimentoData $a, MovimentoData $b): int => ($b->dataHora?->getTimestamp() ?? 0) <=> ($a->dataHora?->getTimestamp() ?? 0));

        $assuntos = [];

        foreach (is_array($s['assuntos'] ?? null) ? $s['assuntos'] : [] as $a) {
            if (is_array($a) && ($nome = self::texto($a['nome'] ?? null)) !== null) {
                $assuntos[] = $nome;
            }
        }

        return new self(
            tribunal: (string) (self::texto($s['tribunal'] ?? null) ?? ''),
            grau: self::texto($s['grau'] ?? null),
            numeroProcesso: (string) (self::texto($s['numeroProcesso'] ?? null) ?? ''),
            classe: self::texto($s['classe']['nome'] ?? null),
            codigoClasse: is_numeric($s['classe']['codigo'] ?? null) ? (int) $s['classe']['codigo'] : null,
            assuntos: array_values(array_unique($assuntos)),
            orgaoJulgador: self::texto($s['orgaoJulgador']['nome'] ?? null),
            dataAjuizamento: self::data($s['dataAjuizamento'] ?? null),
            sistema: self::texto($s['sistema']['nome'] ?? null),
            formato: self::texto($s['formato']['nome'] ?? null),
            atualizadoEm: self::data($s['dataHoraUltimaAtualizacao'] ?? null),
            movimentos: $movimentos,
        );
    }

    private static function texto(mixed $v): ?string
    {
        if (! is_scalar($v)) {
            return null;
        }

        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    /** Aceita ISO 8601 (`2025-07-14T18:53:27.000Z`) e o compacto `YmdHis` de dataAjuizamento. */
    private static function data(mixed $v): ?CarbonImmutable
    {
        if (! is_string($v) || $v === '') {
            return null;
        }

        try {
            return preg_match('/^\d{14}$/', $v)
                ? CarbonImmutable::createFromFormat('YmdHis', $v, 'UTC')
                : CarbonImmutable::parse($v)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
