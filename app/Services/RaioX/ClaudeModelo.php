<?php

namespace App\Services\RaioX;

use Anthropic\Client;
use App\Data\RaioX\RespostaModelo;
use App\Services\RaioX\Contracts\ModeloLinguagem;

/**
 * Claude pela API da Anthropic (SDK oficial anthropic-ai/sdk), com saída estruturada (JSON Schema).
 *
 * O prompt de sistema é fixo e vai com cache (`cacheControl`): ele se repete em todas as
 * classificações do relatório. Esforço baixo: é uma classificação curta.
 */
final class ClaudeModelo implements ModeloLinguagem
{
    private ?Client $cliente = null;

    /**
     * @param  array{api_key: string|null, modelo: string, esforco: string, max_tokens: int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function gerarJson(string $sistema, string $usuario, array $esquema): RespostaModelo
    {
        $mensagem = $this->cliente()->messages->create(
            model: $this->config['modelo'],
            maxTokens: $this->config['max_tokens'],
            system: [
                ['type' => 'text', 'text' => $sistema, 'cacheControl' => ['type' => 'ephemeral']],
            ],
            messages: [['role' => 'user', 'content' => $usuario]],
            outputConfig: [
                'effort' => $this->config['esforco'],
                'format' => ['type' => 'json_schema', 'schema' => $esquema],
            ],
            // Abaixo do retry_after (90 s) da fila: a tarefa nunca é reenviada no meio de uma chamada.
            requestOptions: ['timeout' => 75, 'maxRetries' => 2],
        );

        $dados = null;

        // Com recusa (stop_reason "refusal") não há JSON: o caso vai para conferência humana.
        if ($mensagem->stopReason !== 'refusal') {
            foreach ($mensagem->content as $bloco) {
                if ($bloco->type === 'text') {
                    $json = json_decode($bloco->text, true);
                    $dados = is_array($json) ? $json : null;
                    break;
                }
            }
        }

        return new RespostaModelo(
            $dados,
            $mensagem->stopReason,
            $mensagem->usage->inputTokens + (int) $mensagem->usage->cacheReadInputTokens + (int) $mensagem->usage->cacheCreationInputTokens,
            $mensagem->usage->outputTokens,
        );
    }

    public function nome(): string
    {
        return $this->config['modelo'];
    }

    private function cliente(): Client
    {
        return $this->cliente ??= new Client(apiKey: $this->config['api_key'] ?: null);
    }
}
