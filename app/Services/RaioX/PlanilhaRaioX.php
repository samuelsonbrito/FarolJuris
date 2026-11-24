<?php

namespace App\Services\RaioX;

use App\Enums\RaioX\ResultadoDecisao;
use App\Models\RaioX\ProcessoRaioX;
use App\Models\RaioX\RelatorioRaioX;
use App\Support\CapitaisUf;
use App\Support\NumeroCnj;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Planilha do Raio-X: processo a processo e as mesmas tabelas do relatório, uma por aba. */
final class PlanilhaRaioX implements Export, WithMultipleSheets
{
    /** @param  array<string, mixed>  $metricas  MetricasRaioX::calcular() */
    public function __construct(
        private readonly RelatorioRaioX $relatorio,
        private readonly array $metricas,
    ) {}

    /** @return list<AbaRaioX> */
    public function sheets(): array
    {
        $capital = CapitaisUf::capital($this->relatorio->uf);
        $estado = CapitaisUf::estado($this->relatorio->uf);
        $grupo = ['Grupo', 'Decididos', 'Êxito', 'Parcial', 'Acordo', 'Insucesso', 'Taxa de êxito'];
        $linhas = fn (array $ls) => array_map(fn (array $l) => [
            $l['rotulo'], $l['decididos'], $l['exito'], $l['parcial'], $l['acordo'], $l['insucesso'],
            $l['taxa'] !== null ? round($l['taxa'] * 100).'%' : '—',
        ], $ls);

        $varas = [
            ...array_map(fn ($l) => ['Vara cível', ...$l], $linhas($this->metricas['varasCapital']['civeis'])),
            ...array_map(fn ($l) => ['Juizado', ...$l], $linhas($this->metricas['varasCapital']['juizados'])),
        ];

        return [
            new AbaRaioX('Processos', [
                'Processo', 'Tribunal', 'Publicações', 'Última publicação', 'Resultado', 'Instância', 'Órgão que julgou',
                'Vara de origem', 'Grupo de origem', 'Tema', 'Parte contrária', 'Cliente no polo', 'Data da decisão',
                'Extinção sem mérito', 'Confiança da IA', 'Situação', 'Justificativa',
            ], $this->relatorio->processos()->orderByDesc('data_decisao')->get()->map(fn (ProcessoRaioX $p) => [
                strlen($p->numero_processo) === 20 ? NumeroCnj::mascara($p->numero_processo) : $p->numero_processo,
                $p->tribunal,
                $p->publicacoes,
                $p->ultima_publicacao?->format('d/m/Y'),
                $p->resultado?->rotulo() ?? 'Não classificado',
                $p->instancia?->rotulo(),
                $p->orgao_julgador,
                $p->vara_origem,
                $p->grupo_origem?->rotulo($capital, $estado),
                $p->tema?->rotulo(),
                $p->parte_contraria,
                $p->polo_cliente,
                $p->data_decisao?->format('d/m/Y'),
                $p->extincao_sem_merito ? 'Sim' : 'Não',
                $p->confianca?->value,
                $p->precisa_conferencia ? 'Em conferência' : ($p->conferido_em ? 'Conferido' : ($p->resultado === ResultadoDecisao::SemDecisao ? 'Sem decisão no período' : 'Classificado')),
                $p->justificativa,
            ])->all()),
            new AbaRaioX('Instância', $grupo, $linhas($this->metricas['porInstancia'])),
            new AbaRaioX('Por vara de origem', $grupo, $linhas($this->metricas['porOrigem'])),
            new AbaRaioX('Por vara ('.($capital ?? 'capital').')', ['Tipo', ...$grupo], $varas),
            new AbaRaioX('Por colegiado', $grupo, $linhas($this->metricas['porColegiado'])),
            new AbaRaioX('Por tema', $grupo, $linhas($this->metricas['porTema'])),
            new AbaRaioX('Por parte contrária', $grupo, $linhas($this->metricas['porParte'])),
            new AbaRaioX('Custo por processo', ['Indicador', 'Valor', 'Como se calcula'], $this->custo()),
        ];
    }

    /** @return list<list<string|int|float|null>> */
    private function custo(): array
    {
        $c = $this->metricas['custo'];

        return [
            ['Processos ativos', $c['ativos'], 'Processos com alguma publicação no DJEN no período'],
            ['Processos com êxito', $c['exitos'], 'Êxito + êxito parcial'],
            ['Processos por êxito', $c['processosPorExito'], 'Processos ativos ÷ processos com êxito'],
            ['Custo fixo mensal', $c['custoMensal'], 'Informado pelo escritório'],
            ['Custo variável por processo/ano', $c['custoVariavel'], 'Informado pelo escritório'],
            ['Custo total no ano', $c['custoTotal'], 'Custo fixo mensal × 12 + custo variável × processos ativos'],
            ['Custo por processo ativo', $c['custoPorProcesso'], 'Custo total ÷ processos ativos'],
            ['Honorário mínimo por êxito', $c['honorarioMinimo'], 'Custo total ÷ processos com êxito. Abaixo disso, o escritório perde dinheiro'],
            ['Honorário médio por êxito', $c['honorarioMedio'], 'Informado pelo escritório'],
            ['Margem por êxito', $c['margem'], 'Honorário médio – honorário mínimo'],
        ];
    }
}
