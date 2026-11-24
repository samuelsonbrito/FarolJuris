<?php

namespace App\Services\RaioX;

use App\Enums\RaioX\GrupoOrigem;
use App\Enums\RaioX\InstanciaDecisao;
use App\Enums\RaioX\ResultadoDecisao;
use App\Models\RaioX\ProcessoRaioX;
use App\Models\RaioX\RelatorioRaioX;
use App\Support\CapitaisUf;
use Illuminate\Support\Collection;

/**
 * Consolida a carteira de um Raio-X. Cada processo conta uma vez, pela decisão mais recente do
 * período, lida do ponto de vista do cliente. Taxa de êxito = (êxito + parcial) ÷ decididos.
 * Casos em conferência ficam fora dos decididos até alguém conferir.
 */
final class MetricasRaioX
{
    /** @return array<string, mixed> */
    public function calcular(RelatorioRaioX $relatorio): array
    {
        /** @var Collection<int, ProcessoRaioX> $processos */
        $processos = $relatorio->processos()->get();
        $decididos = $processos->filter(fn (ProcessoRaioX $p) => $p->decidido())->values();
        $capital = CapitaisUf::capital($relatorio->uf);
        $estado = CapitaisUf::estado($relatorio->uf);

        $contagem = $this->contagem($decididos);

        return [
            'visaoGeral' => [
                ...$contagem,
                'pendentes' => $processos->where('precisa_conferencia', true)->count(),
                'extincoes' => $decididos->where('extincao_sem_merito', true)->count(),
                'ativos' => $processos->count(),
                'semDecisao' => $processos->filter(fn (ProcessoRaioX $p) => ! $p->precisa_conferencia && $p->resultado === ResultadoDecisao::SemDecisao)->count(),
                'publicacoes' => $relatorio->publicacoes_total,
            ],
            'porInstancia' => $this->porInstancia($decididos),
            'porOrigem' => $this->porOrigem($decididos, $capital, $estado),
            'porColegiado' => $this->porColegiado($decididos),
            'varasCapital' => [
                'civeis' => $this->porVara($decididos->where('grupo_origem', GrupoOrigem::CivelCapital)),
                'juizados' => $this->porVara($decididos->where('grupo_origem', GrupoOrigem::JuizadoCapital)),
                'capital' => $capital,
            ],
            'porTema' => $this->agrupar($decididos, fn (ProcessoRaioX $p) => $p->tema?->rotulo() ?? 'Outros'),
            'porParte' => $this->porParte($decididos),
            'custo' => $this->custo($relatorio, $processos->count(), $contagem['exito'] + $contagem['parcial']),
            'destaques' => $this->destaques($decididos, $capital, $estado),
            'conferencia' => $processos->where('precisa_conferencia', true)->pluck('numero_processo')->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, ProcessoRaioX>  $ps
     * @return array{decididos: int, exito: int, parcial: int, acordo: int, insucesso: int, favoraveis: int, taxa: float|null}
     */
    private function contagem(Collection $ps): array
    {
        $exito = $ps->where('resultado', ResultadoDecisao::Exito)->count();
        $parcial = $ps->where('resultado', ResultadoDecisao::ExitoParcial)->count();
        $total = $ps->count();

        return [
            'decididos' => $total,
            'exito' => $exito,
            'parcial' => $parcial,
            'acordo' => $ps->where('resultado', ResultadoDecisao::Acordo)->count(),
            'insucesso' => $ps->where('resultado', ResultadoDecisao::Insucesso)->count(),
            'favoraveis' => $exito + $parcial,
            'taxa' => $total > 0 ? round(($exito + $parcial) / $total, 4) : null,
        ];
    }

    /**
     * @param  Collection<int, ProcessoRaioX>  $ps
     * @param  callable(ProcessoRaioX): string  $chave
     * @return list<array<string, mixed>>
     */
    private function agrupar(Collection $ps, callable $chave): array
    {
        return $ps->groupBy($chave)
            ->map(fn (Collection $g, string $rotulo) => ['rotulo' => $rotulo, ...$this->contagem($g)])
            ->sortByDesc('decididos')
            ->values()
            ->all();
    }

    /** @param  Collection<int, ProcessoRaioX>  $ps */
    private function porInstancia(Collection $ps): array
    {
        $linhas = [];

        foreach (InstanciaDecisao::cases() as $instancia) {
            $grupo = $ps->where('instancia', $instancia);

            if ($instancia === InstanciaDecisao::Outra && $grupo->isEmpty()) {
                continue;
            }

            $linhas[] = ['chave' => $instancia->value, 'rotulo' => $instancia->rotulo(), ...$this->contagem($grupo)];
        }

        return $linhas;
    }

    /** @param  Collection<int, ProcessoRaioX>  $ps */
    private function porOrigem(Collection $ps, ?string $capital, ?string $estado): array
    {
        $linhas = [];

        foreach (GrupoOrigem::cases() as $grupo) {
            $g = $ps->filter(fn (ProcessoRaioX $p) => ($p->grupo_origem ?? GrupoOrigem::NaoIdentificada) === $grupo);

            if ($g->isNotEmpty()) {
                $linhas[] = ['chave' => $grupo->value, 'rotulo' => $grupo->rotulo($capital, $estado), ...$this->contagem($g)];
            }
        }

        return $linhas;
    }

    /** Quem julgou os recursos. Colegiados com 1 caso vão para "Outros colegiados". */
    private function porColegiado(Collection $ps): array
    {
        $recursos = $ps->filter(fn (ProcessoRaioX $p) => in_array($p->instancia, [InstanciaDecisao::RecursoCliente, InstanciaDecisao::RecursoContraria], true));
        $linhas = $this->agrupar($recursos, fn (ProcessoRaioX $p) => $p->orgao_julgador ?? 'Colegiado não identificado');

        return $this->agruparCauda($linhas, 8, 'Outros colegiados');
    }

    /** @param  Collection<int, ProcessoRaioX>  $ps */
    private function porVara(Collection $ps): array
    {
        $linhas = $this->agrupar($ps, fn (ProcessoRaioX $p) => $p->vara_origem ?? 'Vara não identificada');

        // Ordem natural do número da vara ("2ª Vara Cível" antes de "13ª Vara Cível").
        usort($linhas, fn (array $a, array $b) => strnatcasecmp($a['rotulo'], $b['rotulo']));

        return $linhas;
    }

    private function porParte(Collection $ps): array
    {
        $linhas = $this->agrupar($ps, fn (ProcessoRaioX $p) => $p->parte_contraria ? self::normalizarParte($p->parte_contraria) : 'Parte não identificada');

        return $this->agruparCauda($linhas, 10, 'Demais partes contrárias');
    }

    /** Junta grafias diferentes da mesma parte (caixa e espaços). */
    public static function normalizarParte(string $nome): string
    {
        $limpo = trim((string) preg_replace('/\s+/u', ' ', $nome));

        return mb_convert_case(mb_strtolower($limpo), MB_CASE_TITLE);
    }

    /**
     * Mantém as $limite primeiras linhas e soma o resto numa só.
     *
     * @param  list<array<string, mixed>>  $linhas
     * @return list<array<string, mixed>>
     */
    private function agruparCauda(array $linhas, int $limite, string $rotulo): array
    {
        if (count($linhas) <= $limite) {
            return $linhas;
        }

        $cauda = array_slice($linhas, $limite);
        $soma = ['rotulo' => $rotulo, 'decididos' => 0, 'exito' => 0, 'parcial' => 0, 'acordo' => 0, 'insucesso' => 0, 'favoraveis' => 0];

        foreach ($cauda as $l) {
            foreach (['decididos', 'exito', 'parcial', 'acordo', 'insucesso', 'favoraveis'] as $k) {
                $soma[$k] += $l[$k];
            }
        }

        $soma['taxa'] = $soma['decididos'] > 0 ? round($soma['favoraveis'] / $soma['decididos'], 4) : null;

        return [...array_slice($linhas, 0, $limite), $soma];
    }

    /**
     * Quanto cada êxito precisa pagar. O custo é informado pelo advogado (pode faltar).
     *
     * @return array<string, int|float|null>
     */
    public function custo(RelatorioRaioX $r, int $ativos, int $exitos): array
    {
        $mensal = $r->custo_mensal !== null ? (float) $r->custo_mensal : null;
        $variavel = $r->custo_variavel !== null ? (float) $r->custo_variavel : 0.0;
        $honorario = $r->honorario_medio !== null ? (float) $r->honorario_medio : null;

        $total = $mensal !== null ? $mensal * 12 + $variavel * $ativos : null;
        $minimo = $total !== null && $exitos > 0 ? $total / $exitos : null;

        return [
            'ativos' => $ativos,
            'exitos' => $exitos,
            'processosPorExito' => $exitos > 0 ? round($ativos / $exitos, 1) : null,
            'custoMensal' => $mensal,
            'custoVariavel' => $r->custo_variavel !== null ? $variavel : null,
            'honorarioMedio' => $honorario,
            'custoTotal' => $total !== null ? round($total, 2) : null,
            'custoPorProcesso' => $total !== null && $ativos > 0 ? round($total / $ativos, 2) : null,
            'honorarioMinimo' => $minimo !== null ? round($minimo, 2) : null,
            'margem' => $minimo !== null && $honorario !== null ? round($honorario - $minimo, 2) : null,
        ];
    }

    /**
     * "Onde vai melhor" e "onde vai pior": grupos (instância, tema, parte, origem) com amostra mínima.
     *
     * @return array{melhor: list<string>, pior: list<string>}
     */
    private function destaques(Collection $ps, ?string $capital, ?string $estado): array
    {
        $minimo = max(1, (int) config('raiox.minimo_para_destaque', 5));
        $candidatos = [];

        foreach ($this->porInstancia($ps) as $l) {
            $candidatos[] = $l;
        }

        foreach ([...$this->agrupar($ps, fn (ProcessoRaioX $p) => $p->tema?->rotulo() ?? 'Outros'), ...$this->porOrigem($ps, $capital, $estado)] as $l) {
            $candidatos[] = $l;
        }

        foreach ($this->agrupar($ps, fn (ProcessoRaioX $p) => $p->parte_contraria ? self::normalizarParte($p->parte_contraria) : '') as $l) {
            if ($l['rotulo'] !== '') {
                $candidatos[] = $l;
            }
        }

        $validos = array_values(array_filter($candidatos, fn (array $l) => $l['decididos'] >= $minimo && $l['taxa'] !== null));
        $texto = fn (array $l) => sprintf('%s: %d%% de êxito (%d de %d).', $l['rotulo'], (int) round($l['taxa'] * 100), $l['favoraveis'], $l['decididos']);

        $melhor = $validos;
        usort($melhor, fn ($a, $b) => [$b['taxa'], $b['decididos']] <=> [$a['taxa'], $a['decididos']]);
        $pior = $validos;
        usort($pior, fn ($a, $b) => [$a['taxa'], -$a['decididos']] <=> [$b['taxa'], -$b['decididos']]);

        return [
            'melhor' => array_map($texto, array_slice(array_filter($melhor, fn ($l) => $l['taxa'] >= 0.4), 0, 5)),
            'pior' => array_map($texto, array_slice(array_filter($pior, fn ($l) => $l['taxa'] < 0.4), 0, 5)),
        ];
    }
}
