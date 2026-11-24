<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import CartaoGrafico from '@/components/admin/CartaoGrafico.vue';
import FiltroPeriodo from '@/components/admin/FiltroPeriodo.vue';
import GraficoColunas from '@/components/admin/GraficoColunas.vue';
import EstadoVazio from '@/components/EstadoVazio.vue';
import Paginacao from '@/components/Paginacao.vue';
import { Badge } from '@/components/ui/badge';
import { queryPeriodo, visitarAdmin } from '@/lib/admin';
import { formatarDataHora, formatarValor } from '@/lib/formatar';
import type { Periodo } from '@/types/admin';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administração', href: '/admin' },
            { title: 'Custos de IA', href: '/admin/custos-ia' },
        ],
    },
});

interface Chamada {
    id: number;
    quando: string;
    relatorio: { id: number; oab: string } | null;
    modelo: string;
    tokensEntrada: number;
    tokensCacheLeitura: number;
    tokensCacheEscrita: number;
    tokensSaida: number;
    usd: number | null;
    brl: number | null;
    cotacao: number;
    resultado: string;
    duracaoMs: number | null;
}

const props = defineProps<{
    periodo: Periodo;
    presets: Record<string, string>;
    cotacao: number;
    precos: Record<
        string,
        {
            entrada: number;
            saida: number;
            cache_leitura: number;
            cache_escrita: number;
        }
    >;
    modeloAtual: string;
    resumo: {
        chamadas: number;
        relatorios: number;
        usd: number;
        brl: number;
        tokens: {
            entrada: number;
            cacheLeitura: number;
            cacheEscrita: number;
            saida: number;
        };
        mediaUsdPorChamada: number | null;
        mediaBrlPorChamada: number | null;
        mediaBrlPorRelatorio: number | null;
        falhas: number;
        semPreco: number;
        duracaoMediaMs: number | null;
    };
    porDia: { data: string; chamadas: number; usd: number; brl: number }[];
    porRelatorio: {
        relatorioId: number | null;
        oab: string | null;
        conta: string | null;
        chamadas: number;
        tokensEntrada: number;
        tokensSaida: number;
        usd: number;
        brl: number;
    }[];
    chamadas: {
        data: Chamada[];
        meta: { pagina: number; ultimaPagina: number; total: number };
    };
}>();

const carregando = ref(false);

function visitar(mudancas: Record<string, string | number | null> = {}) {
    visitarAdmin(
        'admin.custos-ia',
        { ...queryPeriodo(props.periodo), ...mudancas },
        (c) => (carregando.value = c),
    );
}

const diaCurto = (d: string) => `${d.slice(8, 10)}/${d.slice(5, 7)}`;
const diaLongo = (d: string) =>
    `${d.slice(8, 10)}/${d.slice(5, 7)}/${d.slice(0, 4)}`;

const custoPorDia = computed(() =>
    props.porDia.map((d) => ({
        rotulo: diaCurto(d.data),
        dica: diaLongo(d.data),
        valor: d.brl,
    })),
);

const tabelaPorDia = computed(() => ({
    cabecalhos: ['Dia', 'Chamadas', 'US$', 'R$'],
    linhas: props.porDia.map((d) => [
        diaLongo(d.data),
        formatarValor(d.chamadas),
        formatarValor(d.usd, 'usd'),
        formatarValor(d.brl, 'brl'),
    ]),
}));

const totalTokens = computed(
    () =>
        props.resumo.tokens.entrada +
        props.resumo.tokens.cacheLeitura +
        props.resumo.tokens.cacheEscrita +
        props.resumo.tokens.saida,
);

const cartoes = computed(() => [
    {
        rotulo: 'Gasto em reais',
        valor: formatarValor(props.resumo.brl, 'brl'),
        detalhe: `cotação US$ 1 = ${formatarValor(props.cotacao, 'brl')}`,
        destaque: true,
    },
    {
        rotulo: 'Gasto em dólares',
        valor: formatarValor(props.resumo.usd, 'usd'),
        detalhe: `${formatarValor(props.resumo.chamadas)} chamadas`,
    },
    {
        rotulo: 'Média por Raio-X',
        valor: formatarValor(props.resumo.mediaBrlPorRelatorio, 'brl'),
        detalhe: `${formatarValor(props.resumo.relatorios)} Raio-X no período`,
    },
    {
        rotulo: 'Média por chamada',
        valor: formatarValor(props.resumo.mediaBrlPorChamada, 'brl'),
        detalhe:
            props.resumo.duracaoMediaMs !== null
                ? `${formatarValor(props.resumo.duracaoMediaMs, 'ms')} em média`
                : '—',
    },
    {
        rotulo: 'Tokens',
        valor: formatarValor(totalTokens.value),
        detalhe: `${formatarValor(props.resumo.tokens.saida)} de saída`,
    },
    {
        rotulo: 'Falhas',
        valor: formatarValor(props.resumo.falhas),
        detalhe: 'recusas, erros e respostas inválidas',
    },
]);

