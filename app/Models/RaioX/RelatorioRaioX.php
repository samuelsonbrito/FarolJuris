<?php

namespace App\Models\RaioX;

use App\Enums\RaioX\StatusRaioX;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $oab
 * @property string $uf
 * @property string|null $nome_advogado
 * @property CarbonInterface $inicio
 * @property CarbonInterface $fim
 * @property StatusRaioX $status
 * @property string|null $etapa
 * @property int $publicacoes_total
 * @property int $processos_total
 * @property string|null $custo_mensal
 * @property string|null $custo_variavel
 * @property string|null $honorario_medio
 * @property string|null $ia_modelo
 * @property int $ia_tokens_entrada
 * @property int $ia_tokens_saida
 * @property string|null $erro
 * @property CarbonInterface|null $iniciado_em
 * @property CarbonInterface|null $coleta_concluida_em
 * @property int $paginas_total
 * @property int $paginas_coletadas
 * @property int $classificar_total
 * @property int $classificados
 * @property CarbonInterface|null $gerado_em
 * @property CarbonInterface|null $liberado_em
 * @property int|null $liberado_por
 * @property CarbonInterface $created_at
 * @property-read User $user
 */
class RelatorioRaioX extends Model
{
    protected $table = 'raiox_relatorios';

    protected $fillable = [
        'oab', 'uf', 'nome_advogado', 'inicio', 'fim', 'status', 'etapa', 'publicacoes_total', 'processos_total',
        'custo_mensal', 'custo_variavel', 'honorario_medio', 'ia_modelo', 'ia_tokens_entrada', 'ia_tokens_saida',
        'erro', 'gerado_em', 'liberado_em', 'liberado_por', 'iniciado_em', 'coleta_concluida_em',
        'paginas_total', 'paginas_coletadas', 'classificar_total', 'classificados',
    ];

    protected $attributes = ['status' => 'solicitado', 'publicacoes_total' => 0, 'processos_total' => 0, 'ia_tokens_entrada' => 0, 'ia_tokens_saida' => 0, 'paginas_total' => 0, 'paginas_coletadas' => 0, 'classificar_total' => 0, 'classificados' => 0];

    protected function casts(): array
    {
        return [
            'status' => StatusRaioX::class,
            'inicio' => 'date',
            'fim' => 'date',
            'gerado_em' => 'datetime',
            'iniciado_em' => 'datetime',
            'coleta_concluida_em' => 'datetime',
            'liberado_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ProcessoRaioX, $this> */
    public function processos(): HasMany
    {
        return $this->hasMany(ProcessoRaioX::class, 'relatorio_id');
    }

    /** @return HasMany<PublicacaoRaioX, $this> */
    public function publicacoes(): HasMany
    {
        return $this->hasMany(PublicacaoRaioX::class, 'relatorio_id');
    }

    public function liberado(): bool
    {
        return $this->status === StatusRaioX::Liberado;
    }

    /** Pode (re)gerar: não está rodando. */
    public function podeGerar(): bool
    {
        return $this->status !== StatusRaioX::Processando;
    }

    /** Pode liberar: classificado e sem pendência de conferência. */
    public function podeLiberar(): bool
    {
        return $this->status === StatusRaioX::Pronto;
    }

    public function descricaoOab(): string
    {
        return "OAB {$this->oab}/{$this->uf}";
    }
}
