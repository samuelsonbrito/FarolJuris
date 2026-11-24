<?php

namespace App\Models\RaioX;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Publicação do DJEN coletada para um Raio-X.
 *
 * @property int $id
 * @property int $relatorio_id
 * @property string $hash
 * @property string $numero_processo
 * @property CarbonInterface $data_disponibilizacao
 * @property string|null $tribunal
 * @property string|null $orgao
 * @property string|null $tipo_comunicacao
 * @property string|null $tipo_documento
 * @property string|null $classe
 * @property list<array{nome: string, polo: string|null}>|null $destinatarios
 * @property list<array{nome: string, numeroOab: string|null, ufOab: string|null}>|null $advogados
 * @property string $texto
 */
class PublicacaoRaioX extends Model
{
    protected $table = 'raiox_publicacoes';

    protected $fillable = [
        'hash', 'numero_processo', 'data_disponibilizacao', 'tribunal', 'orgao', 'tipo_comunicacao',
        'tipo_documento', 'classe', 'destinatarios', 'advogados', 'texto',
    ];

    protected function casts(): array
    {
        return [
            'data_disponibilizacao' => 'date',
            'destinatarios' => 'array',
            'advogados' => 'array',
        ];
    }

    /** @return BelongsTo<RelatorioRaioX, $this> */
    public function relatorio(): BelongsTo
    {
        return $this->belongsTo(RelatorioRaioX::class, 'relatorio_id');
    }
}