const rotulosResultado: Record<string, string> = {
    ok: 'OK',
    recusa: 'Recusa',
    invalido: 'Inválida',
    erro: 'Erro',
};

const brl4 = (v: number | null) =>
    v === null
        ? '—'
        : v.toLocaleString('pt-BR', {
              style: 'currency',
              currency: 'BRL',
              minimumFractionDigits: 4,
              maximumFractionDigits: 4,
          });
const usd4 = (v: number | null) =>
    v === null
        ? '—'
        : v.toLocaleString('en-US', {
              style: 'currency',
              currency: 'USD',
              minimumFractionDigits: 4,
              maximumFractionDigits: 4,
          });
</script>

<template>
    <Head title="Custos de IA · Administração" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 md:p-6"
        :class="carregando && 'opacity-60 transition-opacity'"
    >
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Custos de IA</h1>
            <p class="text-sm text-muted-foreground">
                Tokens gastos e valores, em dólar e em real, de cada chamada à
                IA (classificação das decisões do Raio-X).
            </p>
        </header>

        <AdminNav :query="queryPeriodo(periodo)" />

        <FiltroPeriodo
            :periodo="periodo"
            :presets="presets"
            @mudar="
                (q) =>
                    visitarAdmin('admin.custos-ia', q, (c) => (carregando = c))
            "
        />

        <dl class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
            <div
                v-for="c in cartoes"
                :key="c.rotulo"
                class="rounded-xl p-4 shadow-xs"
                :class="
                    c.destaque
                        ? 'bg-[var(--marca-marinho)] text-white'
                        : 'border bg-card'
                "
                :data-testid="c.destaque ? 'total-brl' : undefined"
            >
                <dt
                    class="text-xs font-medium"
                    :class="
                        c.destaque ? 'text-white/70' : 'text-muted-foreground'
                    "
                >
                    {{ c.rotulo }}
                </dt>
                <dd class="mt-1.5 text-xl font-semibold tabular-nums">
                    {{ c.valor }}
                </dd>
                <dd
                    class="text-xs"
                    :class="
                        c.destaque ? 'text-white/70' : 'text-muted-foreground'
                    "
                >
                    {{ c.detalhe }}
                </dd>
            </div>
        </dl>

        <p
            v-if="resumo.semPreco > 0"
            class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
        >
            {{ resumo.semPreco }} chamadas usaram um modelo sem preço
            configurado em config/raiox.php e ficaram fora dos valores.
        </p>

        <CartaoGrafico titulo="Gasto por dia (R$)" :tabela="tabelaPorDia">
            <GraficoColunas
                :pontos="custoPorDia"
                serie="Gasto em reais"
                formato="brl"
            />
        </CartaoGrafico>

        <div class="grid gap-4 lg:grid-cols-3">
            <section
                class="overflow-hidden rounded-xl border bg-card shadow-xs lg:col-span-2"
                aria-labelledby="por-relatorio"
            >
                <h2
                    id="por-relatorio"
                    class="border-b px-4 py-2.5 text-sm font-semibold"
                >
                    Por Raio-X
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-2 font-medium">
                                    OAB / conta
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Chamadas
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Tokens (entrada / saída)
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    US$
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    R$
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="r in porRelatorio"
                                :key="r.relatorioId ?? 'x'"
                                class="border-t"
                            >
                                <td class="px-4 py-2">
                                    <template v-if="r.relatorioId && r.oab">
                                        <Link
                                            :href="
                                                route(
                                                    'raiox.show',
                                                    r.relatorioId,
                                                )
                                            "
                                            class="font-medium hover:underline"
                                        >
                                            {{ r.oab }}
                                        </Link>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ r.conta }}
                                        </p>
                                    </template>
                                    <span v-else class="text-muted-foreground"
                                        >Raio-X excluído</span
                                    >
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ formatarValor(r.chamadas) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ formatarValor(r.tokensEntrada) }} /
                                    {{ formatarValor(r.tokensSaida) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ formatarValor(r.usd, 'usd') }}
                                </td>
                                <td
                                    class="px-4 py-2 text-right font-semibold tabular-nums"
                                >
                                    {{ formatarValor(r.brl, 'brl') }}
                                </td>
                            </tr>
                            <tr v-if="!porRelatorio.length">
                                <td
                                    colspan="5"
                                    class="px-4 py-3 text-center text-muted-foreground"
                                >
                                    Nenhuma chamada no período.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                class="grid content-start gap-3 rounded-xl border bg-card p-4 text-sm shadow-xs"
                aria-labelledby="tabela-precos"
            >
                <h2 id="tabela-precos" class="font-semibold">
                    Preços usados (US$ por milhão de tokens)
                </h2>
                <table class="w-full text-xs">
                    <thead class="text-left text-muted-foreground">
                        <tr>
                            <th class="py-1 font-medium">Modelo</th>
                            <th class="py-1 text-right font-medium">Entrada</th>
                            <th class="py-1 text-right font-medium">Saída</th>
                            <th class="py-1 text-right font-medium">Cache</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(p, modelo) in precos"
                            :key="modelo"
                            class="border-t"
                        >
                            <td class="py-1">
                                {{ modelo }}
                                <Badge
                                    v-if="modelo === modeloAtual"
                                    variant="secondary"
                                    class="ml-1"
                                    >em uso</Badge
                                >
                            </td>
                            <td class="py-1 text-right tabular-nums">
                                {{ p.entrada }}
                            </td>
                            <td class="py-1 text-right tabular-nums">
                                {{ p.saida }}
                            </td>
                            <td class="py-1 text-right tabular-nums">
                                {{ p.cache_leitura }} / {{ p.cache_escrita }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="text-xs text-muted-foreground">
                    Cache: leitura / escrita. Cotação: US$ 1 =
                    {{ formatarValor(cotacao, 'brl') }} (RAIOX_COTACAO_USD_BRL
                    no .env). Cada chamada guarda a cotação do momento.
                </p>
            </section>
        </div>

        <section class="grid gap-3" aria-labelledby="chamadas-ia">
            <h2 id="chamadas-ia" class="text-lg font-semibold tracking-tight">
                Chamadas ({{ formatarValor(chamadas.meta.total) }})
            </h2>
            <EstadoVazio
                v-if="!chamadas.data.length"
                titulo="Nenhuma chamada à IA no período"
                descricao="As chamadas aparecem aqui quando um Raio-X é gerado com a IA."
            />
            <div
                v-else
                class="overflow-x-auto rounded-xl border bg-card shadow-xs"
            >
                <table class="w-full text-sm">
                    <thead
                        class="bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">Quando</th>
                            <th class="px-3 py-2 font-medium">Raio-X</th>
                            <th class="px-3 py-2 font-medium">Modelo</th>
                            <th class="px-3 py-2 text-right font-medium">
                                Entrada
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Cache (leit. / escr.)
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Saída
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                US$
                            </th>
                            <th class="px-3 py-2 text-right font-medium">R$</th>
                            <th class="px-3 py-2 font-medium">Resultado</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Duração
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="c in chamadas.data"
                            :key="c.id"
                            class="border-t"
                            data-testid="chamada-ia"
                        >
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ formatarDataHora(c.quando) }}
                            </td>
                            <td class="px-3 py-2">
                                <Link
                                    v-if="c.relatorio"
                                    :href="route('raiox.show', c.relatorio.id)"
                                    class="hover:underline"
                                >
                                    {{ c.relatorio.oab }}
                                </Link>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-3 py-2 text-xs">{{ c.modelo }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ formatarValor(c.tokensEntrada) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ formatarValor(c.tokensCacheLeitura) }} /
                                {{ formatarValor(c.tokensCacheEscrita) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ formatarValor(c.tokensSaida) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ usd4(c.usd) }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-semibold tabular-nums"
                            >
                                {{ brl4(c.brl) }}
                            </td>
                            <td class="px-3 py-2">
                                <Badge
                                    :variant="
                                        c.resultado === 'ok'
                                            ? 'secondary'
                                            : 'destructive'
                                    "
                                >
                                    {{
                                        rotulosResultado[c.resultado] ??
                                        c.resultado
                                    }}
                                </Badge>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">
                                {{ formatarValor(c.duracaoMs, 'ms') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Paginacao
                v-if="chamadas.meta.ultimaPagina > 1"
                :pagina="chamadas.meta.pagina"
                :ultima-pagina="chamadas.meta.ultimaPagina"
                :desabilitado="carregando"
                @mudar="(p) => visitar({ pagina: p })"
            />
        </section>
    </div>
</template>
