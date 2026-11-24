// Formatação e cores do Raio-X.
import type { Contagem } from '@/types/raiox';

const moeda = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 0,
});
const decimal1 = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });

/** Fração (0–1) → "53%". */
export function percentual(taxa: number | null | undefined): string {
    return taxa === null || taxa === undefined
        ? '—'
        : `${Math.round(taxa * 100)}%`;
}

export function dinheiro(valor: number | null | undefined): string {
    return valor === null || valor === undefined ? '—' : moeda.format(valor);
}

export function decimal(valor: number | null | undefined): string {
    return valor === null || valor === undefined ? '—' : decimal1.format(valor);
}

/** Cor do texto da taxa: alta (≥ 50%) em destaque, baixa (< 30%) em alerta. */
export function classeTaxa(taxa: number | null): string {
    if (taxa === null) {
        return 'text-muted-foreground';
    }

    if (taxa >= 0.5) {
        return 'text-[var(--raiox-exito)] font-semibold';
    }

    return taxa < 0.3 ? 'text-[var(--raiox-insucesso)] font-semibold' : '';
}

/** Segmentos da barra empilhada, na ordem da legenda. */
export const SEGMENTOS = [
    { chave: 'exito', rotulo: 'Êxito', cor: 'var(--raiox-exito)' },
    { chave: 'parcial', rotulo: 'Êxito parcial', cor: 'var(--raiox-parcial)' },
    { chave: 'acordo', rotulo: 'Acordo', cor: 'var(--raiox-acordo)' },
    { chave: 'insucesso', rotulo: 'Insucesso', cor: 'var(--raiox-insucesso)' },
] as const;

export function partes(c: Contagem) {
    return SEGMENTOS.map((s) => ({
        ...s,
        valor: c[s.chave],
        largura: c.decididos > 0 ? (c[s.chave] / c.decididos) * 100 : 0,
    }));
}

/**
 * Calculadora de custo (mesma conta de MetricasRaioX::custo), para a tela
 * responder enquanto o advogado digita.
 */
export function calcularCusto(
    ativos: number,
    exitos: number,
    custoMensal: number | null,
    custoVariavel: number | null,
    honorarioMedio: number | null,
) {
    const total =
        custoMensal === null
            ? null
            : custoMensal * 12 + (custoVariavel ?? 0) * ativos;
    const minimo = total !== null && exitos > 0 ? total / exitos : null;

    return {
        custoTotal: total,
        custoPorProcesso: total !== null && ativos > 0 ? total / ativos : null,
        honorarioMinimo: minimo,
        margem:
            minimo !== null && honorarioMedio !== null
                ? honorarioMedio - minimo
                : null,
        processosPorExito: exitos > 0 ? ativos / exitos : null,
    };
}

export const ROTULOS_STATUS: Record<string, string> = {
    solicitado: 'Solicitado',
    processando: 'Em processamento',
    conferencia: 'Em conferência',
    pronto: 'Pronto para liberar',
    liberado: 'Disponível',
    falhou: 'Falhou',
};
