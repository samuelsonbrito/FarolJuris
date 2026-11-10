<?php

namespace App\Console\Commands;

use App\Services\Djen\DjenClient;
use App\Services\Djen\Exceptions\DjenException;
use App\Services\Rastreio\Rastreador;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Confere, com chamadas reais, se a Comunica API respeita cada parâmetro de
 * filtro que o DjenApiGateway envia. Um nome de parâmetro errado é ignorado
 * pela API, que devolve comunicações quaisquer: é isso que o comando detecta.
 * Rodar no servidor (IP no Brasil). Usa poucas chamadas pequenas (limite: 20).
 */
#[Signature('djen:verificar-filtros
    {--tribunal=TJSP : Sigla para testar siglaTribunal e as datas}
    {--data= : Dia (AAAA-MM-DD) para testar as datas; padrão: último dia útil}
    {--oab= : Número da OAB para testar numeroOab + ufOab}
    {--uf= : UF da OAB}
    {--processo= : Número do processo (com ou sem máscara) para testar numeroProcesso}
    {--parte= : Nome de parte para testar nomeParte}
    {--advogado= : Nome de advogado para testar nomeAdvogado}
    {--texto= : Palavra-chave para testar texto (busca no teor), junto com o tribunal}
    {--meio= : D ou E para testar meio, junto com o tribunal}
    {--comunicacao= : Número da comunicação para testar numeroComunicacao, junto com o tribunal}')]
#[Description('Confere na API real se os parâmetros de filtro do DJEN funcionam')]
class VerificarFiltrosDjen extends Command
{
    private const ITENS = 5;

    private int $falhas = 0;

    private int $errosDeAcesso = 0;

    public function handle(DjenClient $client): int
    {
        Rastreador::origem('comando');

        $data = $this->option('data')
            ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('data'))
            : self::ultimoDiaUtil();
        $tribunal = mb_strtoupper((string) $this->option('tribunal'));

        // pagina
        $this->checar('pagina', $client, ['siglaTribunal' => $tribunal], function (array $p1) use ($client, $tribunal): ?string {
            $p2 = $this->itens($client, ['siglaTribunal' => $tribunal, 'pagina' => 2]);

            if ($p1 === [] || $p2 === []) {
                return null;
            }

            $repetidos = array_intersect(array_column($p1, 'hash'), array_column($p2, 'hash'));

            return $repetidos === [] ? 'ok' : 'a página 2 repetiu itens da página 1';
        });

        $this->checar('siglaTribunal', $client, ['siglaTribunal' => $tribunal],
            fn (array $itens) => self::todos($itens, fn ($i) => ($i['siglaTribunal'] ?? null) === $tribunal, "nem todos são do {$tribunal}"));

        $dia = $data->toDateString();
        $this->checar("dataDisponibilizacaoInicio/Fim ({$dia})", $client,
            ['siglaTribunal' => $tribunal, 'dataDisponibilizacaoInicio' => $dia, 'dataDisponibilizacaoFim' => $dia],
            fn (array $itens) => self::todos($itens, fn ($i) => ($i['data_disponibilizacao'] ?? null) === $dia, "há itens fora de {$dia}"));

        if ($this->option('oab')) {
            $oab = (string) $this->option('oab');
            $uf = mb_strtoupper((string) $this->option('uf'));
            $this->checar('numeroOab + ufOab', $client, ['numeroOab' => $oab, 'ufOab' => $uf],
                fn (array $itens) => self::todos($itens, fn ($i) => collect($i['destinatarioadvogados'] ?? [])
                    ->contains(fn ($v) => mb_strtoupper((string) ($v['advogado']['numero_oab'] ?? '')) === mb_strtoupper($oab)
                        && mb_strtoupper((string) ($v['advogado']['uf_oab'] ?? '')) === $uf), "há itens sem a OAB {$oab}/{$uf}"));
        }

        if ($this->option('processo')) {
            $processo = (string) preg_replace('/\D/', '', (string) $this->option('processo'));
            $this->checar('numeroProcesso', $client, ['numeroProcesso' => $processo],
                fn (array $itens) => self::todos($itens, fn ($i) => ($i['numero_processo'] ?? null) === $processo, 'há itens de outro processo'));
        }

        if ($this->option('parte')) {
            $parte = (string) $this->option('parte');
            $this->checar('nomeParte', $client, ['nomeParte' => $parte],
                fn (array $itens) => self::todos($itens, fn ($i) => collect($i['destinatarios'] ?? [])
                    ->contains(fn ($d) => self::contem((string) ($d['nome'] ?? ''), $parte)), "há itens sem a parte \"{$parte}\""));
        }

