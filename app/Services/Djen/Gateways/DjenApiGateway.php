<?php

namespace App\Services\Djen\Gateways;

use App\Data\Djen\FiltroConsulta;
use App\Data\Djen\ResultadoConsulta;
use App\Data\Djen\TribunalData;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\DjenClient;
use App\Services\Djen\Mapping\ComunicacaoApiMapper;

/**
 * Comunica API do CNJ, chamada pelo nosso backend (o navegador nunca fala
 * com o CNJ). Exige servidor com IP no Brasil.
 *
 * Parâmetros de GET /comunicacao vistos em requests reais: `siglaTribunal`,
 * `nomeParte`, `texto`, `dataDisponibilizacaoInicio/Fim` e `itensPorPagina`.
 * Os demais seguem a documentação pública e são conferidos no servidor por
 * `php artisan djen:verificar-filtros`. O tipo de comunicação a API ignora: é filtrado aqui
 * (`buscarPorTipo`).
 */
final class DjenApiGateway implements ComunicacaoGateway
{
    public function __construct(
        private readonly DjenClient $client,
        private readonly ComunicacaoApiMapper $mapper,
    ) {}

    public function buscar(FiltroConsulta $filtro): ResultadoConsulta
    {
        if ($filtro->tipoComunicacao !== null) {
            return $this->buscarPorTipo($filtro);
        }

        $resposta = $this->client->getCached('comunicacao', self::parametros($filtro));

        return $this->mapper->resultado($resposta->json, $filtro->pagina, $filtro->porPagina, $resposta->fromCache);
    }

    /**
     * A API ignora `tipoComunicacao` (em produção o total não mudava com o tipo), então o
     * filtro é feito aqui: busca as páginas da API (cada uma no cache do DjenClient), guarda
     * as do tipo pedido e pagina localmente. Analisa no máximo `djen.filtro_tipo.max_paginas`
     * páginas; acima disso o resultado vem marcado como parcial.
     */
    private function buscarPorTipo(FiltroConsulta $filtro): ResultadoConsulta
    {
        $porPaginaApi = max(1, (int) config('djen.filtro_tipo.itens_por_pagina', 100));
        $maxPaginas = max(1, (int) config('djen.filtro_tipo.max_paginas', 10));
        $tipo = mb_strtolower(trim($filtro->tipoComunicacao));

        $doTipo = [];
        $analisadas = 0;
        $totalApi = 0;
        $doCache = true;

        for ($pagina = 1; $pagina <= $maxPaginas; $pagina++) {
            $resposta = $this->client->getCached('comunicacao', [
                ...self::parametros($filtro),
                'pagina' => $pagina,
                'itensPorPagina' => $porPaginaApi,
            ]);
            $parcial = $this->mapper->resultado($resposta->json, $pagina, $porPaginaApi, $resposta->fromCache);

            $doCache = $doCache && $resposta->fromCache;
            $totalApi = $parcial->total;
            $analisadas += count($parcial->comunicacoes);

            foreach ($parcial->comunicacoes as $c) {
                if (mb_strtolower(trim((string) $c->tipoComunicacao)) === $tipo) {
                    $doTipo[] = $c;
                }
            }

            if (count($parcial->comunicacoes) < $porPaginaApi || $analisadas >= $totalApi) {
                break;
            }
        }

        $porPagina = max(1, $filtro->porPagina);

        return new ResultadoConsulta(
            comunicacoes: array_slice($doTipo, ($filtro->pagina - 1) * $porPagina, $porPagina),
            total: count($doTipo),
            pagina: $filtro->pagina,
            porPagina: $porPagina,
            doCache: $doCache,
            filtroParcial: $analisadas < $totalApi ? ['analisadas' => $analisadas, 'totalApi' => $totalApi] : null,
        );
    }

    public function tribunais(): array
    {
        return array_map(
            fn (string $sigla, string $nome): TribunalData => new TribunalData($sigla, $nome),
            array_keys(config('tribunais')),
            array_values(config('tribunais')),
        );
    }

    /**
     * @return array<string, string|int|null>
     */
    public static function parametros(FiltroConsulta $f): array
    {
        return [
            'numeroOab' => $f->numeroOab,
            'ufOab' => $f->ufOab,
            'nomeParte' => $f->nomeParte,
            'nomeAdvogado' => $f->nomeAdvogado,
            'numeroProcesso' => $f->numeroProcesso,
            'siglaTribunal' => $f->tribunal,
            'texto' => $f->texto,
            'meio' => $f->meio,
            'numeroComunicacao' => $f->numeroComunicacao,
            'dataDisponibilizacaoInicio' => $f->dataInicio?->toDateString(),
            'dataDisponibilizacaoFim' => $f->dataFim?->toDateString(),
            'pagina' => $f->pagina,
            'itensPorPagina' => $f->porPagina,
        ];
    }
}
