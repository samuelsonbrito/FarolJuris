// Atalhos de período da Consulta. Datas de calendário (AAAA-MM-DD) no fuso do navegador.

export type AtalhoData =
    | 'hoje'
    | 'ontem'
    | '7dias'
    | '30dias'
    | 'esteMes'
    | 'mesPassado'
    | 'semDatas';

export const ATALHOS_DATA: { chave: AtalhoData; rotulo: string }[] = [
    { chave: 'hoje', rotulo: 'Hoje' },
    { chave: 'ontem', rotulo: 'Ontem' },
    { chave: '7dias', rotulo: '7 dias' },
    { chave: '30dias', rotulo: '30 dias' },
    { chave: 'esteMes', rotulo: 'Este mês' },
    { chave: 'mesPassado', rotulo: 'Mês passado' },
    { chave: 'semDatas', rotulo: 'Sem datas' },
];

function iso(d: Date): string {
    const mes = String(d.getMonth() + 1).padStart(2, '0');
    const dia = String(d.getDate()).padStart(2, '0');

    return `${d.getFullYear()}-${mes}-${dia}`;
}

function menosDias(d: Date, dias: number): Date {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate() - dias);
}

/** Intervalo { inicio, fim } de um atalho; `hoje` é injetável para testes. */
export function intervaloDoAtalho(
    atalho: AtalhoData,
    hoje: Date = new Date(),
): { inicio: string | null; fim: string | null } {
    const h = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate());

    switch (atalho) {
        case 'hoje':
            return { inicio: iso(h), fim: iso(h) };
        case 'ontem':
            return { inicio: iso(menosDias(h, 1)), fim: iso(menosDias(h, 1)) };
        case '7dias':
            return { inicio: iso(menosDias(h, 6)), fim: iso(h) };
        case '30dias':
            return { inicio: iso(menosDias(h, 29)), fim: iso(h) };
        case 'esteMes':
            return {
                inicio: iso(new Date(h.getFullYear(), h.getMonth(), 1)),
                fim: iso(h),
            };
        case 'mesPassado':
            return {
                inicio: iso(new Date(h.getFullYear(), h.getMonth() - 1, 1)),
                fim: iso(new Date(h.getFullYear(), h.getMonth(), 0)),
            };
        default:
            return { inicio: null, fim: null };
    }
}

/** Qual atalho corresponde ao intervalo atual (para destacar o botão). */
export function atalhoAtivo(
    inicio: string | null,
    fim: string | null,
    hoje: Date = new Date(),
): AtalhoData | null {
    return (
        ATALHOS_DATA.find(({ chave }) => {
            const i = intervaloDoAtalho(chave, hoje);

            return i.inicio === (inicio || null) && i.fim === (fim || null);
        })?.chave ?? null
    );
}
