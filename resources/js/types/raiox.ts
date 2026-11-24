// Espelha app/Http/Resources/RaioX/* e App\Services\RaioX\MetricasRaioX.

export type StatusRaioX =
    | 'solicitado'
    | 'processando'
    | 'conferencia'
    | 'pronto'
    | 'liberado'
    | 'falhou';

export type Resultado =
    | 'exito'
    | 'exito_parcial'
    | 'acordo'
    | 'insucesso'
    | 'sem_decisao';

/** App\Http\Resources\RaioX\RelatorioRaioXResource */
export interface RelatorioRaioX {
    id: number;
    oab: string;
    uf: string;
    nomeAdvogado: string | null;
    inicio: string;
    fim: string;
    status: StatusRaioX;
    statusRotulo: string;
    etapa: string | null;
    publicacoes: number;
    processos: number;
    custoMensal: number | null;
    custoVariavel: number | null;
    honorarioMedio: number | null;
    solicitadoEm: string;
    geradoEm: string | null;
    liberadoEm: string | null;
    /** App\Services\RaioX\ProgressoRaioX::de() — só enquanto processa. */
    progresso: ProgressoRaioX | null;
    /** Tempo total do último processamento. */
    duracaoSegundos: number | null;
    /** Só para o admin. */
    usuario?: { id: number; nome: string; email: string };
    pendentes?: number;
    erro?: string | null;
    ia?: {
        modelo: string | null;
        tokensEntrada: number;
        tokensSaida: number;
        custoUsd: number;
        custoBrl: number;
    };
}

/** App\Http\Resources\RaioX\ProcessoRaioXResource */
export interface ProcessoRaioX {
    id: number;
    numero: string;
    numeroMascara: string;
    tribunal: string | null;
    publicacoes: number;
    ultimaPublicacao: string | null;
    resultado: Resultado | null;
    extincaoSemMerito: boolean;
    instancia: string | null;
    orgaoJulgador: string | null;
    varaOrigem: string | null;
    grupoOrigem: string | null;
    tema: string | null;
    parteContraria: string | null;
    poloCliente: string | null;
    dataDecisao: string | null;
    confianca: 'alta' | 'media' | 'baixa' | null;
    justificativa: string | null;
    textoDecisao: string | null;
    precisaConferencia: boolean;
    conferidoEm: string | null;
}

/** Contagem de um grupo (MetricasRaioX::contagem). Taxa é fração (0–1). */
export interface Contagem {
    decididos: number;
    exito: number;
    parcial: number;
    acordo: number;
    insucesso: number;
    favoraveis: number;
    taxa: number | null;
}

export interface LinhaGrupo extends Contagem {
    rotulo: string;
    chave?: string;
}

export interface CustoRaioX {
    ativos: number;
    exitos: number;
    processosPorExito: number | null;
    custoMensal: number | null;
    custoVariavel: number | null;
    honorarioMedio: number | null;
    custoTotal: number | null;
    custoPorProcesso: number | null;
    honorarioMinimo: number | null;
    margem: number | null;
}

/** App\Services\RaioX\MetricasRaioX::calcular() */
export interface MetricasRaioX {
    visaoGeral: Contagem & {
        pendentes: number;
        extincoes: number;
        ativos: number;
        semDecisao: number;
        publicacoes: number;
    };
    porInstancia: LinhaGrupo[];
    porOrigem: LinhaGrupo[];
    porColegiado: LinhaGrupo[];
    varasCapital: {
        civeis: LinhaGrupo[];
        juizados: LinhaGrupo[];
        capital: string | null;
    };
    porTema: LinhaGrupo[];
    porParte: LinhaGrupo[];
    custo: CustoRaioX;
    destaques: { melhor: string[]; pior: string[] };
    conferencia: string[];
}

export interface Opcao {
    valor: string;
    rotulo: string;
}

export interface ProgressoRaioX {
    etapa: 'coleta' | 'classificacao';
    /** 1–99 enquanto processa. */
    percentual: number;
    paginas: number;
    paginasTotal: number;
    classificados: number;
    classificarTotal: number;
    decorridoSegundos: number | null;
    restanteSegundos: number | null;
}

/** Médias dos Raio-X já gerados (Admin\RaioXController::index). */
export interface TemposRaioX {
    amostras: number;
    mediaTotal: number | null;
    mediaColeta: number | null;
    mediaClassificacao: number | null;
    porPagina: number;
    porProcesso: number;
}
