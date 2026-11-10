import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vite-plus/test';
import type { Comunicacao } from '@/types/djen';
import LinhaComunicacao from './LinhaComunicacao.vue';

const comunicacao: Comunicacao = {
    hash: 'h1',
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
    ],
    advogados: [
        { nome: 'Advogada Um', numeroOab: '30820', ufOab: 'RS' },
        { nome: 'Advogado Dois', numeroOab: '12345', ufOab: 'AM' },
        { nome: 'Advogada Três', numeroOab: '777', ufOab: 'SP' },
        { nome: 'Advogado Quatro', numeroOab: '888', ufOab: 'PR' },
        { nome: 'Sem OAB', numeroOab: null, ufOab: null },
    ],
    trecho: '…',
    textoHtml: '<p>…</p>',
    linkDocumento: null,
};

describe('LinhaComunicacao', () => {
    it('mostra data, tribunal, meio, processo, classe, órgão, tipo e documento', () => {
        const w = mount(LinhaComunicacao, { props: { comunicacao } });
        const texto = w.text();

        expect(texto).toContain('05/11/2026');
        expect(w.find('[data-testid="tribunal"]').text()).toBe('TJAM');
        expect(texto).toContain('D');
        expect(w.find('[data-testid="abrir-processo"]').text()).toBe(
            '0341724-22.2026.8.04.1000',
        );
        expect(w.find('[data-testid="classe"]').text()).toBe(
            'BUSCA E APREENSÃO EM ALIENAÇÃO FIDUCIÁRIA',
        );
        expect(texto).toContain('23ª Vara Cível');
        expect(w.find('[data-testid="tipo-comunicacao"]').text()).toBe(
            'Lista de distribuição',
        );
        expect(
            w.find('[data-testid="tipo-comunicacao"]').classes().join(' '),
        ).toContain('sky');
        expect(texto).toContain('Distribuição de processo');
    });

    it('mostra ativo × passivo e até 3 OABs, com o restante contado', () => {
        const w = mount(LinhaComunicacao, { props: { comunicacao } });

        expect(w.find('[data-testid="partes"]').text()).toMatch(
            /^Banco Autor S\.A\.\s*×\s*Empresa Ré Ltda$/,
        );
        const oabs = w.find('[data-testid="oabs"]').text();
        expect(oabs).toContain('30820/RS');
        expect(oabs).toContain('12345/AM');
        expect(oabs).toContain('777/SP');
        expect(oabs).not.toContain('888/PR');
        expect(oabs).toContain('+1');
    });

    it('abre os detalhes pelo número do processo ou pelo botão', async () => {
        const w = mount(LinhaComunicacao, { props: { comunicacao } });

        await w.find('[data-testid="abrir-processo"]').trigger('click');
        await w.find('[data-testid="ver-inteiro-teor"]').trigger('click');

        expect(w.emitted('verInteiroTeor')).toEqual([
            [comunicacao],
            [comunicacao],
        ]);
    });

    it('copia o número do processo só com dígitos', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText } });
        const w = mount(LinhaComunicacao, { props: { comunicacao } });

        await w.find('[data-testid="copiar-processo"]').trigger('click');
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith('03417242220268041000');
    });

    it('seleciona para exportar quando selecionável', async () => {
        const w = mount(LinhaComunicacao, {
            props: { comunicacao, selecionavel: true },
        });

        await w.find('[data-testid="selecionar"]').setValue(true);

        expect(w.emitted('update:selecionada')).toEqual([[true]]);
        expect(
            mount(LinhaComunicacao, { props: { comunicacao } })
                .find('[data-testid="selecionar"]')
                .exists(),
        ).toBe(false);
    });
});
