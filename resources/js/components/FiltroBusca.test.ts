import { flushPromises, mount } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import type { FiltrosConsulta } from '@/types/djen';
import FiltroBusca from './FiltroBusca.vue';

const vazio: FiltrosConsulta = {
    oab: null,
    uf: null,
    parte: null,
    advogado: null,
    processo: null,
    tribunal: null,
    inicio: null,
    fim: null,
    texto: null,
    meio: null,
    comunicacao: null,
};

function montar(filtros: Partial<FiltrosConsulta> = {}, extra = {}) {
    return mount(FiltroBusca, {
        props: {
            filtros: { ...vazio, ...filtros },
            tribunais: [
                { sigla: 'TJAM', nome: 'Tribunal de Justiça do Amazonas' },
            ],
            ufs: ['AM', 'SP'],
            debounce: 500,
            ...extra,
        },
        attachTo: document.body,
    });
}

describe('FiltroBusca', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('preenche os campos a partir dos filtros da URL, com máscara CNJ', () => {
        const wrapper = montar({
            oab: '12345',
            uf: 'AM',
            processo: '00012345620248040001',
            tribunal: 'TJAM',
        });

        expect(
            (wrapper.find('#filtro-oab').element as HTMLInputElement).value,
        ).toBe('12345');
        expect(
            (wrapper.find('#filtro-uf').element as HTMLSelectElement).value,
        ).toBe('AM');
        expect(
            (wrapper.find('#filtro-processo').element as HTMLInputElement)
                .value,
        ).toBe('0001234-56.2024.8.04.0001');
        expect(
            (wrapper.find('#filtro-tribunal').element as HTMLSelectElement)
                .value,
        ).toBe('TJAM');
    });

    it('emite filtros normalizados ao enviar', async () => {
        const wrapper = montar();

        await wrapper.find('#filtro-parte').setValue('  Joana  ');
        await wrapper
            .find('#filtro-processo')
            .setValue('0001234-56.2024.8.04.0001');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.emitted('buscar')?.at(-1)).toEqual([
            { ...vazio, parte: 'Joana', processo: '00012345620248040001' },
        ]);
    });

    it('envia OAB com letra, em maiúsculas', async () => {
        const wrapper = montar({ uf: 'MT' });

        await wrapper.find('#filtro-oab').setValue('20413-o');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.emitted('buscar')?.at(-1)).toEqual([
            { ...vazio, uf: 'MT', oab: '20413-O' },
        ]);
    });

    it('aplica máscara CNJ enquanto digita', async () => {
        const wrapper = montar();
        const input = wrapper.find('#filtro-processo');

        await input.setValue('123456789');

        expect((input.element as HTMLInputElement).value).toBe('1234567-89');
    });

    it('busca automaticamente após o debounce, uma vez só', async () => {
        const wrapper = montar();
        const input = wrapper.find('#filtro-parte');

        await input.setValue('Jo');
        await input.setValue('Joa');
        await input.setValue('Joan');
        vi.advanceTimersByTime(499);
        await flushPromises();
        expect(wrapper.emitted('buscar')).toBeUndefined();

        vi.advanceTimersByTime(1);
        await flushPromises();
        expect(wrapper.emitted('buscar')).toEqual([
            [{ ...vazio, parte: 'Joan' }],
        ]);
    });

    it('cancela o debounce pendente quando há uma busca imediata', async () => {
        const wrapper = montar();

        await wrapper.find('#filtro-oab').setValue('12345'); // agenda debounce
        await wrapper.find('#filtro-uf').setValue('AM'); // busca imediata
        vi.advanceTimersByTime(2000);
        await flushPromises();

        expect(wrapper.emitted('buscar')).toHaveLength(1);
    });

    it('não repete a busca se os filtros já são os da URL', async () => {
        const wrapper = montar({ parte: 'Joana' });

        await wrapper.find('#filtro-parte').setValue('Joana ');
        vi.advanceTimersByTime(600);
        await flushPromises();

        expect(wrapper.emitted('buscar')).toBeUndefined();
    });

    it('não busca automaticamente com filtro incompleto', async () => {
        const wrapper = montar();

        await wrapper.find('#filtro-parte').setValue('Jo'); // menos de 3 letras
        vi.advanceTimersByTime(600);
        await wrapper.find('#filtro-parte').setValue('');
        await wrapper.find('#filtro-oab').setValue('12345'); // OAB sem UF
        vi.advanceTimersByTime(600);
        await wrapper.find('#filtro-oab').setValue('');
        await wrapper.find('#filtro-processo').setValue('12345'); // processo incompleto
        vi.advanceTimersByTime(600);
        await flushPromises();

        expect(wrapper.emitted('buscar')).toBeUndefined();
    });

    it('busca na hora ao trocar select com filtros válidos', async () => {
        const wrapper = montar({ oab: '12345' });

        await wrapper.find('#filtro-uf').setValue('AM');

        expect(wrapper.emitted('buscar')).toEqual([
            [{ ...vazio, oab: '12345', uf: 'AM' }],
        ]);
    });

    it('limpa todos os campos e busca sem filtros', async () => {
        const wrapper = montar({ parte: 'Joana', tribunal: 'TJAM' });

        await wrapper.find('[data-testid="limpar"]').trigger('click');

        expect(wrapper.emitted('buscar')).toEqual([[vazio]]);
        expect(
            (wrapper.find('#filtro-parte').element as HTMLInputElement).value,
        ).toBe('');
    });

    it('exibe os erros de validação do servidor', () => {
        const wrapper = montar(
            {},
            {
                erros: {
                    processo: 'O número do processo deve ter 20 dígitos.',
                },
            },
        );

        expect(wrapper.text()).toContain(
            'O número do processo deve ter 20 dígitos.',
        );
        expect(
            wrapper.find('#filtro-processo').attributes('aria-invalid'),
        ).toBe('true');
    });

    it('envia palavra-chave, meio e nº da comunicação', async () => {
        const wrapper = montar(
            { tribunal: 'TJAM' },
            {
                meios: {
                    D: 'Diário de Justiça Eletrônico Nacional',
                    E: 'Plataforma Nacional de Editais',
                },
            },
        );

        await wrapper.find('#filtro-texto').setValue('  alienação fiduciária ');
        await wrapper
            .find('[data-testid="alternar-avancados"]')
            .trigger('click');
        await wrapper.find('#filtro-meio').setValue('E');
        await wrapper.find('#filtro-comunicacao').setValue('56-45');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.emitted('buscar')?.at(-1)).toEqual([
            {
                ...vazio,
                tribunal: 'TJAM',
                texto: 'alienação fiduciária',
                meio: 'E',
                comunicacao: '5645',
            },
        ]);
    });

    it('abre os filtros avançados quando vieram na URL', () => {
        const fechado = montar();
        expect(fechado.find('#filtros-avancados').isVisible()).toBe(false);

        const aberto = montar({ meio: 'D' });
        expect(aberto.find('#filtros-avancados').isVisible()).toBe(true);
    });

    it('aplica o atalho de período e busca na hora', async () => {
        vi.setSystemTime(new Date(2026, 10, 10, 12, 0, 0));
        const wrapper = montar({ tribunal: 'TJAM' });

        await wrapper.find('[data-testid="atalho-7dias"]').trigger('click');

        expect(wrapper.emitted('buscar')?.at(-1)).toEqual([
            {
                ...vazio,
                tribunal: 'TJAM',
                inicio: '2026-11-04',
                fim: '2026-11-10',
            },
        ]);
        expect(
            wrapper
                .find('[data-testid="atalho-7dias"]')
                .attributes('aria-pressed'),
        ).toBe('true');

        await wrapper.find('[data-testid="atalho-semDatas"]').trigger('click');
        expect(wrapper.emitted('buscar')?.at(-1)).toEqual([
            { ...vazio, tribunal: 'TJAM' },
        ]);
    });

    it('palavra-chave sozinha já é critério de busca', async () => {
        const wrapper = montar();

        await wrapper.find('#filtro-texto').setValue('penhora');
        vi.advanceTimersByTime(500);
        await flushPromises();

        expect(wrapper.emitted('buscar')).toEqual([
            [{ ...vazio, texto: 'penhora' }],
        ]);
    });
});
