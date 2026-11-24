<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    FileSpreadsheet,
    FileText,
    ThumbsDown,
    ThumbsUp,
    TriangleAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import BarraResultados from '@/components/raiox/BarraResultados.vue';
import CalculadoraCusto from '@/components/raiox/CalculadoraCusto.vue';
import TabelaGrupos from '@/components/raiox/TabelaGrupos.vue';
import { Button } from '@/components/ui/button';
import { formatarData } from '@/lib/cnj';
import { percentual, SEGMENTOS } from '@/lib/raiox';
import type { MetricasRaioX, RelatorioRaioX } from '@/types/raiox';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Raio-X', href: '/raio-x' }],
    },
});

const props = defineProps<{
    relatorio: RelatorioRaioX;
    metricas: MetricasRaioX;
}>();

const vg = computed(() => props.metricas.visaoGeral);
const titulo = computed(
    () =>
        props.relatorio.nomeAdvogado ??
        `OAB ${props.relatorio.oab}/${props.relatorio.uf}`,
);
const periodo = computed(
    () =>
        `${formatarData(props.relatorio.inicio)} a ${formatarData(props.relatorio.fim)}`,
);
const temVarasCapital = computed(
    () =>
        props.metricas.varasCapital.civeis.length > 0 ||
        props.metricas.varasCapital.juizados.length > 0,
);

const cartoes = computed(() => [
    {
        rotulo: 'Processos decididos',
        valor: vg.value.decididos,
        detalhe:
            vg.value.pendentes > 0
                ? `+ ${vg.value.pendentes} em conferência`
                : `${vg.value.ativos} ativos no período`,
    },
    { rotulo: 'Êxito', valor: vg.value.exito, detalhe: 'procedente / provido' },
    {
        rotulo: 'Êxito parcial',
        valor: vg.value.parcial,
        detalhe: 'parcialmente procedente',
    },
    { rotulo: 'Acordos', valor: vg.value.acordo, detalhe: 'homologados' },
    {
        rotulo: 'Insucesso',
        valor: vg.value.insucesso,
        detalhe:
            vg.value.extincoes > 0
                ? `inclui ${vg.value.extincoes} extinções sem mérito`
                : 'improcedente / desprovido',
    },
]);
</script>

