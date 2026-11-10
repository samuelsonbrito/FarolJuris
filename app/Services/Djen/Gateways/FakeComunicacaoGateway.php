<?php

namespace App\Services\Djen\Gateways;

use App\Data\Djen\AdvogadoData;
use App\Data\Djen\ComunicacaoData;
use App\Data\Djen\DestinatarioData;
use App\Data\Djen\FiltroConsulta;
use App\Data\Djen\ResultadoConsulta;
use App\Data\Djen\TribunalData;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Dados de exemplo, determinísticos, no formato do app (não da API).
 * Usado nos testes e no desenvolvimento local (DJEN_GATEWAY=fake).
 * Todos os nomes são fictícios.
 */
final class FakeComunicacaoGateway implements ComunicacaoGateway
{
    /** @var list<ComunicacaoData>|null */
    private ?array $comunicacoes = null;

    public function __construct(
        private readonly ?CarbonImmutable $hoje = null,
    ) {}

    public function buscar(FiltroConsulta $filtro): ResultadoConsulta
    {
        $encontradas = array_values(array_filter(
            $this->todas(),
            fn (ComunicacaoData $c): bool => $this->corresponde($c, $filtro),
        ));

        usort($encontradas, fn (ComunicacaoData $a, ComunicacaoData $b): int => $b->dataDisponibilizacao <=> $a->dataDisponibilizacao ?: strcmp($a->hash, $b->hash));

        return new ResultadoConsulta(
            array_slice($encontradas, ($filtro->pagina - 1) * $filtro->porPagina, $filtro->porPagina),
            count($encontradas),
            $filtro->pagina,
            $filtro->porPagina,
        );
    }

    public function tribunais(): array
    {
        return [
            new TribunalData('TJAM', 'Tribunal de Justiça do Amazonas'),
            new TribunalData('TJSP', 'Tribunal de Justiça de São Paulo'),
            new TribunalData('TRF1', 'Tribunal Regional Federal da 1ª Região'),
            new TribunalData('TRT11', 'Tribunal Regional do Trabalho da 11ª Região'),
            new TribunalData('STJ', 'Superior Tribunal de Justiça'),
        ];
    }

    /** @return list<ComunicacaoData> */
    public function todas(): array
    {
        return $this->comunicacoes ??= $this->gerar();
    }

    private function corresponde(ComunicacaoData $c, FiltroConsulta $f): bool
    {
        if ($f->numeroOab !== null && ! collect($c->advogados)->contains(
            fn (AdvogadoData $a): bool => $a->numeroOab === $f->numeroOab && ($f->ufOab === null || $a->ufOab === $f->ufOab),
        )) {
            return false;
        }

        if ($f->nomeParte !== null && ! collect($c->destinatarios)->contains(
            fn (DestinatarioData $d): bool => self::contem($d->nome, $f->nomeParte),
        )) {
            return false;
        }

        if ($f->nomeAdvogado !== null && ! collect($c->advogados)->contains(
            fn (AdvogadoData $a): bool => self::contem($a->nome, $f->nomeAdvogado),
        )) {
            return false;
        }

        if ($f->numeroProcesso !== null && $c->numeroProcesso !== $f->numeroProcesso) {
            return false;
        }

        if ($f->tribunal !== null && $c->tribunal !== $f->tribunal) {
            return false;
        }

        if ($f->texto !== null && ! self::contem($c->textoPuro(), $f->texto)) {
            return false;
        }

        if ($f->meio !== null && $c->meio !== $f->meio) {
            return false;
        }

        if ($f->numeroComunicacao !== null && $c->numeroComunicacao !== $f->numeroComunicacao) {
            return false;
        }

        // Datas de calendário: compara AAAA-MM-DD, sem fuso nem horário.
        $data = $c->dataDisponibilizacao->toDateString();

        if ($f->dataInicio !== null && $data < $f->dataInicio->toDateString()) {
            return false;
        }

        return $f->dataFim === null || $data <= $f->dataFim->toDateString();
    }

