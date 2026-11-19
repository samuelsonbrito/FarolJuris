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
    tipo: null,
};

function montar(filtros: Partial<FiltrosConsulta> = {}, extra = {}) {
    return mount(FiltroBusca, {
        props: {
            filtros: { ...vazio, ...filtros },
            tribunais: [
                {
                    sigla: 'TJAM',
                    nome: 'Tribunal de Justiça do Amazonas',
                    categoria: 'Justiça Estadual',
                },
            ],
            ufs: ['AM', 'SP'],
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

    it('não busca enquanto digita, só ao clicar em Buscar', async () => {
        const wrapper = montar();
        const input = wrapper.find('#filtro-parte');

        await input.setValue('Jo');
        await input.setValue('Joana');
        vi.advanceTimersByTime(5000);
        await flushPromises();
        expect(wrapper.emitted('buscar')).toBeUndefined();

        await wrapper.find('form').trigger('submit');

        expect(wrapper.emitted('buscar')?.at(-1)).toEqual([
            { ...vazio, parte: 'Joana' },
        ]);
    });

    it('não busca ao trocar select, data ou atalho de período', async () => {
        vi.setSystemTime(new Date(2026, 10, 10, 12, 0, 0));
        const wrapper = montar({ oab: '12345' });

        await wrapper.find('#filtro-uf').setValue('AM');
        await wrapper.find('#filtro-tribunal').setValue('TJAM');
        await wrapper.find('#filtro-inicio').setValue('2026-11-01');
        await wrapper.find('[data-testid="atalho-7dias"]').trigger('click');
        vi.advanceTimersByTime(5000);
        await flushPromises();

        expect(wrapper.emitted('buscar')).toBeUndefined();
    });

    it('avisa que os filtros mudaram até a pessoa buscar', async () => {
        const wrapper = montar({ parte: 'Joana' });
        expect(wrapper.find('[data-testid="filtros-alterados"]').exists()).toBe(
            false,
        );

        // Espaço a mais não conta como mudança.
        await wrapper.find('#filtro-parte').setValue('Joana ');
        expect(wrapper.find('[data-testid="filtros-alterados"]').exists()).toBe(
            false,
        );

        await wrapper.find('#filtro-parte').setValue('Maria');
        expect(wrapper.find('[data-testid="filtros-alterados"]').exists()).toBe(
            true,
        );

        // A página recebe os novos filtros da URL depois da busca.
        await wrapper.setProps({ filtros: { ...vazio, parte: 'Maria' } });
        expect(wrapper.find('[data-testid="filtros-alterados"]').exists()).toBe(
            false,
        );
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

    it('aplica o atalho de período no formulário e busca ao enviar', async () => {
        vi.setSystemTime(new Date(2026, 10, 10, 12, 0, 0));
        const wrapper = montar({ tribunal: 'TJAM' });

        await wrapper.find('[data-testid="atalho-7dias"]').trigger('click');
        expect(wrapper.emitted('buscar')).toBeUndefined();
        await wrapper.find('form').trigger('submit');

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
        await wrapper.find('form').trigger('submit');
        expect(wrapper.emitted('buscar')?.at(-1)).toEqual([
            { ...vazio, tribunal: 'TJAM' },
        ]);
    });

    it('palavra-chave sozinha já é critério de busca', async () => {
        const wrapper = montar();

        await wrapper.find('#filtro-texto').setValue('penhora');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.emitted('buscar')).toEqual([
            [{ ...vazio, texto: 'penhora' }],
        ]);
    });

    it('filtra por tipo de comunicação ao enviar', async () => {
        const wrapper = montar(
            { oab: '5055', uf: 'AM' },
            {
                tipos: [
                    'Intimação',
                    'Lista de distribuição',
                    'Edital',
                    'Pauta de julgamento',
                ],
            },
        );

        expect(
            wrapper.findAll('#filtro-tipo option').map((o) => o.text()),
        ).toEqual([
            'Todos',
            'Intimação',
            'Lista de distribuição',
            'Edital',
            'Pauta de julgamento',
        ]);

        await wrapper.find('#filtro-tipo').setValue('Edital');
        expect(wrapper.emitted('buscar')).toBeUndefined();
        await wrapper.find('form').trigger('submit');

        expect(wrapper.emitted('buscar')?.at(-1)).toEqual([
            { ...vazio, oab: '5055', uf: 'AM', tipo: 'Edital' },
        ]);
    });

    it('agrupa os tribunais por categoria', () => {
        const wrapper = montar(
            {},
            {
                tribunais: [
                    {
                        sigla: 'STJ',
                        nome: 'STJ',
                        categoria: 'Tribunais superiores',
                    },
                    {
                        sigla: 'TJAM',
                        nome: 'TJAM',
                        categoria: 'Justiça Estadual',
                    },
                    {
                        sigla: 'TJSP',
                        nome: 'TJSP',
                        categoria: 'Justiça Estadual',
                    },
                    {
                        sigla: 'TRE-AM',
                        nome: 'TRE-AM',
                        categoria: 'Justiça Eleitoral',
                    },
                ],
            },
        );

        const grupos = wrapper.findAll('#filtro-tribunal optgroup');
        expect(grupos.map((g) => g.attributes('label'))).toEqual([
            'Tribunais superiores',
            'Justiça Estadual',
            'Justiça Eleitoral',
        ]);
        expect(
            grupos[1]!.findAll('option').map((o) => o.attributes('value')),
        ).toEqual(['TJAM', 'TJSP']);
    });
});