        if ($this->option('advogado')) {
            $advogado = (string) $this->option('advogado');
            $this->checar('nomeAdvogado', $client, ['nomeAdvogado' => $advogado],
                fn (array $itens) => self::todos($itens, fn ($i) => collect($i['destinatarioadvogados'] ?? [])
                    ->contains(fn ($v) => self::contem((string) ($v['advogado']['nome'] ?? ''), $advogado)), "há itens sem o advogado \"{$advogado}\""));
        }

        if ($this->option('texto')) {
            $texto = (string) $this->option('texto');
            $this->checar('texto', $client, ['siglaTribunal' => $tribunal, 'texto' => $texto],
                fn (array $itens) => self::todos($itens, fn ($i) => self::contem(strip_tags(html_entity_decode((string) ($i['texto'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')), $texto), "há itens sem \"{$texto}\" no teor"));
        }

        if ($this->option('meio')) {
            $meio = mb_strtoupper((string) $this->option('meio'));
            $this->checar('meio', $client, ['siglaTribunal' => $tribunal, 'meio' => $meio],
                fn (array $itens) => self::todos($itens, fn ($i) => ($i['meio'] ?? null) === $meio, "há itens de outro meio que não {$meio}"));
        }

        if ($this->option('comunicacao')) {
            $numero = (string) $this->option('comunicacao');
            $this->checar('numeroComunicacao', $client, ['siglaTribunal' => $tribunal, 'numeroComunicacao' => $numero],
                fn (array $itens) => self::todos($itens, fn ($i) => (string) ($i['numeroComunicacao'] ?? '') === $numero, 'há itens com outro número de comunicação'));
        }

        $this->newLine();

        if ($this->errosDeAcesso > 0) {
            $this->components->error('Não foi possível consultar a API a partir deste servidor (veja acima). Nada foi verificado; rode djen:diagnostico.');

            return self::FAILURE;
        }

        if ($this->falhas > 0) {
            $this->components->error("{$this->falhas} verificação(ões) falharam. Ajuste os nomes em DjenApiGateway::parametros().");

            return self::FAILURE;
        }

        $this->components->info('Os filtros testados funcionam na API real.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string|int>  $query
     * @param  callable(list<array<string, mixed>>): ?string  $avaliar  'ok', mensagem de erro ou null (inconclusivo)
     */
    private function checar(string $nome, DjenClient $client, array $query, callable $avaliar): void
    {
        try {
            $resultado = $avaliar($this->itens($client, $query));
        } catch (DjenException $e) {
            $this->errosDeAcesso++;
            $this->components->twoColumnDetail($nome, '<fg=red>ERRO</> '.$e->getMessage());

            return;
        }

        match (true) {
            $resultado === 'ok' => $this->components->twoColumnDetail($nome, '<fg=green>OK</>'),
            $resultado === null => $this->components->twoColumnDetail($nome, '<fg=yellow>SEM DADOS</> nenhum resultado; tente outro valor'),
            default => (function () use ($nome, $resultado): void {
                $this->falhas++;
                $this->components->twoColumnDetail($nome, "<fg=red>FALHOU</> {$resultado}: o parâmetro parece ser ignorado");
            })(),
        };
    }

    /**
     * @param  array<string, string|int>  $query
     * @return list<array<string, mixed>>
     */
    private function itens(DjenClient $client, array $query): array
    {
        $json = $client->get('comunicacao', $query + ['pagina' => 1, 'itensPorPagina' => self::ITENS])->json;

        return array_values(array_filter((array) ($json['items'] ?? []), 'is_array'));
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     */
    private static function todos(array $itens, callable $condicao, string $erro): ?string
    {
        if ($itens === []) {
            return null;
        }

        return collect($itens)->every($condicao) ? 'ok' : $erro;
    }

    private static function contem(string $texto, string $busca): bool
    {
        return str_contains(Str::lower(Str::ascii($texto)), Str::lower(Str::ascii($busca)));
    }

    private static function ultimoDiaUtil(): CarbonImmutable
    {
        $d = CarbonImmutable::now((string) config('djen.monitoring.timezone'))->subDay()->startOfDay();

        while ($d->isWeekend()) {
            $d = $d->subDay();
        }

        return $d;
    }
}
