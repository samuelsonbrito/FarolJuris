import { toast } from 'vue-sonner';
import type { Destinatario } from '@/types/djen';

/**
 * Cores do tipo de comunicação (o nome vai escrito no selo: a cor só ajuda a
 * bater o olho). Tipos vistos na Comunica API; os demais ficam neutros.
 */
const CORES_TIPO: Record<string, string> = {
    intimação:
        'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-400/40 dark:bg-amber-400/10 dark:text-amber-200',
    citação:
        'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-400/40 dark:bg-rose-400/10 dark:text-rose-200',
    edital: 'border-violet-300 bg-violet-50 text-violet-900 dark:border-violet-400/40 dark:bg-violet-400/10 dark:text-violet-200',
    'lista de distribuição':
        'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-400/40 dark:bg-sky-400/10 dark:text-sky-200',
    'pauta de julgamento':
        'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-400/40 dark:bg-emerald-400/10 dark:text-emerald-200',
};

const COR_PADRAO = 'border-border bg-muted text-foreground dark:bg-muted/60';

export function corDoTipo(tipo: string | null | undefined): string {
    return (
        CORES_TIPO[(tipo ?? '').trim().toLocaleLowerCase('pt-BR')] ?? COR_PADRAO
    );
}

/** Partes por polo: "ATIVO × PASSIVO", com o que sobra contado em "+N". */
export function resumoPartes(
    destinatarios: Destinatario[],
    limite = 1,
): { ativo: string[]; passivo: string[]; restantes: number } {
    const ativos = destinatarios
        .filter((d) => d.polo === 'A')
        .map((d) => d.nome);
    const outros = destinatarios
        .filter((d) => d.polo !== 'A')
        .map((d) => d.nome);

    // Sem ativo identificado, mostra os primeiros nomes como estão.
    if (ativos.length === 0) {
        return {
            ativo: outros.slice(0, limite),
            passivo: outros.slice(limite, limite * 2),
            restantes: Math.max(0, outros.length - limite * 2),
        };
    }

    return {
        ativo: ativos.slice(0, limite),
        passivo: outros.slice(0, limite),
        restantes:
            Math.max(0, ativos.length - limite) +
            Math.max(0, outros.length - limite),
    };
}

export async function copiarTexto(
    texto: string,
    mensagem: string,
): Promise<void> {
    try {
        await navigator.clipboard.writeText(texto);
        toast.success(mensagem);
    } catch {
        toast.error('Não foi possível copiar. Selecione e copie manualmente.');
    }
}
