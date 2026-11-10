import { describe, expect, it } from 'vite-plus/test';
import { atalhoAtivo, intervaloDoAtalho } from './datas';

// 10/11/2026 (terça), meio-dia no horário local.
const hoje = new Date(2026, 10, 10, 12, 0, 0);

describe('intervaloDoAtalho', () => {
    it('calcula cada atalho em datas de calendário', () => {
        expect(intervaloDoAtalho('hoje', hoje)).toEqual({
            inicio: '2026-11-10',
            fim: '2026-11-10',
        });
        expect(intervaloDoAtalho('ontem', hoje)).toEqual({
            inicio: '2026-11-09',
            fim: '2026-11-09',
        });
        expect(intervaloDoAtalho('7dias', hoje)).toEqual({
            inicio: '2026-11-04',
            fim: '2026-11-10',
        });
        expect(intervaloDoAtalho('30dias', hoje)).toEqual({
            inicio: '2026-10-12',
            fim: '2026-11-10',
        });
        expect(intervaloDoAtalho('esteMes', hoje)).toEqual({
            inicio: '2026-11-01',
            fim: '2026-11-10',
        });
        expect(intervaloDoAtalho('mesPassado', hoje)).toEqual({
            inicio: '2026-10-01',
            fim: '2026-10-31',
        });
        expect(intervaloDoAtalho('semDatas', hoje)).toEqual({
            inicio: null,
            fim: null,
        });
    });

    it('vira o ano no mês passado de janeiro', () => {
        expect(intervaloDoAtalho('mesPassado', new Date(2027, 0, 5))).toEqual({
            inicio: '2026-12-01',
            fim: '2026-12-31',
        });
    });
});

describe('atalhoAtivo', () => {
    it('reconhece o atalho do intervalo atual', () => {
        expect(atalhoAtivo('2026-11-04', '2026-11-10', hoje)).toBe('7dias');
        expect(atalhoAtivo(null, null, hoje)).toBe('semDatas');
        expect(atalhoAtivo('2026-11-02', '2026-11-10', hoje)).toBeNull();
    });
});
