<?php

namespace App\Services\RaioX;

use App\Data\RaioX\ClassificacaoData;
use App\Data\RaioX\ContextoProcesso;
use App\Enums\RaioX\Confianca;
use App\Enums\RaioX\GrupoOrigem;
use App\Enums\RaioX\InstanciaDecisao;
use App\Enums\RaioX\ResultadoDecisao;
use App\Enums\RaioX\TemaRaioX;
use App\Services\RaioX\Contracts\ClassificadorDecisoes;

/**
 * Classificação por expressões fixas (RAIOX_IA_DRIVER=fake): para testes e desenvolvimento sem
 * custo de IA. Não sabe o lado do cliente, então tudo que não for explícito sai com confiança baixa.
 */
final class ClassificadorRegras implements ClassificadorDecisoes
{
    public function modelo(): string
    {
        return 'regras';
    }

    public function classificar(ContextoProcesso $contexto): ClassificacaoData
    {
        foreach ($contexto->publicacoes as $p) {
            if (! DetectorDecisao::pareceDecisao($p)) {
                continue;
            }

            $t = mb_strtolower($p->texto);
            $recurso = (bool) preg_match('/provimento|ac[óo]rd[ãa]o|turma|c[âa]mara/u', $t);

            [$resultado, $extincao] = match (true) {
                (bool) preg_match('/homolog\w*.{0,30}(acordo|transa)/u', $t) => [ResultadoDecisao::Acordo, false],
                (bool) preg_match('/sem\s+resolu[çc][ãa]o\s+(do\s+)?m[ée]rito/u', $t) => [ResultadoDecisao::Insucesso, true],
                (bool) preg_match('/parcialmente\s+procedente|parcial\s+provimento|procedente\s+em\s+parte/u', $t) => [ResultadoDecisao::ExitoParcial, false],
                (bool) preg_match('/improcedente|nego\s+provimento|negar\s+provimento|negou\s+provimento/u', $t) => [ResultadoDecisao::Insucesso, false],
                (bool) preg_match('/procedente|dou\s+provimento|dar\s+provimento|deu\s+provimento/u', $t) => [ResultadoDecisao::Exito, false],
                default => [null, false],
            };

            if ($resultado === null) {
                continue;
            }

            return new ClassificacaoData(
                resultado: $resultado,
                extincaoSemMerito: $extincao,
                instancia: $recurso ? InstanciaDecisao::RecursoCliente : InstanciaDecisao::Sentenca,
                orgaoJulgador: $p->orgao,
                varaOrigem: $recurso ? null : $p->orgao,
                grupoOrigem: GrupoOrigem::NaoIdentificada,
                tema: TemaRaioX::Outros,
                parteContraria: null,
                poloCliente: 'ativo',
                dataDecisao: $p->data_disponibilizacao->toImmutable(),
                confianca: Confianca::Media,
                justificativa: 'Classificado por regras (sem IA).',
                hashDecisao: $p->hash,
            );
        }

        return self::exemplo($contexto);
    }

    /**
     * Sem expressão reconhecível (caso dos textos fictícios do gateway "fake"): gera uma classificação
     * de demonstração estável (derivada do número do processo), para o dashboard ter o que mostrar
     * em desenvolvimento. Nunca roda com o driver "claude".
     */
    private static function exemplo(ContextoProcesso $c): ClassificacaoData
    {
        $n = crc32($c->numeroProcesso);
        $escolher = fn (array $opcoes, int $deslocamento) => $opcoes[intdiv($n, 7 ** $deslocamento) % count($opcoes)];

        $resultado = $escolher([ResultadoDecisao::Exito, ResultadoDecisao::ExitoParcial, ResultadoDecisao::Insucesso, ResultadoDecisao::Insucesso, ResultadoDecisao::Acordo, ResultadoDecisao::Exito], 0);
        $instancia = $escolher([InstanciaDecisao::Sentenca, InstanciaDecisao::Sentenca, InstanciaDecisao::RecursoCliente, InstanciaDecisao::RecursoContraria], 1);
        $grupo = $escolher([GrupoOrigem::CivelCapital, GrupoOrigem::JuizadoCapital, GrupoOrigem::JuizadoCapital, GrupoOrigem::Interior, GrupoOrigem::OutroEstado], 2);
        $vara = $grupo === GrupoOrigem::CivelCapital ? ($n % 22 + 1).'ª Vara Cível' : ($grupo === GrupoOrigem::JuizadoCapital ? ($n % 23 + 1).'º JEC' : null);
        $publicacao = $c->publicacoes[0] ?? null;

        return new ClassificacaoData(
            resultado: $resultado,
            extincaoSemMerito: $resultado === ResultadoDecisao::Insucesso && $n % 5 === 0,
            instancia: $instancia,
            orgaoJulgador: $instancia === InstanciaDecisao::Sentenca ? $vara : $escolher(['1ª Turma Recursal', '2ª Turma Recursal', 'Segunda Câmara Cível', 'Terceira Câmara Cível'], 3),
            varaOrigem: $vara,
            grupoOrigem: $grupo,
            tema: $escolher(TemaRaioX::cases(), 4),
            parteContraria: $escolher(['Banco Exemplo', 'Financeira Fictícia', 'Telefonia Modelo', 'Varejo Demonstração', 'Seguradora Teste'], 5),
            poloCliente: 'ativo',
            dataDecisao: $publicacao?->data_disponibilizacao->toImmutable(),
            confianca: $n % 9 === 0 ? Confianca::Baixa : Confianca::Media,
            justificativa: 'Exemplo gerado pelo modo de demonstração (RAIOX_IA_DRIVER=fake).',
            hashDecisao: $publicacao?->hash,
        );
    }
}