    private static function contem(string $texto, string $busca): bool
    {
        return str_contains(Str::lower(Str::ascii($texto)), Str::lower(Str::ascii($busca)));
    }

    /** @return list<ComunicacaoData> */
    private function gerar(): array
    {
        $hoje = ($this->hoje ?? CarbonImmutable::today())->startOfDay();
        $tribunais = ['TJAM', 'TJAM', 'TJSP', 'TRF1', 'TRT11', 'STJ'];
        $orgaos = ['1ª Vara Cível', '2ª Vara de Família', 'Juizado Especial Cível', '3ª Turma', 'Vara do Trabalho'];
        $tipos = ['Intimação', 'Intimação', 'Citação', 'Edital'];
        $documentos = ['Despacho', 'Decisão', 'Sentença', 'Acórdão'];
        $advogados = [
            new AdvogadoData('Helena Prado Queiroz', '12345', 'AM'),
            new AdvogadoData('Rafael Monteiro Lins', '54321', 'AM'),
            new AdvogadoData('Beatriz Sales Amaral', '98765', 'SP'),
        ];
        $partes = ['Joana Ferreira Alves', 'Construtora Rio Negro Ltda', 'Marcos Antônio Teles', 'Município de Exemplo', 'Lúcia Bentes Coelho'];
        $classes = [['PROCEDIMENTO COMUM CÍVEL', '7'], ['BUSCA E APREENSÃO EM ALIENAÇÃO FIDUCIÁRIA', '81'], ['EXECUÇÃO DE TÍTULO EXTRAJUDICIAL', '12154'], ['APELAÇÃO CÍVEL', '198']];
        $assuntos = ['manifestar-se sobre os documentos juntados aos autos', 'manifestar-se sobre a alienação fiduciária do bem', 'apresentar contrarrazões ao recurso'];

        $lista = [];

        for ($i = 0; $i < 45; $i++) {
            $tipo = $tipos[$i % count($tipos)];
            $processo = sprintf('%07d%02d%04d8%02d%04d', 1000000 + $i * 7919 % 9000000, $i % 97, 2024 + $i % 3, 4, $i % 9000);
            $parte = $partes[$i % count($partes)];
            $parteContraria = $partes[($i + 2) % count($partes)];

            $lista[] = new ComunicacaoData(
                hash: substr(sha1("fake-{$i}"), 0, 16),
                tribunal: $tribunais[$i % count($tribunais)],
                orgaoJulgador: $orgaos[$i % count($orgaos)],
                tipoComunicacao: $tipo,
                tipoDocumento: $documentos[$i % count($documentos)],
                dataDisponibilizacao: $hoje->subDays(intdiv($i, 3)),
                numeroProcesso: $processo,
                destinatarios: [new DestinatarioData($parte, 'A'), new DestinatarioData($parteContraria, 'P')],
                advogados: array_values(array_unique([$advogados[$i % 3], $advogados[($i + 1) % 3]], SORT_REGULAR)),
                textoHtml: "<p><strong>{$tipo}</strong> — exemplo fictício nº ".($i + 1).'.</p>'
                    ."<p>Fica a parte {$parte} intimada, por seus advogados, para, no prazo de 15 (quinze) dias, "
                    .$assuntos[$i % count($assuntos)].', sob pena de preclusão.</p>'
                    .'<p>Este texto é gerado pelo modo de demonstração e não corresponde a nenhum processo real.</p>',
                linkDocumento: $i % 2 === 0 ? 'https://example.org/documentos/'.substr(sha1("fake-{$i}"), 0, 16) : null,
                idCnj: 900100000 + $i,
                numeroComunicacao: (string) (1000 + $i),
                meio: $tipo === 'Edital' ? 'E' : 'D',
                meioCompleto: $tipo === 'Edital' ? 'Plataforma Nacional de Editais' : 'Diário de Justiça Eletrônico Nacional',
                classe: $classes[$i % count($classes)][0],
                codigoClasse: $classes[$i % count($classes)][1],
            );
        }

        return $lista;
    }
}
