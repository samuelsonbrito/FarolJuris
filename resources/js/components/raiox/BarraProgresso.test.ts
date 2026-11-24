import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import { duracao } from '@/lib/raiox';
import BarraProgresso from './BarraProgresso.vue';

describe('BarraProgresso', () => {
    it('mostra a coleta com página, percentual e tempo restante', () => {
        const w = mount(BarraProgresso, {
            props: {
                progresso: {
                    etapa: 'coleta',
                    percentual: 12,
                    paginas: 3,
                    paginasTotal: 18,
                    classificados: 0,
                    classificarTotal: 0,
                    decorridoSegundos: 15,
                    restanteSegundos: 400,
                },
            },
        });

        expect(w.text()).toContain('página 3 de 18');
        expect(w.text()).toContain('12%');
        expect(w.text()).toContain('Faltam cerca de 7 min');
        expect(w.find('[role="progressbar"]').attributes('aria-valuenow')).toBe(
            '12',
        );
    });

    it('mostra a leitura das decisões', () => {
        const w = mount(BarraProgresso, {
            props: {
                progresso: {
                    etapa: 'classificacao',
                    percentual: 60,
                    paginas: 18,
                    paginasTotal: 18,
                    classificados: 40,
                    classificarTotal: 100,
                    decorridoSegundos: 200,
                    restanteSegundos: 300,
                },
            },
        });

        expect(w.text()).toContain('Lendo as decisões: 40 de 100 processos');
    });

    it('formata durações', () => {
        expect(duracao(42)).toBe('42 s');
        expect(duracao(300)).toBe('5 min');
        expect(duracao(3900)).toBe('1 h 05 min');
        expect(duracao(null)).toBe('—');
    });
});
