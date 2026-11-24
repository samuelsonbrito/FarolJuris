<?php

namespace App\Services\RaioX;

use App\Data\Djen\AdvogadoData;
use App\Data\Djen\ComunicacaoData;
use App\Data\Djen\DestinatarioData;
use App\Data\Djen\FiltroConsulta;
use App\Data\RaioX\ClassificacaoData;
use App\Data\RaioX\ContextoProcesso;
use App\Enums\RaioX\StatusRaioX;
use App\Jobs\RaioX\ClassificarProcessoRaioXJob;
use App\Jobs\RaioX\ColetarRaioXJob;
use App\Models\RaioX\ProcessoRaioX;
use App\Models\RaioX\PublicacaoRaioX;
use App\Models\RaioX\RelatorioRaioX;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\RaioX\Contracts\ClassificadorDecisoes;
use App\Support\CapitaisUf;
use App\Support\Oab;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

/**
 * Orquestra o Raio-X em etapas curtas (cada uma numa tarefa da fila, abaixo do retry_after):
 * coleta do DJEN em blocos de páginas → montagem dos processos → classificação de cada
 * processo com decisão (lote) → conclusão (conferência ou pronto para liberar).
 */
final class GeradorRaioX
{
    public function __construct(
        private readonly ComunicacaoGateway $gateway,
        private readonly ClassificadorDecisoes $classificador,
    ) {}

    /** Recomeça do zero (apaga coleta e classificação anteriores) e põe a coleta na fila. */
    public function iniciar(RelatorioRaioX $relatorio): void
    {
        DB::transaction(function () use ($relatorio): void {
            $relatorio->processos()->delete();
            $relatorio->publicacoes()->delete();
            $relatorio->update([
                'status' => StatusRaioX::Processando,
                'etapa' => 'Coletando publicações no DJEN',
                'publicacoes_total' => 0,
                'processos_total' => 0,
                'ia_tokens_entrada' => 0,
                'ia_tokens_saida' => 0,
                'erro' => null,
                'gerado_em' => null,
                'iniciado_em' => now(),
                'coleta_concluida_em' => null,
                'paginas_total' => 0,
                'paginas_coletadas' => 0,
                'classificar_total' => 0,
                'classificados' => 0,
                'liberado_em' => null,
                'liberado_por' => null,
                'ia_modelo' => $this->classificador->modelo(),
            ]);
        });

        ColetarRaioXJob::dispatch($relatorio->id, 1);
    }

    /**
     * Coleta um bloco de páginas a partir de $pagina.
     *
     * @return int|null Próxima página a coletar, ou nulo quando a coleta terminou.
     */
    public function coletar(RelatorioRaioX $relatorio, int $pagina, int $paginasPorTarefa): ?int
    {
        $porPagina = max(1, (int) config('raiox.coleta.itens_por_pagina', 100));
        $maxPaginas = max(1, (int) config('raiox.coleta.max_paginas', 60));
        $pausaMs = max(0, (int) config('raiox.coleta.pausa_ms', 0));

        for ($i = 0; $i < $paginasPorTarefa; $i++, $pagina++) {
            $resultado = $this->gateway->buscar(new FiltroConsulta(
                numeroOab: $relatorio->oab,
                ufOab: $relatorio->uf,
                dataInicio: $relatorio->inicio->toImmutable(),
                dataFim: $relatorio->fim->toImmutable(),
                pagina: $pagina,
                porPagina: $porPagina,
            ));

            $this->guardar($relatorio, $resultado->comunicacoes);

            $relatorio->update([
                'publicacoes_total' => $relatorio->publicacoes()->count(),
                'paginas_coletadas' => $pagina,
                'paginas_total' => max($pagina, min($maxPaginas, $resultado->ultimaPagina())),
                'etapa' => "Coletando publicações no DJEN (página {$pagina} de ".min($maxPaginas, $resultado->ultimaPagina()).')',
            ]);

            if ($pagina >= $resultado->ultimaPagina() || $pagina >= $maxPaginas || count($resultado->comunicacoes) === 0) {
                return null;
            }

            if (! $resultado->doCache && $pausaMs > 0) {
                Sleep::usleep($pausaMs * 1000);
            }
        }

        return $pagina;
    }

