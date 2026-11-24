<?php

namespace App\Models\RaioX;

use App\Enums\RaioX\Confianca;
use App\Enums\RaioX\GrupoOrigem;
use App\Enums\RaioX\InstanciaDecisao;
use App\Enums\RaioX\ResultadoDecisao;
use App\Enums\RaioX\TemaRaioX;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um processo da carteira no Raio-X, com a classificação da decisão mais recente do período.
 *
 * @property int $id
 * @property int $relatorio_id
 * @property string $numero_processo
 * @property string|null $tribunal
 * @property int $publicacoes
 * @property CarbonInterface|null $ultima_publicacao
 * @property bool $candidato
 * @property ResultadoDecisao|null $resultado
 * @property bool $extincao_sem_merito
 * @property InstanciaDecisao|null $instancia
 * @property string|null $orgao_julgador
 * @property string|null $vara_origem
 * @property GrupoOrigem|null $grupo_origem
 * @property TemaRaioX|null $tema
 * @property string|null $parte_contraria
 * @property string|null $polo_cliente
 * @property CarbonInterface|null $data_decisao
 * @property Confianca|null $confianca
 * @property string|null $justificativa
 * @property string|null $hash_decisao
 * @property string|null $texto_decisao
 * @property bool $precisa_conferencia
 * @property CarbonInterface|null $conferido_em
 * @property int|null $conferido_por
 * @property-read RelatorioRaioX $relatorio
 */
class ProcessoRaioX extends Model
{
    protected $table = 'raiox_processos';

    protected $fillable = [
        'numero_processo', 'tribunal', 'publicacoes', 'ultima_publicacao', 'candidato', 'resultado',
        'extincao_sem_merito', 'instancia', 'orgao_julgador', 'vara_origem', 'grupo_origem', 'tema',
        'parte_contraria', 'polo_cliente', 'data_decisao', 'confianca', 'justificativa', 'hash_decisao',
        'texto_decisao', 'precisa_conferencia', 'conferido_em', 'conferido_por',
    ];

    protected $attributes = ['publicacoes' => 0, 'candidato' => false, 'extincao_sem_merito' => false, 'precisa_conferencia' => false];

    protected function casts(): array
    {
        return [
            'ultima_publicacao' => 'date',
            'data_decisao' => 'date',
            'candidato' => 'boolean',
            'extincao_sem_merito' => 'boolean',
            'precisa_conferencia' => 'boolean',
            'conferido_em' => 'datetime',
            'resultado' => ResultadoDecisao::class,
            'instancia' => InstanciaDecisao::class,
            'grupo_origem' => GrupoOrigem::class,
            'tema' => TemaRaioX::class,
            'confianca' => Confianca::class,
        ];
    }

    /** @return BelongsTo<RelatorioRaioX, $this> */
    public function relatorio(): BelongsTo
    {
        return $this->belongsTo(RelatorioRaioX::class, 'relatorio_id');
    }

    /** @return BelongsTo<User, $this> */
    public function conferente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conferido_por');
    }

    public function decidido(): bool
    {
        return $this->resultado !== null && $this->resultado->decidido() && ! $this->precisa_conferencia;
    }
}
