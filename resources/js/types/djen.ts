// Espelha os API Resources de app/Http/Resources/Djen e as props do
// ConsultaController. Mantenha os dois lados sincronizados.

/** App\Http\Resources\Djen\ComunicacaoResource → destinatarios[] */
export interface Destinatario {
    nome: string;
    /** Polo na API: A (ativo), P (passivo) ou outro código; null se desconhecido. */
    polo: string | null;
}

/** App\Http\Resources\Djen\ComunicacaoResource → advogados[] */
export interface Advogado {
    nome: string;
    numeroOab: string | null;
    ufOab: string | null;
}

/** App\Http\Resources\Djen\ComunicacaoResource */
export interface Comunicacao {
    hash: string;
    /** Identificador da comunicação na Comunica API. */
    id: number | null;
    numeroComunicacao: string | null;
    /** D (Diário de Justiça Eletrônico Nacional) ou E (Plataforma Nacional de Editais). */
    meio: string | null;
    meioCompleto: string | null;
    classe: string | null;
    codigoClasse: string | null;
    /** Número do processo só com dígitos (para copiar e buscar). */
    numeroProcessoDigitos: string;
    tribunal: string;
    orgaoJulgador: string | null;
    tipoComunicacao: string | null;
    tipoDocumento: string | null;
    /** AAAA-MM-DD */
    dataDisponibilizacao: string;
    /** Formatado no padrão CNJ quando possível. */
    numeroProcesso: string;
    destinatarios: Destinatario[];
    advogados: Advogado[];
    trecho: string;
    /** HTML já sanitizado no servidor (App\Support\HtmlSanitizer). */
    textoHtml: string;
    linkDocumento: string | null;
}

/** App\Http\Resources\Djen\ResultadoConsultaResource */
export interface ResultadoConsulta {
    data: Comunicacao[];
    meta: {
        pagina: number;
        porPagina: number;
        total: number;
        ultimaPagina: number;
        /** Filtro por tipo aplicado só às primeiras `analisadas` de `totalApi` comunicações. */
        filtroParcial: { analisadas: number; totalApi: number } | null;
    };
}

/** App\Http\Resources\Djen\TribunalResource */
export interface Tribunal {
    sigla: string;
    nome: string;
}

/** ConsultaController::erro() */
export interface ErroConsulta {
    tipo: 'limite' | 'bloqueado' | 'indisponivel' | 'inesperado';
    mensagem: string;
}

/** Filtros da query string (ConsultaRequest). */
export interface FiltrosConsulta {
    oab: string | null;
    uf: string | null;
    parte: string | null;
    advogado: string | null;
    processo: string | null;
    tribunal: string | null;
    inicio: string | null;
    fim: string | null;
    /** Palavra-chave no teor. */
    texto: string | null;
    meio: string | null;
    /** Número da comunicação. */
    comunicacao: string | null;
    /** Tipo de comunicação (Intimação, Edital...). */
    tipo: string | null;
}

/** Props da página Consulta/Index. */
export interface ConsultaPageProps {
    filtros: FiltrosConsulta;
    resultados: ResultadoConsulta | null;
    erro: ErroConsulta | null;
    tribunais: Tribunal[];
    ufs: string[];
    /** ConsultaRequest::MEIOS (código → nome). */
    meios: Record<string, string>;
    /** ConsultaRequest::TIPOS. */
    tipos: string[];
}
