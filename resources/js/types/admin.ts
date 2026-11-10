// Espelha as props dos controllers em app/Http/Controllers/Admin.

export type ChavePeriodo = 'hoje' | '7d' | '30d' | '90d' | 'personalizado';

/** App\Support\Admin\Periodo::toArray() */
export interface Periodo {
    chave: ChavePeriodo;
    /** AAAA-MM-DD */
    inicio: string;
    fim: string;
    dias: number;
}

/** Item de distribuição (MetricasAdmin::distribuicao). */
export interface Fatia {
    chave: string;
    rotulo: string;
    total: number;
}

export interface Indicador {
    valor: number | null;
    anterior: number | null;
}

export interface MetaPagina {
    pagina: number;
    porPagina: number;
    total: number;
    ultimaPagina: number;
}

export interface Paginado<T> {
    data: T[];
    meta: MetaPagina;
}

export interface UsuarioResumo {
    id: number;
    nome: string;
    email: string;
}

export interface ResumoCnj {
    porResultado: Fatia[];
    porOrigem: Fatia[];
    latenciaMediaMs: number | null;
    latenciaP95Ms: number | null;
    ratelimitRestante: number | null;
    ratelimitEm: string | null;
}

/** Admin/Dashboard */
export interface DashboardProps {
    periodo: Periodo;
    presets: Record<string, string>;
    indicadores: Record<
        | 'buscas'
        | 'visitantes'
        | 'taxaResultado'
        | 'tempoMedioMs'
        | 'taxaCache'
        | 'cadastros'
        | 'usuariosAtivos'
        | 'alertas'
        | 'notificacoes'
        | 'exportacoes'
        | 'chamadasCnj'
        | 'falhasCnj'
        | 'alvosAtivos',
        Indicador
    >;
    porDia: { data: string; buscas: number; visitantes: number }[];
    buscasPorHora: { hora: number; total: number }[];
    buscasPorCriterio: Fatia[];
    buscasPorStatus: Fatia[];
    tribunais: Fatia[];
    ufs: Fatia[];
    dispositivos: Fatia[];
    navegadores: Fatia[];
    sistemas: Fatia[];
    referencias: Fatia[];
    paginas: Fatia[];
    termos: Record<
        'oab' | 'parte' | 'advogado' | 'processo' | 'texto',
        Fatia[]
    >;
    cnj: ResumoCnj;
}

interface Origem {
    visitante: string | null;
    dispositivo: string | null;
    navegador: string | null;
    sistema: string | null;
    ip: string | null;
    referencia: string | null;
}

export interface BuscaRegistrada extends Origem {
    id: number;
    quando: string;
    criterio: string | null;
    filtros: Record<string, string>;
    status: 'ok' | 'vazio' | 'erro' | 'invalido';
    erroTipo: string | null;
    resultados: number | null;
    duracaoMs: number | null;
    doCache: boolean | null;
    usuario: UsuarioResumo | null;
    bot: boolean;
}

/** Admin/Buscas */
export interface BuscasProps {
    periodo: Periodo;
    presets: Record<string, string>;
    filtros: {
        criterio: string | null;
        status: string | null;
        quem: 'usuarios' | 'visitantes' | null;
        usuario: number | null;
        termo: string | null;
        robos: boolean;
    };
    criterios: Record<string, string>;
    status: Record<string, string>;
    buscas: Paginado<BuscaRegistrada>;
}

export interface EventoAtividade extends Origem {
    id: number;
    quando: string;
    tipo: string;
    rotulo: string;
    dados: Record<string, unknown>;
    usuario: UsuarioResumo | null;
    caminho: string | null;
}

/** Admin/Atividade */
export interface AtividadeProps {
    periodo: Periodo;
    presets: Record<string, string>;
    filtros: {
        tipo: string | null;
        usuario: UsuarioResumo | null;
        robos: boolean;
    };
    tipos: Record<string, string>;
    porTipo: Fatia[];
    eventos: Paginado<EventoAtividade>;
}

export interface UsuarioAdmin {
    id: number;
    nome: string;
    email: string;
    admin: boolean;
    verificado: boolean;
    whatsapp: boolean;
    cadastro: string;
    ultimoAcesso: string | null;
    alvos: number;
    alertas: number;
    buscas: number;
    exportacoes: number;
}

/** Admin/Usuarios */
export interface UsuariosProps {
    filtros: { q: string | null };
    totais: {
        usuarios: number;
        verificados: number;
        admins: number;
        comWhatsapp: number;
    };
    usuarios: Paginado<UsuarioAdmin>;
}

export interface ChamadaCnj {
    id: number;
    quando: string;
    origem: string;
    endpoint: string;
    parametros: Record<string, string | number>;
    status: number | null;
    resultado: string;
    duracaoMs: number;
    itens: number | null;
    ratelimitRestante: number | null;
    erro: string | null;
}

/** Admin/ChamadasCnj */
export interface ChamadasCnjProps {
    periodo: Periodo;
    presets: Record<string, string>;
    filtros: { resultado: string | null; origem: string | null };
    resultados: Record<string, string>;
    origens: Record<string, string>;
    resumo: ResumoCnj & { total: number };
    chamadas: Paginado<ChamadaCnj>;
}
