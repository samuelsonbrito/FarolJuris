<?php

namespace App\Models\IA;

use App\Models\RaioX\RelatorioRaioX;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma chamada à IA, com tokens e custo (dólar e real, pela cotação do momento).
 *
 * @property int $id
 * @property int|null $relatorio_id
 * @property string $finalidade
 * @property string $modelo
 * @property int $tokens_entrada
 * @property int $tokens_cache_leitura
 * @property int $tokens_cache_escrita
 * @property int $tokens_saida
 * @property string|null $custo_usd
 * @property string $cotacao_usd_brl
 * @property string|null $custo_brl
 * @property string $resultado
 * @property int|null $duracao_ms
 * @property CarbonInterface $created_at
 */
class ChamadaIA extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ia_chamadas';

    protected $fillable = [
        'relatorio_id', 'finalidade', 'modelo', 'tokens_entrada', 'tokens_cache_leitura', 'tokens_cache_escrita',
        'tokens_saida', 'custo_usd', 'cotacao_usd_brl', 'custo_brl', 'resultado', 'duracao_ms',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<RelatorioRaioX, $this> */
    public function relatorio(): BelongsTo
    {
        return $this->belongsTo(RelatorioRaioX::class, 'relatorio_id');
    }
}