<template>
    <Head :title="`Raio-X · OAB ${relatorio.oab}/${relatorio.uf}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Link
                :href="route('raiox.index')"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Raio-X
            </Link>
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline" size="sm">
                    <a
                        :href="route('raiox.planilha', relatorio.id)"
                        data-testid="baixar-planilha"
                    >
                        <FileSpreadsheet class="size-4" aria-hidden="true" />
                        Planilha (Excel)
                    </a>
                </Button>
                <Button as-child variant="outline" size="sm">
                    <a
                        :href="route('raiox.pdf', relatorio.id)"
                        data-testid="baixar-pdf"
                    >
                        <FileText class="size-4" aria-hidden="true" />
                        PDF
                    </a>
                </Button>
            </div>
        </div>

        <p
            v-if="relatorio.status !== 'liberado'"
            class="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
            data-testid="previa-admin"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            Prévia do administrador: o cliente só vê este relatório depois de
            liberado ({{ relatorio.statusRotulo.toLowerCase() }}).
        </p>

        <header class="grid gap-1 border-b pb-5">
            <p
                class="text-xs font-semibold tracking-wide text-[var(--marca-ambar)] uppercase"
            >
                Relatório de desempenho processual · {{ periodo }}
            </p>
            <h1
                class="text-2xl font-semibold tracking-tight sm:text-3xl"
                data-testid="titulo-raiox"
            >
                {{ titulo }}
            </h1>
            <p class="text-sm text-muted-foreground">
                OAB {{ relatorio.oab }}/{{ relatorio.uf }} · Decisões publicadas
                no DJEN de {{ periodo }} · Fonte: FarolJuris
            </p>
        </header>

        <section aria-labelledby="rx-visao" class="grid gap-3">
            <h2 id="rx-visao" class="text-xl font-semibold tracking-tight">
                Visão geral
            </h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                <div
                    class="rounded-xl bg-[var(--marca-marinho)] p-4 text-white shadow-xs"
                    data-testid="taxa-exito"
                >
                    <p class="text-xs text-white/70">Taxa de êxito*</p>
                    <p class="text-3xl font-semibold tabular-nums">
                        {{ percentual(vg.taxa) }}
                    </p>
                    <p class="text-xs text-white/70">
                        {{ vg.favoraveis }} de {{ vg.decididos }} decididos
                    </p>
                </div>
                <div
                    v-for="c in cartoes"
                    :key="c.rotulo"
                    class="rounded-xl border bg-card p-4 shadow-xs"
                >
                    <p class="text-xs text-muted-foreground">{{ c.rotulo }}</p>
                    <p class="text-3xl font-semibold tabular-nums">
                        {{ c.valor }}
                    </p>
                    <p class="text-xs text-muted-foreground">{{ c.detalhe }}</p>
                </div>
            </div>
            <p class="text-xs text-muted-foreground">
                * Taxa de êxito = (êxito + êxito parcial) ÷ processos decididos.
                Cada processo conta uma vez, pela decisão mais recente do
                período, lida do ponto de vista do cliente.
            </p>
        </section>

        <section aria-labelledby="rx-instancia" class="grid gap-3">
            <h2 id="rx-instancia" class="text-xl font-semibold tracking-tight">
                Onde está a diferença: 1º grau × recursos
            </h2>
            <ul class="flex flex-wrap gap-4 text-xs text-muted-foreground">
                <li
                    v-for="s in SEGMENTOS"
                    :key="s.chave"
                    class="inline-flex items-center gap-1.5"
                >
                    <span
                        class="size-3 rounded-sm"
                        :style="{ background: s.cor }"
                        aria-hidden="true"
                    />
                    {{ s.rotulo }}
                </li>
            </ul>
            <div class="rounded-xl border bg-card px-4 py-2 shadow-xs">
                <BarraResultados
                    v-for="l in metricas.porInstancia"
                    :key="l.rotulo"
                    :linha="l"
                />
            </div>
        </section>

        <section aria-labelledby="rx-vara" class="grid gap-3">
            <h2 id="rx-vara" class="text-xl font-semibold tracking-tight">
                Por vara e por colegiado
            </h2>
            <div class="grid gap-4 lg:grid-cols-2">
                <TabelaGrupos
                    titulo="Por vara de origem (resultado final)"
                    coluna="Órgão"
                    :linhas="metricas.porOrigem"
                />
                <TabelaGrupos
                    titulo="Recursos – quem julgou"
                    coluna="Órgão"
                    :linhas="metricas.porColegiado"
                />
            </div>
        </section>

        <section
            v-if="temVarasCapital"
            aria-labelledby="rx-varas"
            class="grid gap-3"
        >
            <h2 id="rx-varas" class="text-xl font-semibold tracking-tight">
                Vara a vara em
                {{ metricas.varasCapital.capital ?? 'na capital' }}
            </h2>
            <div class="grid gap-4 lg:grid-cols-2">
                <TabelaGrupos
                    titulo="Varas cíveis"
                    coluna="Vara"
                    :linhas="metricas.varasCapital.civeis"
                />
                <TabelaGrupos
                    titulo="Juizados Especiais Cíveis"
                    coluna="Vara"
                    :linhas="metricas.varasCapital.juizados"
                />
            </div>
            <p class="text-xs text-muted-foreground">
                Com 1 a 3 casos por vara, são sinais para observar, não
                conclusões.
            </p>
        </section>

        <section aria-labelledby="rx-tema" class="grid gap-3">
            <h2 id="rx-tema" class="text-xl font-semibold tracking-tight">
                Por tema
            </h2>
            <TabelaGrupos coluna="Tema" :linhas="metricas.porTema" completa />
            <p class="text-xs text-muted-foreground">
                "Bancário – tema não informado": a publicação é só a ata da
                sessão ou um voto curto, sem dizer do que trata a ação. Temas
                com 1 a 4 casos não permitem conclusão segura.
            </p>
        </section>

        <section aria-labelledby="rx-parte" class="grid gap-3">
            <h2 id="rx-parte" class="text-xl font-semibold tracking-tight">
                Por parte contrária
            </h2>
            <TabelaGrupos
                coluna="Parte contrária"
                :linhas="metricas.porParte"
                completa
            />
        </section>

        <section aria-labelledby="rx-custo" class="grid gap-3">
            <h2 id="rx-custo" class="text-xl font-semibold tracking-tight">
                Quanto cada êxito precisa pagar
            </h2>
            <CalculadoraCusto
                :relatorio-id="relatorio.id"
                :custo="metricas.custo"
                editavel
            />
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div
                class="rounded-xl border bg-card p-5 shadow-xs"
                data-testid="vai-melhor"
            >
                <h2
                    class="mb-3 inline-flex items-center gap-2 font-semibold text-[var(--raiox-exito)]"
                >
                    <ThumbsUp class="size-4" aria-hidden="true" />
                    Onde vai melhor
                </h2>
                <ul class="grid list-disc gap-1.5 pl-5 text-sm">
                    <li v-for="t in metricas.destaques.melhor" :key="t">
                        {{ t }}
                    </li>
                    <li
                        v-if="!metricas.destaques.melhor.length"
                        class="list-none text-muted-foreground"
                    >
                        Nenhum grupo com casos suficientes acima de 40%.
                    </li>
                </ul>
            </div>
            <div
                class="rounded-xl border bg-card p-5 shadow-xs"
                data-testid="vai-pior"
            >
                <h2
                    class="mb-3 inline-flex items-center gap-2 font-semibold text-[var(--raiox-insucesso)]"
                >
                    <ThumbsDown class="size-4" aria-hidden="true" />
                    Onde vai pior
                </h2>
                <ul class="grid list-disc gap-1.5 pl-5 text-sm">
                    <li v-for="t in metricas.destaques.pior" :key="t">
                        {{ t }}
                    </li>
                    <li
                        v-if="!metricas.destaques.pior.length"
                        class="list-none text-muted-foreground"
                    >
                        Nenhum grupo com casos suficientes abaixo de 40%.
                    </li>
                </ul>
            </div>
        </section>

        <section
            aria-labelledby="rx-ler"
            class="grid gap-2 border-t pt-5 text-sm text-muted-foreground"
        >
            <h2 id="rx-ler" class="font-semibold text-foreground">
                Como ler estes números
            </h2>
            <p>
                Base: {{ vg.publicacoes }} publicações em
                {{ vg.ativos }} processos ({{ relatorio.oab }}/{{
                    relatorio.uf
                }}). Entraram {{ vg.decididos }} processos com decisão de
                mérito, acordo ou extinção; {{ vg.semDecisao }} tiveram só
                publicações sem resultado no período.
                <template v-if="vg.pendentes > 0">
                    {{ vg.pendentes }} casos estão marcados para conferência nos
                    autos ({{ metricas.conferencia.join(', ') }}) e ficam fora
                    das taxas.
                </template>
            </p>
            <p>
                Fonte: publicações oficiais e públicas do DJEN. Decisões ainda
                não publicadas, acordos extrajudiciais e valores recebidos não
                entram na análise. O Raio-X descreve o passado e orienta
                decisões; não garante êxito em processos futuros. As métricas
                são ferramenta de gestão interna e não devem ser usadas em
                anúncios (Provimento 205/2021 da OAB).
            </p>
        </section>
    </div>
</template>
