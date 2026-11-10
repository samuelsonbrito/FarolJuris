import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import type { Comunicacao } from '@/types/djen';
import PainelComunicacao from './PainelComunicacao.vue';

const comunicacao: Comunicacao = {
    hash: 'rX21azVqRw3hza8SKTyP7DQRAKmGWl',
    id: 776349899,
    numeroComunicacao: '5645',
    meio: 'D',
    meioCompleto: 'Diário de Justiça Eletrônico Nacional',
    classe: 'BUSCA E APREENSÃO EM ALIENAÇÃO FIDUCIÁRIA',
    codigoClasse: '81',
    numeroProcessoDigitos: '03417242220268041000',
    tribunal: 'TJAM',
    orgaoJulgador: '23ª Vara Cível',
    tipoComunicacao: 'Lista de distribuição',
    tipoDocumento: 'Distribuição de processo',
    dataDisponibilizacao: '2026-11-05',
    numeroProcesso: '0341724-22.2026.8.04.1000',
    destinatarios: [
        { nome: 'Empresa Ré Ltda', polo: 'P' },
        { nome: 'Banco Autor S.A.', polo: 'A' },
        { nome: 'Terceiro', polo: 'T' },
    ],
    advogados: [{ nome: 'Advogada Exemplo', numeroOab: '30820', ufOab: 'RS' }],
    trecho: 'Foi distribuído…',
    textoHtml:
        '<p>Foi <strong>distribuído</strong> o feito.</p><p>Segundo parágrafo.</p>',
    linkDocumento: null,
};

// O Sheet (reka-ui) renderiza num portal no <body>.
function montar(c: Comunicacao = comunicacao) {
    return mount(PainelComunicacao, {
        props: { comunicacao: c, open: true },
        attachTo: document.body,
        global: {
            stubs: {
                Link: {
                    template: '<a :href="href"><slot /></a>',
                    props: ['href'],
                },
            },
            mocks: {
                route: (nome: string, q: Record<string, string>) =>
                    `/${nome}?processo=${q.processo}`,
            },
        },
    });
}

const corpo = () => document.body;
const achar = (sel: string) =>
    document.body.querySelector(sel) as HTMLElement | null;

describe('PainelComunicacao', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.restoreAllMocks();
    });

    it('mostra os dados da comunicação, partes com polo e advogados com OAB', async () => {
        montar();
        await flushPromises();
        const texto = corpo().textContent ?? '';

        expect(texto).toContain('Comunicação nº 776349899');
        expect(
            achar('[data-testid="painel-processo"]')?.textContent?.trim(),
        ).toBe('0341724-22.2026.8.04.1000');
        expect(texto).toContain('05/11/2026');
        expect(texto).toContain('BUSCA E APREENSÃO EM ALIENAÇÃO FIDUCIÁRIA');
        expect(texto).toContain('(81)');
        expect(texto).toContain('Diário de Justiça Eletrônico Nacional');
        expect(texto).toContain('5645');
        expect(texto).toContain('Passivo');
        expect(texto).toContain('Ativo');
        expect(texto).toContain('Polo T');
        expect(texto).toContain('OAB 30820/RS');
        expect(texto).toContain('Sem link do documento no tribunal.');
        expect(
            achar('[data-testid="todas-do-processo"]')?.getAttribute('href'),
        ).toBe('/consulta.index?processo=03417242220268041000');
    });

    it('alterna o teor entre formatado, texto e dados', async () => {
        montar();
        await flushPromises();

        expect(achar('[data-testid="inteiro-teor"]')?.innerHTML).toContain(
            '<strong>distribuído</strong>',
        );

        achar('[data-testid="visao-texto"]')?.click();
        await flushPromises();
        expect(achar('[data-testid="teor-texto"]')?.textContent).toBe(
            'Foi distribuído o feito.\nSegundo parágrafo.',
        );

        achar('[data-testid="visao-dados"]')?.click();
        await flushPromises();
        const dados = JSON.parse(
            achar('[data-testid="teor-dados"]')?.textContent ?? '{}',
        );
        expect(dados).toMatchObject({
            id: 776349899,
            numeroComunicacao: '5645',
            texto: 'Foi distribuído o feito.\nSegundo parágrafo.',
        });
        expect(dados).not.toHaveProperty('textoHtml');
    });

    it('copia o número do processo só com dígitos', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText } });
        montar();
        await flushPromises();

        achar('[data-testid="copiar-processo"]')?.click();
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith('03417242220268041000');
    });
});