    /** @param  list<ComunicacaoData>  $comunicacoes */
    private function guardar(RelatorioRaioX $relatorio, array $comunicacoes): void
    {
        $oab = Oab::normalizar($relatorio->oab);

        foreach ($comunicacoes as $c) {
            $processo = preg_replace('/\D/', '', $c->numeroProcesso) ?: $c->numeroProcesso;

            $relatorio->publicacoes()->updateOrCreate(
                ['hash' => $c->hash],
                [
                    'numero_processo' => mb_substr($processo, 0, 20),
                    'data_disponibilizacao' => $c->dataDisponibilizacao->toDateString(),
                    'tribunal' => $c->tribunal,
                    'orgao' => $c->orgaoJulgador,
                    'tipo_comunicacao' => $c->tipoComunicacao,
                    'tipo_documento' => $c->tipoDocumento,
                    'classe' => $c->classe,
                    'destinatarios' => array_map(fn (DestinatarioData $d) => ['nome' => $d->nome, 'polo' => $d->polo], $c->destinatarios),
                    'advogados' => array_map(fn (AdvogadoData $a) => ['nome' => $a->nome, 'numeroOab' => $a->numeroOab, 'ufOab' => $a->ufOab], $c->advogados),
                    'texto' => mb_substr($c->textoPuro(), 0, 20000),
                ],
            );

            if ($relatorio->nome_advogado === null) {
                foreach ($c->advogados as $a) {
                    if ($a->numeroOab !== null && Oab::normalizar($a->numeroOab) === $oab && strtoupper((string) $a->ufOab) === $relatorio->uf) {
                        $relatorio->nome_advogado = $a->nome;
                        $relatorio->save();
                        break;
                    }
                }
            }
        }
    }

    /** Agrupa as publicações por processo e põe na fila a classificação dos que têm decisão. */
    public function montar(RelatorioRaioX $relatorio): void
    {
        $porProcesso = $relatorio->publicacoes()->orderByDesc('data_disponibilizacao')->get()->groupBy('numero_processo');
        $candidatos = [];

        foreach ($porProcesso as $numero => $publicacoes) {
            $candidato = $publicacoes->contains(fn (PublicacaoRaioX $p) => DetectorDecisao::pareceDecisao($p));

            $processo = $relatorio->processos()->create([
                'numero_processo' => (string) $numero,
                'tribunal' => $publicacoes->first()->tribunal,
                'publicacoes' => $publicacoes->count(),
                'ultima_publicacao' => $publicacoes->first()->data_disponibilizacao,
                'candidato' => $candidato,
                // Sem publicação de decisão no período: ativo, sem decisão (não vai para a IA).
                'resultado' => $candidato ? null : 'sem_decisao',
            ]);

            if ($candidato) {
                $candidatos[] = new ClassificarProcessoRaioXJob($processo->id);
            }
        }

        $relatorio->update([
            'processos_total' => $porProcesso->count(),
            'coleta_concluida_em' => now(),
            'classificar_total' => count($candidatos),
            'classificados' => 0,
            'etapa' => $candidatos === [] ? null : 'Classificando '.count($candidatos).' processos com decisão',
        ]);

        if ($candidatos === []) {
            $this->concluir($relatorio->id);

            return;
        }

        $id = $relatorio->id;

        Bus::batch($candidatos)
            ->name("raio-x {$id}")
            ->allowFailures()
            ->finally(function (Batch $batch) use ($id): void {
                app(GeradorRaioX::class)->concluir($id);
            })
            ->dispatch();
    }

