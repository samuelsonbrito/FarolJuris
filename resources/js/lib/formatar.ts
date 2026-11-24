// Formatação de números e datas da área administrativa (pt-BR).

const inteiro = new Intl.NumberFormat('pt-BR');
const decimal = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });
const reais = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const dolares = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
});

export type Formato = 'numero' | 'percentual' | 'ms' | 'brl' | 'usd';

export function formatarValor(
    valor: number | null | undefined,
    formato: Formato = 'numero',
): string {
    if (valor === null || valor === undefined) {
        return '—';
    }

    switch (formato) {
        case 'percentual':
            return `${decimal.format(valor)}%`;
        case 'brl':
            return reais.format(valor);
        case 'usd':
            return dolares.format(valor);
        case 'ms':
            return valor >= 1000
                ? `${decimal.format(valor / 1000)} s`
                : `${inteiro.format(valor)} ms`;
        default:
            return inteiro.format(valor);
    }
}

/**
 * Variação em relação ao período anterior. Percentuais comparam pontos
 * percentuais; o resto, a variação relativa.
 */
export function variacao(
    valor: number | null,
    anterior: number | null,
    formato: Formato = 'numero',
): { texto: string; direcao: 'sobe' | 'desce' | 'igual' } | null {
    if (valor === null || anterior === null) {
        return null;
    }

    const diferenca = valor - anterior;
    const direcao = diferenca > 0 ? 'sobe' : diferenca < 0 ? 'desce' : 'igual';
    const sinal = diferenca > 0 ? '+' : diferenca < 0 ? '−' : '';

    if (formato === 'percentual') {
        return {
            texto: `${sinal}${decimal.format(Math.abs(diferenca))} p.p.`,
            direcao,
        };
    }

    if (anterior === 0) {
        return { texto: diferenca === 0 ? '0%' : 'novo', direcao };
    }

    return {
        texto: `${sinal}${decimal.format(Math.abs((diferenca * 100) / anterior))}%`,
        direcao,
    };
}

/** "2026-11-07T14:05:00-04:00" → "07/11/2026 14:05" (horário já no fuso de exibição). */
export function formatarDataHora(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);

    return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : iso;
}

/** "2026-11-07" → "07/11". */
export function dataCurta(data: string): string {
    const [, mes, dia] = data.split('-');

    return dia && mes ? `${dia}/${mes}` : data;
}
