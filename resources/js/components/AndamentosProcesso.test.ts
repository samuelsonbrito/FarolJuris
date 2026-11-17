import { flushPromises, mount } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import type { MovimentoDatajud, ProcessoDatajud } from '@/types/datajud';
import AndamentosProcesso from './AndamentosProcesso.vue';

function movimento(i: number): MovimentoDatajud {
    const dia = String(28 - i).padStart(2, '0');

    return {
        dataHora: `2026-10-${dia}T15:00:00Z`,
        codigo: 100 + i,
        nome: i === 0 ? 'Petição' : `Movimento ${i}`,
        complementos: i === 0 ? ['para decisão', 'sorteio'] : [],
        orgaoJulgador: '5º Vara do Juizado Especial Cível',
    };
}

const processo: ProcessoDatajud = {
    tribunal: 'TJAM',
    grau: 'JE',
    grauRotulo: 'Juizado Especial',
    numeroProcesso: '01234567820268041000',
    classe: 'Procedimento do Juizado Especial Cível',
    codigoClasse: 436,
    assuntos: ['Cláusulas Abusivas'],
    orgaoJulgador: '5º Vara do Juizado Especial Cível',
    dataAjuizamento: '2026-09-23T20:40:39Z',
    sistema: 'Eproc',
    formato: 'Eletrônico',
    atualizadoEm: '2026-11-04T02:48:47Z',
    movimentos: Array.from({ length: 12 }, (_, i) => movimento(i)),
};

function resposta(status: number, corpo: unknown) {
    return Promise.resolve(
        new Response(JSON.stringify(corpo), {
            status,
            headers: { 'Content-Type': 'application/json' },
        }),
    );
}

function montar() {
    return mount(AndamentosProcesso, {
        props: { tribunal: 'TJAM', numeroProcesso: '01234567820268041000' },
    });
}

describe('AndamentosProcesso', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal(
            'route',
            (nome: string, q: Record<string, string>) =>
                `/${nome}/${q.numero}?tribunal=${q.tribunal}`,
        );
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('só consulta quando a pessoa pede', async () => {
        const w = montar();

        expect(fetchMock).not.toHaveBeenCalled();
        expect(w.find('[data-testid="carregar-andamentos"]').exists()).toBe(
            true,
        );

        fetchMock.mockReturnValue(resposta(200, { data: [processo] }));
        await w.find('[data-testid="carregar-andamentos"]').trigger('click');

        expect(w.find('[data-testid="andamentos-carregando"]').exists()).toBe(
            true,
        );
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/processos.andamentos/01234567820268041000?tribunal=TJAM',
            expect.objectContaining({
                headers: { Accept: 'application/json' },
            }),
        );
    });

    it('mostra os dados do processo e os 10 andamentos mais recentes', async () => {
        fetchMock.mockReturnValue(resposta(200, { data: [processo] }));
        const w = montar();
        await w.find('[data-testid="carregar-andamentos"]').trigger('click');
        await flushPromises();

        const texto = w.text();
        expect(texto).toContain('Juizado Especial');
        expect(texto).toContain('Procedimento do Juizado Especial Cível');
        expect(texto).toContain('Cláusulas Abusivas');
        expect(texto).toContain('Ajuizado em 23/09/2026');
        expect(texto).toContain('atualizado no CNJ em');

        const itens = w.findAll('[data-testid="movimento"]');
        expect(itens).toHaveLength(10);
        expect(itens[0]!.text()).toContain('Petição');
        expect(itens[0]!.text()).toContain('para decisão · sorteio');
        expect(itens[0]!.text()).toContain('28/10/2026');

        await w.find('[data-testid="alternar-movimentos"]').trigger('click');
        expect(w.findAll('[data-testid="movimento"]')).toHaveLength(12);
        expect(w.text()).toContain('Mostrar menos');
    });

    it('avisa quando o DataJud não tem o processo', async () => {
        fetchMock.mockReturnValue(resposta(200, { data: [] }));
        const w = montar();
        await w.find('[data-testid="carregar-andamentos"]').trigger('click');
        await flushPromises();

        expect(w.find('[data-testid="andamentos-vazio"]').text()).toContain(
            'segredo de justiça',
        );
    });

    it('mostra a mensagem do servidor e permite tentar de novo', async () => {
        fetchMock.mockReturnValueOnce(
            resposta(503, {
                erro: {
                    tipo: 'indisponivel',
                    mensagem: 'O DataJud (CNJ) está sobrecarregado agora.',
                },
            }),
        );
        const w = montar();
        await w.find('[data-testid="carregar-andamentos"]').trigger('click');
        await flushPromises();

        expect(w.find('[data-testid="andamentos-erro"]').text()).toContain(
            'sobrecarregado',
        );

        fetchMock.mockReturnValueOnce(resposta(200, { data: [processo] }));
        await w.find('[data-testid="andamentos-erro"] button').trigger('click');
        await flushPromises();

        expect(w.findAll('[data-testid="movimento"]').length).toBe(10);
    });

    it('explica o limite de consultas por minuto', async () => {
        fetchMock.mockReturnValue(resposta(429, {}));
        const w = montar();
        await w.find('[data-testid="carregar-andamentos"]').trigger('click');
        await flushPromises();

        expect(w.find('[data-testid="andamentos-erro"]').text()).toContain(
            'Muitas consultas seguidas',
        );
    });

    it('volta ao início quando muda o processo', async () => {
        fetchMock.mockReturnValue(resposta(200, { data: [processo] }));
        const w = montar();
        await w.find('[data-testid="carregar-andamentos"]').trigger('click');
        await flushPromises();

        await w.setProps({ numeroProcesso: '07654321020258041000' });

        expect(w.find('[data-testid="carregar-andamentos"]').exists()).toBe(
            true,
        );
        expect(w.findAll('[data-testid="movimento"]')).toHaveLength(0);
    });
});
