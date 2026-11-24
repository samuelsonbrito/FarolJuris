<?php

namespace App\Services\RaioX\Contracts;

use App\Data\RaioX\RespostaModelo;

/** Uma chamada a um modelo de linguagem que devolve JSON no esquema pedido. */
interface ModeloLinguagem
{
    /**
     * @param  array<string, mixed>  $esquema  JSON Schema da resposta (saída estruturada).
     */
    public function gerarJson(string $sistema, string $usuario, array $esquema): RespostaModelo;

    public function nome(): string;
}