    public function classificarProcesso(ProcessoRaioX $processo): void
    {
        $relatorio = $processo->relatorio;
        $limite = max(1, (int) config('raiox.ia.publicacoes_por_processo', 6));

        $publicacoes = $relatorio->publicacoes()
            ->where('numero_processo', $processo->numero_processo)
            ->orderByDesc('data_disponibilizacao')
            ->get();

        // As publicações de decisão primeiro; completa com as mais recentes; mantém a ordem por data.
        $escolhidas = $publicacoes->filter(fn (PublicacaoRaioX $p) => DetectorDecisao::pareceDecisao($p))
            ->concat($publicacoes)
            ->unique('id')
            ->take($limite)
            ->sortByDesc(fn (PublicacaoRaioX $p) => $p->data_disponibilizacao->timestamp)
            ->values()
            ->all();

        $classificacao = $this->classificador->classificar(new ContextoProcesso(
            numeroProcesso: $processo->numero_processo,
            tribunal: $processo->tribunal,
            oab: $relatorio->oab,
            uf: $relatorio->uf,
            nomeAdvogado: $relatorio->nome_advogado,
            capital: CapitaisUf::capital($relatorio->uf),
            publicacoes: $escolhidas,
        ));

        $this->aplicar($processo, $classificacao, $publicacoes);

        RelatorioRaioX::query()->whereKey($relatorio->id)->update([
            'ia_tokens_entrada' => DB::raw('ia_tokens_entrada + '.$classificacao->tokensEntrada),
            'ia_tokens_saida' => DB::raw('ia_tokens_saida + '.$classificacao->tokensSaida),
            'classificados' => DB::raw('classificados + 1'),
        ]);
    }

    /** @param  iterable<PublicacaoRaioX>  $publicacoes */
    private function aplicar(ProcessoRaioX $processo, ClassificacaoData $c, iterable $publicacoes): void
    {
        $texto = null;

        foreach ($publicacoes as $p) {
            if ($p->hash === $c->hashDecisao) {
                $texto = $p->texto;
                break;
            }
        }

        $processo->update([
            'resultado' => $c->resultado,
            'extincao_sem_merito' => $c->extincaoSemMerito,
            'instancia' => $c->instancia,
            'orgao_julgador' => $c->orgaoJulgador ? mb_substr($c->orgaoJulgador, 0, 255) : null,
            'vara_origem' => $c->varaOrigem ? mb_substr($c->varaOrigem, 0, 255) : null,
            'grupo_origem' => $c->grupoOrigem,
            'tema' => $c->tema,
            'parte_contraria' => $c->parteContraria ? mb_substr($c->parteContraria, 0, 255) : null,
            'polo_cliente' => $c->poloCliente,
            'data_decisao' => $c->dataDecisao?->toDateString(),
            'confianca' => $c->confianca,
            'justificativa' => $c->justificativa,
            'hash_decisao' => $c->hashDecisao,
            'texto_decisao' => $texto,
            'precisa_conferencia' => $c->precisaConferencia(),
        ]);
    }

    /** Fecha o processamento: com casos ambíguos vai para conferência; sem eles, pronto para liberar. */
    public function concluir(int $relatorioId): void
    {
        $relatorio = RelatorioRaioX::query()->find($relatorioId);

        if ($relatorio === null || $relatorio->status !== StatusRaioX::Processando) {
            return;
        }

        // Processo que a tarefa não conseguiu classificar (falha na fila) também vai para conferência.
        $relatorio->processos()->where('candidato', true)->whereNull('resultado')->update([
            'precisa_conferencia' => true,
            'justificativa' => 'A classificação não terminou. Confira manualmente.',
        ]);

        $pendentes = $relatorio->processos()->where('precisa_conferencia', true)->count();

        $relatorio->update([
            'status' => $pendentes > 0 ? StatusRaioX::Conferencia : StatusRaioX::Pronto,
            'etapa' => null,
            'gerado_em' => now(),
        ]);
    }

    /** Depois de uma conferência: se não sobrou nada pendente, fica pronto para liberar. */
    public function atualizarStatusAposConferencia(RelatorioRaioX $relatorio): void
    {
        if ($relatorio->status === StatusRaioX::Conferencia && ! $relatorio->processos()->where('precisa_conferencia', true)->exists()) {
            $relatorio->update(['status' => StatusRaioX::Pronto]);
        }
    }

    public function falhar(int $relatorioId, string $mensagem): void
    {
        RelatorioRaioX::query()->whereKey($relatorioId)->update([
            'status' => StatusRaioX::Falhou,
            'etapa' => null,
            'erro' => mb_substr($mensagem, 0, 1000),
        ]);
    }
}
