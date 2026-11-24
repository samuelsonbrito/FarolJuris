<?php

namespace App\Services\RaioX;

use Anthropic\Core\Exceptions\AnthropicException;
use App\Data\RaioX\ClassificacaoData;
use App\Data\RaioX\ContextoProcesso;
use App\Data\RaioX\UsoIA;
use App\Enums\RaioX\Confianca;
use App\Enums\RaioX\GrupoOrigem;
use App\Enums\RaioX\InstanciaDecisao;
use App\Enums\RaioX\ResultadoDecisao;
use App\Enums\RaioX\TemaRaioX;
use App\Services\RaioX\Contracts\ClassificadorDecisoes;
use App\Services\RaioX\Contracts\ModeloLinguagem;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Classifica a decisão de um processo lendo as publicações com um modelo de linguagem.
 * Falhas (recusa, JSON inválido, erro da API) não derrubam o relatório: o processo vai
 * para conferência humana.
 */
final class ClassificadorIA implements ClassificadorDecisoes
{
    public const SISTEMA = <<<'TXT'
    Você é um analista jurídico que lê publicações do Diário de Justiça Eletrônico Nacional (DJEN) e
    classifica o resultado de processos do ponto de vista do CLIENTE do advogado indicado (identificado
    pela OAB). Responda apenas com o JSON pedido.

    Como decidir:
    1. Cliente: a parte representada pelo advogado da OAB indicada. Use os destinatários (polo A = ativo,
       P = passivo) e o texto para descobrir de que lado ele está. Se não der para saber, polo_cliente =
       "indefinido".
    2. Considere a decisão MAIS RECENTE do período que resolve o mérito, homologa acordo, extingue o
       processo ou julga recurso. Despachos, intimações para manifestação ou pagamento, pautas, atas sem
       resultado e decisões interlocutórias não são decisão: nesses casos resultado = "sem_decisao".
    3. Resultado, sempre lido do lado do cliente:
       - "exito": pedido do cliente julgado procedente; recurso do cliente provido; recurso da parte
         contrária desprovido ou não conhecido; quando o cliente é réu, ação improcedente.
       - "exito_parcial": procedência parcial ou provimento parcial favorável ao cliente.
       - "acordo": homologação de acordo ou transação.
       - "insucesso": improcedência contra o cliente; recurso do cliente desprovido ou não conhecido;
         recurso da parte contrária provido contra o cliente; extinção sem resolução de mérito
         (marque extincao_sem_merito = true e diga o motivo na justificativa).
    4. instancia: "sentenca" (decisão de 1º grau ou de juizado), "recurso_cliente" (recurso interposto
       pelo cliente), "recurso_contraria" (recurso da parte contrária) ou "outra".
    5. orgao_julgador: o órgão que proferiu a decisão classificada, como aparece na publicação.
       vara_origem: a vara ou juizado de 1º grau onde o processo começou, abreviado como "22ª Vara Cível",
       "19º JEC", "1ª Vara da Fazenda Pública"; vazio se a publicação não disser.
    6. grupo_origem: "civel_capital" (vara cível ou de família da capital indicada), "juizado_capital"
       (juizado especial cível da capital), "interior" (comarca do interior do mesmo estado),
       "outro_estado", "trabalho_federal_fiscal" (Justiça do Trabalho, Federal ou execução fiscal) ou
       "nao_identificada".
    7. tema: escolha o mais próximo da lista. Use "bancario_nao_informado" quando for ação contra banco
       e a publicação não disser do que se trata (ex.: só a ata da sessão).
    8. parte_contraria: nome curto e normalizado do principal adversário do cliente, juntando empresas do
       mesmo grupo ("Bradesco" para Banco Bradesco S.A. ou Bradesco Financiamentos; "Itaú" para Itaú
       Unibanco; "Banco Pan"). Vazio se não houver.
    9. data_decisao (AAAA-MM-DD) e hash_decisao: da publicação que contém a decisão classificada.
    10. confianca: "baixa" quando houver dúvida real (não se sabe quem recorreu, o lado do cliente, ou o
        resultado); "media" quando a conclusão depende de inferência; "alta" quando o texto é explícito.
    11. justificativa: uma ou duas frases citando a expressão decisiva do texto.
    TXT;

    public function __construct(private readonly ModeloLinguagem $modelo) {}

    public function modelo(): string
    {
        return $this->modelo->nome();
    }

    public function classificar(ContextoProcesso $contexto): ClassificacaoData
    {
        try {
            $resposta = $this->modelo->gerarJson(self::SISTEMA, $this->mensagem($contexto), self::esquema());
        } catch (AnthropicException $e) {
            report($e);

            return ClassificacaoData::falha('Falha ao consultar a IA: '.class_basename($e).'. Confira manualmente.')
                ->comUso(new UsoIA($this->modelo->nome(), resultado: 'erro'));
        }

        $uso = new UsoIA(
            $this->modelo->nome(), $resposta->tokensEntrada, $resposta->tokensCacheLeitura,
            $resposta->tokensCacheEscrita, $resposta->tokensSaida, $resposta->duracaoMs,
        );

        if ($resposta->dados === null) {
            $recusa = $resposta->motivoParada === 'refusal';
            $motivo = $recusa
                ? 'A IA recusou classificar esta publicação. Confira manualmente.'
                : 'A IA respondeu fora do formato esperado. Confira manualmente.';

            return ClassificacaoData::falha($motivo)->comUso($uso->comResultado($recusa ? 'recusa' : 'invalido'));
        }

        try {
            return self::paraClassificacao($resposta->dados)->comUso($uso);
        } catch (Throwable) {
            return ClassificacaoData::falha('A IA respondeu com valores inválidos. Confira manualmente.')
                ->comUso($uso->comResultado('invalido'));
        }
    }

