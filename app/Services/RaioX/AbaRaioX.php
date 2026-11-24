<?php

namespace App\Services\RaioX;

use App\Services\Exportacao\PlanilhaComunicacoes;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Uma aba da planilha do Raio-X. */
final class AbaRaioX implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  list<string>  $cabecalho
     * @param  list<list<string|int|float|null>>  $linhas
     */
    public function __construct(
        private readonly string $titulo,
        private readonly array $cabecalho,
        private readonly array $linhas,
    ) {}

    public function title(): string
    {
        return $this->titulo;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return $this->cabecalho;
    }

    /** @return list<list<string|int|float|null>> */
    public function array(): array
    {
        // Texto vindo das publicações não pode virar fórmula no Excel.
        return array_map(
            fn (array $linha) => array_map(fn ($v) => is_string($v) ? PlanilhaComunicacoes::celulaSegura($v) : $v, $linha),
            $this->linhas,
        );
    }
}
