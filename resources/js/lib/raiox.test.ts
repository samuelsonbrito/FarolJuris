import { describe, expect, it } from 'vite-plus/test';
import { calcularCusto, partes, percentual } from './raiox';

describe('lib/raiox', () => {
    it('formata a taxa como percentual inteiro', () => {
        expect(percentual(0.5333)).toBe('53%');
        expect(percentual(null)).toBe('—');
    });

    it('calcula o custo como o relatório modelo (289 ativos, 33 êxitos, R$ 16 mil/mês)', () => {
        const c = calcularCusto(289, 33, 16000, null, 7000);

        expect(c.custoTotal).toBe(192000);
        expect(Math.round(c.custoPorProcesso!)).toBe(664);
        expect(Math.round(c.honorarioMinimo!)).toBe(5818);
        expect(Math.round(c.margem!)).toBe(1182);
        expect(c.processosPorExito?.toFixed(1)).toBe('8.8');
    });

    it('sem custo informado ou sem êxito, deixa em branco', () => {
        expect(
            calcularCusto(10, 2, null, null, null).honorarioMinimo,
        ).toBeNull();
        expect(
            calcularCusto(10, 0, 1000, null, null).honorarioMinimo,
        ).toBeNull();
    });

    it('soma o custo variável por processo ativo', () => {
        expect(calcularCusto(10, 2, 1000, 50, null).custoTotal).toBe(12500);
    });

    it('divide a barra pelos decididos', () => {
        const p = partes({
            decididos: 4,
            exito: 1,
            parcial: 1,
            acordo: 0,
            insucesso: 2,
            favoraveis: 2,
            taxa: 0.5,
        });

        expect(p.map((s) => s.largura)).toEqual([25, 25, 0, 50]);
    });
});
