import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vite-plus/test';
import CalculadoraCusto from './CalculadoraCusto.vue';

const put = vi.fn();

vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/vue3')>();
    const { reactive } = await import('vue');

    return {
        ...original,
        useForm: (dados: Record<string, unknown>) => {
            const form = reactive({
                ...dados,
                processing: false,
                transform: () => form,
                put,
            });

            return form;
        },
    };
});

vi.stubGlobal('route', (nome: string, id: number) => `/${nome}/${id}`);

const custo = {
    ativos: 289,
    exitos: 33,
    processosPorExito: 8.8,
    custoMensal: 16000,
    custoVariavel: null,
    honorarioMedio: null,
    custoTotal: 192000,
    custoPorProcesso: 664.36,
    honorarioMinimo: 5818.18,
    margem: null,
};

describe('CalculadoraCusto', () => {
    it('mostra o honorário mínimo e recalcula enquanto digita', async () => {
        const w = mount(CalculadoraCusto, {
            props: { relatorioId: 7, custo, editavel: true },
        });

        expect(w.find('[data-testid="honorario-minimo"]').text()).toContain(
            '5.818',
        );

        await w.find('#custo-mensal').setValue('8000');
        expect(w.find('[data-testid="honorario-minimo"]').text()).toContain(
            '2.909',
        );

        await w.find('#honorario-medio').setValue('2000');
        expect(w.find('[data-testid="margem"]').text()).toContain('909');
        expect(w.find('[data-testid="margem"]').classes()).toContain(
            'text-destructive',
        );
    });

    it('salva os custos', async () => {
        const w = mount(CalculadoraCusto, {
            props: { relatorioId: 7, custo, editavel: true },
        });

        await w.find('form').trigger('submit');

        expect(put).toHaveBeenCalledWith('/raiox.custo/7', expect.anything());
    });

    it('sem permissão, os campos ficam desabilitados e não há botão', () => {
        const w = mount(CalculadoraCusto, {
            props: { relatorioId: 7, custo, editavel: false },
        });

        expect(w.find('#custo-mensal').attributes('disabled')).toBeDefined();
        expect(w.find('button[type="submit"]').exists()).toBe(false);
    });
});
