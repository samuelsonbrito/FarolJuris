<?php

use Anthropic\Core\Exceptions\APIConnectionException;
use App\Data\RaioX\ContextoProcesso;
use App\Data\RaioX\RespostaModelo;
use App\Enums\RaioX\Confianca;
use App\Enums\RaioX\GrupoOrigem;
use App\Enums\RaioX\InstanciaDecisao;
use App\Enums\RaioX\ResultadoDecisao;
use App\Enums\RaioX\TemaRaioX;
use App\Models\RaioX\PublicacaoRaioX;
use App\Services\RaioX\ClassificadorIA;
use App\Services\RaioX\Contracts\ModeloLinguagem;
use GuzzleHttp\Psr7\Request;

/** Modelo de linguagem de mentira: devolve a resposta pronta e guarda o que recebeu. */
function modeloFalso(RespostaModelo|Throwable $resposta): ModeloLinguagem
{
    return new class($resposta) implements ModeloLinguagem
    {
        public array $chamadas = [];

        public function __construct(private RespostaModelo|Throwable $resposta) {}

        public function gerarJson(string $sistema, string $usuario, array $esquema): RespostaModelo
        {
            $this->chamadas[] = compact('sistema', 'usuario', 'esquema');

            if ($this->resposta instanceof Throwable) {
                throw $this->resposta;
            }

            return $this->resposta;
        }

        public function nome(): string
        {
            return 'modelo-teste';
        }
    };
}

function contexto(): ContextoProcesso
{
    $p = new PublicacaoRaioX([
        'hash' => 'abc123',
        'numero_processo' => '01234567820268041000',
        'data_disponibilizacao' => '2026-10-10',
        'orgao' => '1ª Turma Recursal',
        'tipo_comunicacao' => 'Intimação',
        'tipo_documento' => 'Acórdão',
        'destinatarios' => [['nome' => 'Cliente Fictício', 'polo' => 'A'], ['nome' => 'Banco Exemplo S.A.', 'polo' => 'P']],
        'advogados' => [['nome' => 'Advogada Exemplo', 'numeroOab' => '12345', 'ufOab' => 'AM']],
        'texto' => 'ACÓRDÃO. Acordam os juízes em NEGAR PROVIMENTO ao recurso inominado interposto pela parte autora.',
    ]);

    return new ContextoProcesso('01234567820268041000', 'TJAM', '12345', 'AM', 'Advogada Exemplo', 'Manaus', [$p]);
}

$resposta = [
    'resultado' => 'insucesso',
    'extincao_sem_merito' => false,
    'instancia' => 'recurso_cliente',
    'orgao_julgador' => '1ª Turma Recursal',
    'vara_origem' => '19º JEC',
    'grupo_origem' => 'juizado_capital',
    'tema' => 'descontos_em_conta',
    'parte_contraria' => 'Banco Exemplo',
    'polo_cliente' => 'ativo',
    'data_decisao' => '2026-10-10',
    'hash_decisao' => 'abc123',
    'confianca' => 'alta',
    'justificativa' => '"negar provimento ao recurso inominado interposto pela parte autora".',
];

it('envia o processo à IA com esquema estrito e lê a classificação', function () use ($resposta) {
    $modelo = modeloFalso(new RespostaModelo($resposta, 'end_turn', 1200, 150));

    $c = (new ClassificadorIA($modelo))->classificar(contexto());

    $chamada = $modelo->chamadas[0];
    expect($chamada['sistema'])->toBe(ClassificadorIA::SISTEMA)
        ->and($chamada['usuario'])->toContain('OAB 12345/AM (Advogada Exemplo)')
        ->toContain('Capital do estado da OAB: Manaus')
        ->toContain('hash abc123')
        ->toContain('Cliente Fictício (polo A)')
        ->toContain('NEGAR PROVIMENTO')
        ->and($chamada['esquema']['additionalProperties'])->toBeFalse()
        ->and($chamada['esquema']['required'])->toContain('resultado', 'polo_cliente', 'confianca');

    expect($c->resultado)->toBe(ResultadoDecisao::Insucesso)
        ->and($c->instancia)->toBe(InstanciaDecisao::RecursoCliente)
        ->and($c->grupoOrigem)->toBe(GrupoOrigem::JuizadoCapital)
        ->and($c->tema)->toBe(TemaRaioX::DescontosEmConta)
        ->and($c->varaOrigem)->toBe('19º JEC')
        ->and($c->dataDecisao?->toDateString())->toBe('2026-10-10')
        ->and($c->tokensEntrada)->toBe(1200)
        ->and($c->precisaConferencia())->toBeFalse();
});

it('manda para conferência quando a confiança é baixa ou o lado do cliente é indefinido', function () use ($resposta) {
    $baixa = (new ClassificadorIA(modeloFalso(new RespostaModelo([...$resposta, 'confianca' => 'baixa'], 'end_turn'))))->classificar(contexto());
    $semPolo = (new ClassificadorIA(modeloFalso(new RespostaModelo([...$resposta, 'polo_cliente' => 'indefinido'], 'end_turn'))))->classificar(contexto());
    $semDecisao = (new ClassificadorIA(modeloFalso(new RespostaModelo([...$resposta, 'resultado' => 'sem_decisao', 'polo_cliente' => 'indefinido'], 'end_turn'))))->classificar(contexto());

    expect($baixa->precisaConferencia())->toBeTrue()
        ->and($baixa->confianca)->toBe(Confianca::Baixa)
        ->and($semPolo->precisaConferencia())->toBeTrue()
        ->and($semDecisao->precisaConferencia())->toBeFalse();
});

it('trata recusa, resposta inválida e erro da API como conferência, sem derrubar o relatório', function () use ($resposta) {
    $recusa = (new ClassificadorIA(modeloFalso(new RespostaModelo(null, 'refusal', 500, 0))))->classificar(contexto());
    $invalida = (new ClassificadorIA(modeloFalso(new RespostaModelo([...$resposta, 'resultado' => 'ganhou'], 'end_turn'))))->classificar(contexto());
    $erro = (new ClassificadorIA(modeloFalso(new APIConnectionException(request: new Request('POST', 'https://api.anthropic.com/v1/messages')))))->classificar(contexto());

    foreach ([$recusa, $invalida, $erro] as $c) {
        expect($c->falhou)->toBeTrue()
            ->and($c->precisaConferencia())->toBeTrue();
    }

    expect($recusa->justificativa)->toContain('recusou')
        ->and($recusa->tokensEntrada)->toBe(500)
        ->and($erro->justificativa)->toContain('APIConnectionException');
});
