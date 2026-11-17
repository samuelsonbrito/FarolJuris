/** App\Http\Resources\Datajud\ProcessoDatajudResource (movimentos) */
export interface MovimentoDatajud {
    /** ISO 8601 em UTC. */
    dataHora: string | null;
    codigo: number | null;
    nome: string;
    complementos: string[];
    orgaoJulgador: string | null;
}

/** App\Http\Resources\Datajud\ProcessoDatajudResource */
export interface ProcessoDatajud {
    tribunal: string;
    grau: string | null;
    grauRotulo: string | null;
    numeroProcesso: string;
    classe: string | null;
    codigoClasse: number | null;
    assuntos: string[];
    orgaoJulgador: string | null;
    dataAjuizamento: string | null;
    sistema: string | null;
    formato: string | null;
    atualizadoEm: string | null;
    /** Do mais recente para o mais antigo. */
    movimentos: MovimentoDatajud[];
}
