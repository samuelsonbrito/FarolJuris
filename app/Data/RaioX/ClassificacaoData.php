<?php

namespace App\Data\RaioX;

use App\Enums\RaioX\Confianca;
use App\Enums\RaioX\GrupoOrigem;
use App\Enums\RaioX\InstanciaDecisao;
use App\Enums\RaioX\ResultadoDecisao;
use App\Enums\RaioX\TemaRaioX;
use Carbon\CarbonImmutable;

/** Classificação de um processo (decisão mais recente do período, do ponto de vista do cliente). */
final readonly class ClassificacaoData
{
    public function __construct(
        public ResultadoDecisao $resultado,
        public bool $extincaoSemMerito,
        public ?InstanciaDecisao $instancia,
        public ?string $orgaoJulgador,
        public ?string $varaOrigem,
        public ?GrupoOrigem $grupoOrigem,
        public ?TemaRaioX $tema,
        public ?string $parteContraria,
        /** ativo | passivo | indefinido */
        public string $poloCliente,
        public ?CarbonImmutable $dataDecisao,
        public Confianca $confianca,
        public string $justificativa,
        public ?string $hashDecisao,
        public int $tokensEntrada = 0,
        public int $tokensSaida = 0,
        /** Falha técnica (IA recusou, resposta inválida, erro de rede): vai para conferência. */
        public bool $falhou = false,
        /** Consumo da chamada à IA (nulo quando não houve chamada, como no driver de regras). */
        public ?UsoIA $uso = null,
    ) {}

    public function comUso(?UsoIA $uso): self
    {
        return new self(
            $this->resultado, $this->extincaoSemMerito, $this->instancia, $this->orgaoJulgador, $this->varaOrigem,
            $this->grupoOrigem, $this->tema, $this->parteContraria, $this->poloCliente, $this->dataDecisao,
            $this->confianca, $this->justificativa, $this->hashDecisao,
            $uso ? $uso->totalEntrada() : $this->tokensEntrada, $uso ? $uso->saida : $this->tokensSaida,
            $this->falhou, $uso,
        );
    }

    /** Casos ambíguos vão para conferência humana antes de entrar nos números. */
    public function precisaConferencia(): bool
    {
        if ($this->falhou || $this->confianca === Confianca::Baixa) {
            return true;
        }

        return $this->resultado->decidido() && $this->poloCliente === 'indefinido';
    }

    public static function falha(string $motivo, int $tokensEntrada = 0, int $tokensSaida = 0): self
    {
        return new self(
            ResultadoDecisao::SemDecisao, false, null, null, null, null, null, null, 'indefinido', null,
            Confianca::Baixa, $motivo, null, $tokensEntrada, $tokensSaida, falhou: true,
        );
    }
}
