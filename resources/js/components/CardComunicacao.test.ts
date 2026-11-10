import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import type { Comunicacao } from '@/types/djen';
import CardComunicacao from './CardComunicacao.vue';

const comunicacao: Comunicacao = {
    hash: 'abc123',
    id: 776349899,
    numeroComunicacao: '5645',
    meio: 'D',
    meioCompleto: 'Diário de Justiça Eletrônico Nacional',
    classe: 'PROCEDIMENTO COMUM CÍVEL',
    codigoClasse: '7',
    numeroProcessoDigitos: '00012345620248040001',
    tribunal: 'TJAM',
    orgaoJulgador: '1ª Vara Cível',
    tipoComunicacao: 'Intimação',
    tipoDocumento: 'Despacho',
    dataDisponibilizacao: '2026-11-06',
    numeroProcesso: '0001234-56.2024.8.04.0001',
    destinatarios: [
        { nome: 'Joana Alves', polo: 'A' },
        { nome: 'Empresa X Ltda', polo: 'P' },
    ],
    advogados: [
        { nome: 'Helena Prado', numeroOab: '12345', ufOab: 'AM' },
        { nome: 'Sem OAB', numeroOab: null, ufOab: null },
    ],
    trecho: 'Fica a parte intimada…',
    textoHtml: '<p>Fica a parte intimada.</p>',
    linkDocumento: null,
};

describe('CardComunicacao', () => {
    it('exibe os dados da comunicação', () => {
        const wrapper = mount(CardComunicacao, { props: { comunicacao } });
        const texto = wrapper.text();

        expect(wrapper.find('[data-testid="tribunal"]').text()).toBe('TJAM');
        expect(texto).toContain('Intimação');
        expect(texto).toContain('Despacho');
        expect(texto).toContain('1ª Vara Cível');
        expect(texto).toContain('06/11/2026');
        expect(wrapper.find('h3').text()).toBe('0001234-56.2024.8.04.0001');
        expect(wrapper.find('[data-testid="destinatarios"]').text()).toBe(
            'Joana Alves · Empresa X Ltda',
        );
        expect(wrapper.find('[data-testid="advogados"]').text()).toBe(
            'Helena Prado (OAB 12345/AM) · Sem OAB',
        );
        expect(wrapper.find('[data-testid="trecho"]').text()).toBe(
            'Fica a parte intimada…',
        );
    });

    it('omite campos opcionais ausentes', () => {
        const wrapper = mount(CardComunicacao, {
            props: {
                comunicacao: {
                    ...comunicacao,
                    orgaoJulgador: null,
                    tipoDocumento: null,
                    advogados: [],
                },
            },
        });

        expect(wrapper.text()).not.toContain('Vara');
        expect(wrapper.find('[data-testid="advogados"]').exists()).toBe(false);
    });

    it('abre os detalhes ao clicar no número do processo', async () => {
        const wrapper = mount(CardComunicacao, { props: { comunicacao } });

        await wrapper.find('[data-testid="abrir-processo"]').trigger('click');

        expect(wrapper.emitted('verInteiroTeor')).toEqual([[comunicacao]]);
    });

    it('emite verInteiroTeor com a comunicação', async () => {
        const wrapper = mount(CardComunicacao, { props: { comunicacao } });

        await wrapper.find('[data-testid="ver-inteiro-teor"]').trigger('click');

        expect(wrapper.emitted('verInteiroTeor')).toEqual([[comunicacao]]);
    });

    it('só mostra a caixa de seleção quando selecionável', async () => {
        expect(
            mount(CardComunicacao, { props: { comunicacao } })
                .find('[data-testid="selecionar"]')
                .exists(),
        ).toBe(false);

        const wrapper = mount(CardComunicacao, {
            props: { comunicacao, selecionavel: true, selecionada: false },
        });
        await wrapper.find('[data-testid="selecionar"]').setValue(true);

        expect(wrapper.emitted('update:selecionada')).toEqual([[true]]);
    });
});
