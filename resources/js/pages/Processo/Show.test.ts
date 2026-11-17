import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import type { ProcessoPageProps } from '@/types/djen';
import Show from './Show.vue';

vi.stubGlobal('route', (nome: string) => `/${nome}`);

const estado = {
    user: null as null | { id: number },
    post: vi.fn(),
};

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({
        props: { auth: { user: estado.user }, datajudTribunais: ['TJAM'] },
    }),
    useForm: (dados: Record<string, unknown>) => ({
        ...dados,
        processing: false,
        post: estado.post,
    }),
    router: { get: vi.fn(), reload: vi.fn() },
    Head: { render: () => null },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

const base: ProcessoPageProps = {
    processo: {
        numero: '02930960220268041000',
        numeroMascara: '0293096-02.2026.8.04.1000',
        tribunal: 'TJAM',
        tribunalNome: 'Tribunal de Justiça - Amazonas',
        andamentos: true,
    },
    alvo: null,
    resultados: {
        data: [],
        meta: {
            pagina: 1,
            porPagina: 20,
            total: 0,
            ultimaPagina: 1,
            filtroParcial: null,
        },
    },
    erro: null,
};

function montar(props: Partial<ProcessoPageProps> = {}) {
    return mount(Show, {
        props: { ...base, ...props },
        global: {
            stubs: { PainelComunicacao: true },
            mocks: { route: (nome: string) => `/${nome}` },
        },
    });
}

describe('Processo/Show', () => {
    afterEach(() => {
        estado.user = null;
        estado.post.mockReset();
    });

    it('mostra o número, o tribunal e os andamentos', () => {
        const w = montar();

        expect(w.find('[data-testid="numero-processo"]').text()).toBe(
            '0293096-02.2026.8.04.1000',
        );
        expect(w.text()).toContain('Tribunal de Justiça - Amazonas');
        expect(w.find('[data-testid="andamentos"]').exists()).toBe(true);
        expect(w.text()).toContain('Nenhuma publicação encontrada');
    });

    it('esconde os andamentos quando o tribunal não tem DataJud', () => {
        const w = montar({
            processo: { ...base.processo, tribunal: 'TJSP', andamentos: false },
        });

        expect(w.find('[data-testid="andamentos"]').exists()).toBe(false);
    });

    it('convida o visitante a entrar para monitorar', () => {
        const w = montar();

        expect(
            w.find('[data-testid="entrar-monitorar"]').attributes('href'),
        ).toBe('/login');
        expect(w.find('[data-testid="monitorar"]').exists()).toBe(false);
    });

    it('cria o alvo de monitoramento do processo', async () => {
        estado.user = { id: 1 };
        const w = montar();

        await w.find('[data-testid="monitorar"]').trigger('click');

        expect(estado.post).toHaveBeenCalledWith(
            '/monitoramento.alvos.store',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('indica quando o processo já é monitorado', () => {
        estado.user = { id: 1 };
        const w = montar({ alvo: { id: 7, ativo: true } });

        expect(w.find('[data-testid="monitorado"]').text()).toContain(
            'Monitorado',
        );
        expect(w.find('[data-testid="monitorar"]').exists()).toBe(false);
    });

    it('mostra o erro do CNJ', () => {
        const w = montar({
            resultados: null,
            erro: { tipo: 'indisponivel', mensagem: 'O CNJ está fora do ar.' },
        });

        expect(w.find('[data-testid="erro-api"]').text()).toContain(
            'O CNJ está fora do ar.',
        );
    });
});
