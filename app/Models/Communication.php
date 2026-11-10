<?php

namespace App\Models;

use App\Data\Djen\AdvogadoData;
use App\Data\Djen\ComunicacaoData;
use App\Data\Djen\DestinatarioData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $hash
 * @property string $tribunal
 * @property string|null $orgao_julgador
 * @property string|null $tipo_comunicacao
 * @property string|null $tipo_documento
 * @property Carbon $data_disponibilizacao
 * @property string $numero_processo
 * @property list<array{nome: string, polo?: string|null}> $destinatarios
 * @property list<array{nome: string, numeroOab: string|null, ufOab: string|null}> $advogados
 * @property string $texto_html
 * @property string|null $link_documento
 * @property int|null $cnj_id
 * @property string|null $numero_comunicacao
 * @property string|null $meio
 * @property string|null $meio_completo
 * @property string|null $classe
 * @property string|null $codigo_classe
 */
class Communication extends Model
{
    use Prunable;

    protected $fillable = [
        'hash', 'tribunal', 'orgao_julgador', 'tipo_comunicacao', 'tipo_documento',
        'data_disponibilizacao', 'numero_processo', 'destinatarios', 'advogados',
        'texto_html', 'link_documento',
        'cnj_id', 'numero_comunicacao', 'meio', 'meio_completo', 'classe', 'codigo_classe',
    ];

    protected function casts(): array
    {
        return [
            'data_disponibilizacao' => 'date',
            'destinatarios' => 'array',
            'advogados' => 'array',
        ];
    }

    /** @return BelongsToMany<MonitoringTarget, $this, Alerta> */
    public function targets(): BelongsToMany
    {
        return $this->belongsToMany(MonitoringTarget::class, 'communication_target')
            ->using(Alerta::class)
            ->withPivot(['id', 'lida_em', 'notificada_em'])
            ->withTimestamps();
    }

    /**
     * LGPD: comunicações que nenhum alvo referencia mais.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::query()->whereDoesntHave('targets');
    }

    /** Apaga agora as comunicações órfãs (após exclusão de alvos). */
    public static function apagarOrfas(): int
    {
        return (new static)->prunable()->delete();
    }

    /** @return array<string, mixed> */
    public static function atributosDe(ComunicacaoData $c): array
    {
        return [
            'hash' => $c->hash,
            'tribunal' => $c->tribunal,
            'orgao_julgador' => $c->orgaoJulgador,
            'tipo_comunicacao' => $c->tipoComunicacao,
            'tipo_documento' => $c->tipoDocumento,
            'data_disponibilizacao' => $c->dataDisponibilizacao->toDateString(),
            'numero_processo' => $c->numeroProcesso,
            'destinatarios' => array_map(fn (DestinatarioData $d): array => ['nome' => $d->nome, 'polo' => $d->polo], $c->destinatarios),
            'advogados' => array_map(fn (AdvogadoData $a): array => [
                'nome' => $a->nome, 'numeroOab' => $a->numeroOab, 'ufOab' => $a->ufOab,
            ], $c->advogados),
            'texto_html' => $c->textoHtml,
            'link_documento' => $c->linkDocumento,
            'cnj_id' => $c->idCnj,
            'numero_comunicacao' => $c->numeroComunicacao,
            'meio' => $c->meio,
            'meio_completo' => $c->meioCompleto,
            'classe' => $c->classe,
            'codigo_classe' => $c->codigoClasse,
        ];
    }

    public function toData(): ComunicacaoData
    {
        return new ComunicacaoData(
            hash: $this->hash,
            tribunal: $this->tribunal,
            orgaoJulgador: $this->orgao_julgador,
            tipoComunicacao: $this->tipo_comunicacao,
            tipoDocumento: $this->tipo_documento,
            dataDisponibilizacao: CarbonImmutable::parse($this->data_disponibilizacao->toDateString()),
            numeroProcesso: $this->numero_processo,
            destinatarios: array_map(fn (array $d): DestinatarioData => new DestinatarioData($d['nome'], $d['polo'] ?? null), $this->destinatarios),
            advogados: array_map(fn (array $a): AdvogadoData => new AdvogadoData($a['nome'], $a['numeroOab'] ?? null, $a['ufOab'] ?? null), $this->advogados),
            textoHtml: $this->texto_html,
            linkDocumento: $this->link_documento,
            idCnj: $this->cnj_id,
            numeroComunicacao: $this->numero_comunicacao,
            meio: $this->meio,
            meioCompleto: $this->meio_completo,
            classe: $this->classe,
            codigoClasse: $this->codigo_classe,
        );
    }
}