    public function mensagem(ContextoProcesso $c): string
    {
        $linhas = [
            "Advogado do cliente: OAB {$c->oab}/{$c->uf}".($c->nomeAdvogado ? " ({$c->nomeAdvogado})" : ''),
            'Capital do estado da OAB: '.($c->capital ?? 'não informada'),
            "Processo: {$c->numeroProcesso}".($c->tribunal ? " · {$c->tribunal}" : ''),
            '',
            'Publicações (da mais recente para a mais antiga):',
        ];

        $limite = (int) config('raiox.ia.max_caracteres_publicacao', 6000);

        foreach ($c->publicacoes as $i => $p) {
            $destinatarios = collect($p->destinatarios ?? [])
                ->map(fn (array $d): string => $d['nome'].(isset($d['polo']) && $d['polo'] ? " (polo {$d['polo']})" : ''))
                ->implode('; ');
            $advogados = collect($p->advogados ?? [])
                ->map(fn (array $a): string => $a['nome'].(isset($a['numeroOab']) && $a['numeroOab'] ? " (OAB {$a['numeroOab']}/".($a['ufOab'] ?? '').')' : ''))
                ->implode('; ');

            $linhas[] = '';
            $linhas[] = '--- Publicação '.($i + 1)." · hash {$p->hash} · {$p->data_disponibilizacao->toDateString()}";
            $linhas[] = 'Órgão: '.($p->orgao ?? '—').' · Tipo: '.($p->tipo_comunicacao ?? '—').' / '.($p->tipo_documento ?? '—').' · Classe: '.($p->classe ?? '—');
            $linhas[] = 'Destinatários: '.($destinatarios ?: '—');
            $linhas[] = 'Advogados: '.($advogados ?: '—');
            $linhas[] = 'Texto: '.mb_strimwidth($p->texto, 0, $limite, ' […]');
        }

        return implode("\n", $linhas);
    }

    /** @return array<string, mixed> */
    public static function esquema(): array
    {
        $enum = fn (array $casos): array => ['type' => 'string', 'enum' => array_map(fn ($c) => $c->value, $casos)];

        return [
            'type' => 'object',
            'properties' => [
                'resultado' => $enum(ResultadoDecisao::cases()),
                'extincao_sem_merito' => ['type' => 'boolean'],
                'instancia' => $enum(InstanciaDecisao::cases()),
                'orgao_julgador' => ['type' => 'string'],
                'vara_origem' => ['type' => 'string'],
                'grupo_origem' => $enum(GrupoOrigem::cases()),
                'tema' => $enum(TemaRaioX::cases()),
                'parte_contraria' => ['type' => 'string'],
                'polo_cliente' => ['type' => 'string', 'enum' => ['ativo', 'passivo', 'indefinido']],
                'data_decisao' => ['type' => 'string'],
                'hash_decisao' => ['type' => 'string'],
                'confianca' => $enum(Confianca::cases()),
                'justificativa' => ['type' => 'string'],
            ],
            'required' => [
                'resultado', 'extincao_sem_merito', 'instancia', 'orgao_julgador', 'vara_origem', 'grupo_origem',
                'tema', 'parte_contraria', 'polo_cliente', 'data_decisao', 'hash_decisao', 'confianca', 'justificativa',
            ],
            'additionalProperties' => false,
        ];
    }

    /** @param  array<string, mixed>  $d */
    public static function paraClassificacao(array $d, int $tokensEntrada = 0, int $tokensSaida = 0): ClassificacaoData
    {
        $texto = fn (string $chave): ?string => is_string($d[$chave] ?? null) && trim($d[$chave]) !== '' ? trim($d[$chave]) : null;
        $data = $texto('data_decisao');

        return new ClassificacaoData(
            resultado: ResultadoDecisao::from((string) $d['resultado']),
            extincaoSemMerito: (bool) ($d['extincao_sem_merito'] ?? false),
            instancia: InstanciaDecisao::tryFrom((string) ($d['instancia'] ?? '')),
            orgaoJulgador: $texto('orgao_julgador'),
            varaOrigem: $texto('vara_origem'),
            grupoOrigem: GrupoOrigem::tryFrom((string) ($d['grupo_origem'] ?? '')),
            tema: TemaRaioX::tryFrom((string) ($d['tema'] ?? '')),
            parteContraria: $texto('parte_contraria'),
            poloCliente: in_array($d['polo_cliente'] ?? null, ['ativo', 'passivo'], true) ? $d['polo_cliente'] : 'indefinido',
            dataDecisao: $data !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) ? CarbonImmutable::createFromFormat('!Y-m-d', $data) : null,
            confianca: Confianca::tryFrom((string) ($d['confianca'] ?? '')) ?? Confianca::Baixa,
            justificativa: $texto('justificativa') ?? '',
            hashDecisao: $texto('hash_decisao'),
            tokensEntrada: $tokensEntrada,
            tokensSaida: $tokensSaida,
        );
    }
}
