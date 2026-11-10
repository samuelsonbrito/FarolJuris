import { describe, expect, it } from 'vite-plus/test';
import { corDoTipo, resumoPartes } from './comunicacao';

describe('corDoTipo', () => {
    it('dá uma cor por tipo, sem depender de maiúsculas', () => {
        expect(corDoTipo('Intimação')).toContain('amber');
        expect(corDoTipo('CITAÇÃO')).toContain('rose');
        expect(corDoTipo('Edital')).toContain('violet');
        expect(corDoTipo('Lista de distribuição')).toContain('sky');
        expect(corDoTipo('Pauta de julgamento')).toContain('emerald');
    });

    it('usa uma cor neutra para tipos desconhecidos', () => {
        expect(corDoTipo('Outro tipo')).toContain('bg-muted');
        expect(corDoTipo(null)).toContain('bg-muted');
    });
});

describe('resumoPartes', () => {
    it('separa ativo × passivo e conta o restante', () => {
        expect(
            resumoPartes([
                { nome: 'RÉU LTDA', polo: 'P' },
                { nome: 'BANCO S.A.', polo: 'A' },
                { nome: 'OUTRO AUTOR', polo: 'A' },
                { nome: 'TERCEIRO', polo: 'T' },
            ]),
        ).toEqual({
            ativo: ['BANCO S.A.'],
            passivo: ['RÉU LTDA'],
            restantes: 2,
        });
    });

    it('sem polo ativo, mostra os nomes na ordem', () => {
        expect(
            resumoPartes([
                { nome: 'A', polo: null },
                { nome: 'B', polo: 'P' },
                { nome: 'C', polo: null },
            ]),
        ).toEqual({ ativo: ['A'], passivo: ['B'], restantes: 1 });
    });

    it('lida com lista vazia', () => {
        expect(resumoPartes([])).toEqual({
            ativo: [],
            passivo: [],
            restantes: 0,
        });
    });
});
