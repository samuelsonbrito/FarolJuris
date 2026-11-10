<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Ban,
    CircleCheck,
    CircleMinus,
    CircleX,
    Clock,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import BarraSegmentada from '@/components/admin/BarraSegmentada.vue';
import type { Parte } from '@/components/admin/BarraSegmentada.vue';
import CartaoGrafico from '@/components/admin/CartaoGrafico.vue';
import CartaoIndicador from '@/components/admin/CartaoIndicador.vue';
import FiltroPeriodo from '@/components/admin/FiltroPeriodo.vue';
import GraficoColunas from '@/components/admin/GraficoColunas.vue';
import ListaBarras from '@/components/admin/ListaBarras.vue';
import { queryPeriodo, visitarAdmin } from '@/lib/admin';
import { formatarData } from '@/lib/cnj';
import { dataCurta, formatarDataHora, formatarValor } from '@/lib/formatar';
import type { DashboardProps, Fatia } from '@/types/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Administração', href: '/admin' }],
    },
});

const props = defineProps<DashboardProps>();

const carregando = ref(false);

function mudarPeriodo(query: Record<string, string>) {
    visitarAdmin('admin.dashboard', query, (c) => (carregando.value = c));
}

const buscasPorDia = computed(() =>
    props.porDia.map((d) => ({
        rotulo: dataCurta(d.data),
        dica: formatarData(d.data),
        valor: d.buscas,
    })),
);
const visitantesPorDia = computed(() =>
    props.porDia.map((d) => ({
        rotulo: dataCurta(d.data),
        dica: formatarData(d.data),
        valor: d.visitantes,
    })),
);
const porHora = computed(() =>
    props.buscasPorHora.map((h) => ({
        rotulo: `${h.hora}h`,
        dica: `${h.hora}h–${h.hora + 1}h`,
        valor: h.total,
    })),
);

function tabelaDeFatias(rotulo: string, fatias: Fatia[]) {
    return {
        cabecalhos: [rotulo, 'Total'],
        linhas: fatias.map((f) => [f.rotulo, formatarValor(f.total)]),
    };
}

const tabelaDias = computed(() => ({
    cabecalhos: ['Dia', 'Buscas', 'Visitantes'],
    linhas: props.porDia.map((d) => [
        formatarData(d.data),
        formatarValor(d.buscas),
        formatarValor(d.visitantes),
    ]),
}));

const coresStatus: Record<string, { cor: string; icone: Parte['icone'] }> = {
    ok: { cor: 'var(--viz-bom)', icone: CircleCheck },
    vazio: { cor: 'var(--viz-neutro)', icone: CircleMinus },
    invalido: { cor: 'var(--viz-alerta)', icone: TriangleAlert },
    erro: { cor: 'var(--viz-critico)', icone: CircleX },
};

const coresCnj: Record<string, { cor: string; icone: Parte['icone'] }> = {
    sucesso: { cor: 'var(--viz-bom)', icone: CircleCheck },
    limitado: { cor: 'var(--viz-alerta)', icone: Clock },
    bloqueado: { cor: 'var(--viz-critico)', icone: Ban },
    indisponivel: { cor: 'var(--viz-critico)', icone: CircleX },
    recusado: { cor: 'var(--viz-critico)', icone: TriangleAlert },
    invalido: { cor: 'var(--viz-neutro)', icone: CircleMinus },
};

function partes(
    fatias: Fatia[],
    cores: typeof coresStatus,
    ordem: string[],
    rotulos: Record<string, string>,
): Parte[] {
    return ordem.map((chave) => ({
        chave,
        rotulo: rotulos[chave] ?? chave,
        total: fatias.find((f) => f.chave === chave)?.total ?? 0,
        cor: cores[chave]!.cor,
        icone: cores[chave]!.icone,
    }));
}

const statusBuscas = computed(() =>
    partes(
        props.buscasPorStatus,
        coresStatus,
        ['ok', 'vazio', 'invalido', 'erro'],
        {
            ok: 'Com resultados',
            vazio: 'Sem resultados',
            invalido: 'Filtro inválido',
            erro: 'Erro no CNJ',
        },
    ),
);

