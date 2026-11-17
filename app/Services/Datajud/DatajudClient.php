<?php

namespace App\Services\Datajud;

use App\Data\Datajud\ProcessoDatajudData;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Único ponto de contato com a API Pública do DataJud (CNJ).
 *
 * `POST {base_url}/{indice}/_search` com `Authorization: APIKey <chave pública>` e uma
 * consulta Elasticsearch por `numeroProcesso`. As respostas de sucesso (inclusive "não
 * encontrado") ficam em cache por `cache_ttl`, porque a API é lenta e o índice só muda em lotes.
 */
class DatajudClient
{
    /**
     * @param  array{base_url: string, api_key: string, user_agent: string, timeout: int, connect_timeout: int, cache_ttl: int, tribunais: array<string, string>}  $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
        private readonly array $config,
    ) {}

    public function suporta(string $tribunal): bool
    {
        return isset($this->config['tribunais'][strtoupper($tribunal)]);
    }

    /**
     * Os registros do processo no tribunal (um por grau), já mapeados.
     *
     * @param  string  $numero  20 dígitos.
     * @return list<ProcessoDatajudData>
     *
     * @throws DatajudException
     */
    public function processos(string $tribunal, string $numero): array
    {
        $tribunal = strtoupper($tribunal);
        $indice = $this->config['tribunais'][$tribunal] ?? null;

        if ($indice === null) {
            throw new DatajudException("Tribunal {$tribunal} não habilitado no DataJud.");
        }

        $chave = "datajud:{$tribunal}:{$numero}";

        /** @var list<array<string, mixed>>|null $fontes */
        $fontes = $this->cache->get($chave);

        if (! is_array($fontes)) {
            $fontes = $this->buscar($indice, $numero);
            $this->cache->put($chave, $fontes, $this->config['cache_ttl']);
        }

        return array_map(ProcessoDatajudData::fromApi(...), $fontes);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buscar(string $indice, string $numero): array
    {
        try {
            $resposta = $this->http
                ->baseUrl($this->config['base_url'])
                ->withHeaders([
                    'Authorization' => 'APIKey '.$this->config['api_key'],
                    'User-Agent' => $this->config['user_agent'],
                ])
                ->acceptJson()
                ->timeout($this->config['timeout'])
                ->connectTimeout($this->config['connect_timeout'])
                ->post("{$indice}/_search", [
                    'size' => 10,
                    'query' => ['match' => ['numeroProcesso' => $numero]],
                ]);
        } catch (ConnectionException $e) {
            throw new DatajudException('O DataJud não respondeu a tempo.', previous: $e);
        }

        if ($resposta->status() === 429) {
            throw new DatajudException('O DataJud está sobrecarregado (429).', sobrecarga: true);
        }

        if (! $resposta->successful()) {
            throw new DatajudException("O DataJud respondeu com o status {$resposta->status()}.");
        }

        $hits = $resposta->json('hits.hits');

        if (! is_array($hits)) {
            throw new DatajudException('O DataJud respondeu sem a lista de processos (hits).');
        }

        $fontes = [];

        foreach ($hits as $hit) {
            // O match é por texto: confere o número exato.
            if (is_array($hit['_source'] ?? null) && ($hit['_source']['numeroProcesso'] ?? null) === $numero) {
                $fontes[] = $hit['_source'];
            }
        }

        return $fontes;
    }
}