const resultadoCnj = computed(() =>
    partes(
        props.cnj.porResultado,
        coresCnj,
        [
            'sucesso',
            'limitado',
            'indisponivel',
            'bloqueado',
            'recusado',
            'invalido',
        ],
        Object.fromEntries(
            props.cnj.porResultado.map((f) => [f.chave, f.rotulo]),
        ),
    ).filter((p) => p.chave === 'sucesso' || p.total > 0),
);

const abasTermos = [
    { chave: 'oab', rotulo: 'OAB' },
    { chave: 'parte', rotulo: 'Parte' },
    { chave: 'advogado', rotulo: 'Advogado' },
    { chave: 'processo', rotulo: 'Processo' },
    { chave: 'texto', rotulo: 'Teor' },
] as const;
const abaTermos = ref<(typeof abasTermos)[number]['chave']>('oab');

const ind = computed(() => props.indicadores);
</script>

<template>
    <Head title="Administração" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6"
        :class="carregando && 'opacity-60 transition-opacity'"
    >
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Administração</h1>
            <p class="text-sm text-muted-foreground">
                Uso da plataforma de {{ formatarData(periodo.inicio) }} a
                {{ formatarData(periodo.fim) }} ({{ periodo.dias }}
                {{ periodo.dias === 1 ? 'dia' : 'dias' }}). Robôs não entram nas
                contagens.
            </p>
        </header>

        <AdminNav :query="queryPeriodo(periodo)" />
        <FiltroPeriodo
            :periodo="periodo"
            :presets="presets"
            @mudar="mudarPeriodo"
        />

        <section aria-labelledby="titulo-consulta" class="grid gap-3">
            <h2
                id="titulo-consulta"
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Consulta pública
            </h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                <CartaoIndicador rotulo="Buscas" :indicador="ind.buscas" />
                <CartaoIndicador
                    rotulo="Visitantes únicos"
                    :indicador="ind.visitantes"
                />
                <CartaoIndicador
                    rotulo="Buscas com resultado"
                    :indicador="ind.taxaResultado"
                    formato="percentual"
                />
                <CartaoIndicador
                    rotulo="Tempo médio da busca"
                    :indicador="ind.tempoMedioMs"
                    formato="ms"
                />
                <CartaoIndicador
                    rotulo="Respondidas pelo cache"
                    :indicador="ind.taxaCache"
                    formato="percentual"
                />
            </div>
        </section>

        <section aria-labelledby="titulo-contas" class="grid gap-3">
            <h2
                id="titulo-contas"
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Contas e monitoramento
            </h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <CartaoIndicador
                    rotulo="Novos cadastros"
                    :indicador="ind.cadastros"
                />
                <CartaoIndicador
                    rotulo="Usuários ativos"
                    :indicador="ind.usuariosAtivos"
                />
                <CartaoIndicador
                    rotulo="Alvos ativos"
                    :indicador="ind.alvosAtivos"
                    detalhe="agora"
                />
                <CartaoIndicador
                    rotulo="Alertas gerados"
                    :indicador="ind.alertas"
                />
                <CartaoIndicador
                    rotulo="Notificações enviadas"
                    :indicador="ind.notificacoes"
                />
                <CartaoIndicador
                    rotulo="Exportações"
                    :indicador="ind.exportacoes"
                />
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <CartaoGrafico titulo="Buscas por dia" :tabela="tabelaDias">
                <GraficoColunas :pontos="buscasPorDia" serie="Buscas" />
            </CartaoGrafico>
            <CartaoGrafico
                titulo="Visitantes únicos por dia"
                descricao="Quem buscou ou abriu alguma página"
                :tabela="tabelaDias"
            >
                <GraficoColunas :pontos="visitantesPorDia" serie="Visitantes" />
            </CartaoGrafico>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <CartaoGrafico
                titulo="Resultado das buscas"
                :tabela="tabelaDeFatias('Resultado', buscasPorStatus)"
            >
                <BarraSegmentada :partes="statusBuscas" />
            </CartaoGrafico>
            <CartaoGrafico
                titulo="Buscas por critério"
                descricao="O filtro principal de cada busca"
            >
                <ListaBarras :itens="buscasPorCriterio" />
            </CartaoGrafico>
            <CartaoGrafico
                titulo="Horário das buscas"
                descricao="Hora local, somando o período"
                :tabela="{
                    cabecalhos: ['Hora', 'Buscas'],
                    linhas: buscasPorHora.map((h) => [
                        `${h.hora}h`,
                        formatarValor(h.total),
                    ]),
                }"
            >
                <GraficoColunas
                    :pontos="porHora"
                    serie="Buscas"
                    :altura="160"
                />
            </CartaoGrafico>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <CartaoGrafico
                titulo="Mais buscados"
                descricao="Os 10 termos mais frequentes por tipo"
            >
                <div
                    class="mb-3 inline-flex rounded-lg border p-0.5"
                    role="tablist"
                    aria-label="Tipo de termo"
                >
                    <button
                        v-for="aba in abasTermos"
                        :key="aba.chave"
                        type="button"
                        role="tab"
                        class="rounded-md px-2.5 py-1 text-xs"
                        :class="
                            abaTermos === aba.chave
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-accent'
                        "
                        :aria-selected="abaTermos === aba.chave"
                        @click="abaTermos = aba.chave"
                    >
                        {{ aba.rotulo }}
                    </button>
                </div>
                <ListaBarras
                    :itens="termos[abaTermos]"
                    :com-percentual="false"
                    vazio="Nenhuma busca desse tipo no período."
                />
            </CartaoGrafico>
            <CartaoGrafico titulo="Tribunais mais buscados">
                <ListaBarras
                    :itens="tribunais"
                    vazio="Nenhuma busca filtrou por tribunal."
                />
            </CartaoGrafico>
            <CartaoGrafico titulo="UF das OABs buscadas">
                <ListaBarras
                    :itens="ufs"
                    vazio="Nenhuma busca por OAB no período."
                />
            </CartaoGrafico>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <CartaoGrafico titulo="Dispositivos" descricao="Páginas vistas">
                <ListaBarras :itens="dispositivos" />
            </CartaoGrafico>
            <CartaoGrafico titulo="Navegadores">
                <ListaBarras :itens="navegadores" />
            </CartaoGrafico>
            <CartaoGrafico titulo="Sistemas">
                <ListaBarras :itens="sistemas" />
            </CartaoGrafico>
            <CartaoGrafico
                titulo="De onde vêm"
                descricao="Site de origem do link"
            >
                <ListaBarras
                    :itens="referencias"
                    vazio="Nenhum acesso vindo de outro site."
                />
            </CartaoGrafico>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <CartaoGrafico titulo="Páginas mais vistas" class="lg:col-span-1">
                <ListaBarras :itens="paginas" />
            </CartaoGrafico>
            <CartaoGrafico
                titulo="API do CNJ"
                descricao="Requisições reais (o cache não conta)"
                class="lg:col-span-2"
            >
                <div class="grid gap-5 md:grid-cols-[1fr_1fr]">
                    <div class="grid content-start gap-4">
                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Chamadas
                                </dt>
                                <dd class="text-xl font-semibold tabular-nums">
                                    {{ formatarValor(ind.chamadasCnj.valor) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Falhas
                                </dt>
                                <dd class="text-xl font-semibold tabular-nums">
                                    {{
                                        formatarValor(
                                            ind.falhasCnj.valor,
                                            'percentual',
                                        )
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Latência média
                                </dt>
                                <dd class="text-xl font-semibold tabular-nums">
                                    {{
                                        formatarValor(cnj.latenciaMediaMs, 'ms')
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Latência p95
                                </dt>
                                <dd class="text-xl font-semibold tabular-nums">
                                    {{ formatarValor(cnj.latenciaP95Ms, 'ms') }}
                                </dd>
                            </div>
                        </dl>
                        <p class="text-xs text-muted-foreground">
                            Limite restante na última resposta:
                            <strong class="text-foreground tabular-nums">{{
                                formatarValor(cnj.ratelimitRestante)
                            }}</strong>
                            <template v-if="cnj.ratelimitEm">
                                ({{
                                    formatarDataHora(cnj.ratelimitEm)
                                }})</template
                            >
                        </p>
                        <BarraSegmentada :partes="resultadoCnj" />
                    </div>
                    <div>
                        <h3
                            class="mb-3 text-xs font-medium text-muted-foreground"
                        >
                            Quem chamou
                        </h3>
                        <ListaBarras :itens="cnj.porOrigem" />
                    </div>
                </div>
            </CartaoGrafico>
        </div>
    </div>
</template>
