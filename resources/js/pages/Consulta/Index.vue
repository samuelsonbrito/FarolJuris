<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, RotateCw, SearchX, ScrollText } from '@lucide/vue';
import { computed, ref, useTemplateRef, watch } from 'vue';
import BarraExportacao from '@/components/BarraExportacao.vue';
import LinhaComunicacao from '@/components/LinhaComunicacao.vue';
import SkeletonResultados from '@/components/consulta/SkeletonResultados.vue';
import EstadoVazio from '@/components/EstadoVazio.vue';
import FiltroBusca from '@/components/FiltroBusca.vue';
import PainelComunicacao from '@/components/PainelComunicacao.vue';
import Paginacao from '@/components/Paginacao.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useExportar } from '@/composables/useExportar';
import { useSelecao } from '@/composables/useSelecao';
import type {
    Comunicacao,
    ConsultaPageProps,
    FiltrosConsulta,
} from '@/types/djen';

const props = defineProps<ConsultaPageProps>();

const page = usePage();
const erros = computed(
    () =>
        (page.props.errors ?? {}) as Partial<
            Record<keyof FiltrosConsulta, string>
        >,
);

const carregando = ref(false);
const selecionada = ref<Comunicacao | null>(null);
const modalAberto = ref(false);
const topoResultados = useTemplateRef<HTMLElement>('topoResultados');

const filtrosAtuais = computed<FiltrosConsulta>(() => props.filtros);

function visitar(filtros: FiltrosConsulta, pagina = 1) {
    const query: Record<string, string | number> = {};

    for (const [chave, valor] of Object.entries(filtros)) {
        if (valor !== null && valor !== '') {
            query[chave] = valor;
        }
    }

    if (pagina > 1) {
        query.pagina = pagina;
    }

    router.get(route('consulta.index'), query, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => (carregando.value = true),
        onFinish: () => (carregando.value = false),
    });
}

function mudarPagina(pagina: number) {
    visitar(filtrosAtuais.value, pagina);
    topoResultados.value?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

function abrirInteiroTeor(comunicacao: Comunicacao) {
    selecionada.value = comunicacao;
    modalAberto.value = true;
}

function tentarNovamente() {
    router.reload({
        onStart: () => (carregando.value = true),
        onFinish: () => (carregando.value = false),
    });
}

const logado = computed(() => Boolean(page.props.auth?.user));
const selecao = useSelecao<string>();
const { enviando, exportar } = useExportar();

// A seleção vale para a busca atual: filtros novos, seleção nova.
watch(
    () => JSON.stringify(props.filtros),
    () => selecao.limpar(),
);

const hashesDaPagina = computed(
    () => props.resultados?.data.map((c) => c.hash) ?? [],
);
const paginaAtual = computed(() => props.resultados?.meta.pagina ?? 1);

function pedirExportacao({
    formato,
    todos,
}: {
    formato: 'csv' | 'xlsx' | 'pdf';
    todos: boolean;
}) {
    const filtros = Object.fromEntries(
        Object.entries(props.filtros).filter(([, v]) => v !== null && v !== ''),
    );

    exportar({
        origem: 'consulta',
        formato,
        todos,
        filtros,
        selecao: todos
            ? []
            : selecao.porPagina().map((g) => ({
                  pagina: g.pagina,
                  hashes: g.chaves,
              })),
    });
}

const total = computed(() => props.resultados?.meta.total ?? 0);
const resumo = computed(() =>
    total.value === 1
        ? '1 comunicação encontrada'
        : `${total.value.toLocaleString('pt-BR')} comunicações encontradas`,
);
</script>

<template>
    <Head title="Consulta de comunicações" />

    <div class="grid gap-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Consulta de comunicações processuais
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Intimações, citações e editais publicados no DJEN. Busque por
                OAB, parte, advogado, processo ou tribunal.
            </p>
        </div>

        <FiltroBusca
            :filtros="filtros"
            :tribunais="tribunais"
            :ufs="ufs"
            :meios="meios"
            :erros="erros"
            :carregando="carregando"
            @buscar="visitar"
        />

        <section
            ref="topoResultados"
            aria-live="polite"
            class="grid scroll-mt-4 gap-4"
        >
            <SkeletonResultados v-if="carregando" />

            <Alert
                v-else-if="erro"
                variant="destructive"
                data-testid="erro-api"
            >
                <AlertTriangle class="size-4" />
                <AlertTitle> Não foi possível consultar o CNJ </AlertTitle>
                <AlertDescription>
                    <p>{{ erro.mensagem }}</p>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        @click="tentarNovamente"
                    >
                        <RotateCw class="size-4" aria-hidden="true" />
                        Tentar novamente
                    </Button>
                </AlertDescription>
            </Alert>

            <template v-else-if="resultados">
                <EstadoVazio
                    v-if="resultados.data.length === 0"
                    titulo="Nenhuma comunicação encontrada"
                    descricao="Revise os filtros ou amplie o período de disponibilização."
                >
                    <template #icone><SearchX class="size-10" /></template>
                </EstadoVazio>

                <template v-else>
                    <p
                        class="text-sm text-muted-foreground"
                        data-testid="resumo"
                    >
                        {{ resumo }} · página {{ resultados.meta.pagina }} de
                        {{ resultados.meta.ultimaPagina }}
                    </p>

                    <div
                        class="overflow-hidden rounded-xl border bg-card shadow-xs"
                        data-testid="lista-comunicacoes"
                    >
                        <div
                            class="hidden gap-x-4 border-b bg-muted/40 px-4 py-2 text-xs font-medium tracking-wide text-muted-foreground uppercase lg:grid lg:grid-cols-[7.5rem_minmax(0,1.7fr)_minmax(0,1fr)_minmax(0,1.5fr)_auto]"
                            aria-hidden="true"
                        >
                            <span>Disponib.</span>
                            <span>Processo / órgão</span>
                            <span>Tipo</span>
                            <span>Partes e advogados</span>
                            <span class="text-right">Ações</span>
                        </div>
                        <LinhaComunicacao
                            v-for="c in resultados.data"
                            :key="c.hash"
                            :comunicacao="c"
                            :selecionavel="logado"
                            :selecionada="selecao.selecionado(c.hash)"
                            @update:selecionada="
                                (v) => selecao.alternar(c.hash, paginaAtual, v)
                            "
                            @ver-inteiro-teor="abrirInteiroTeor"
                        />
                    </div>

                    <BarraExportacao
                        :quantidade="selecao.quantidade.value"
                        :total="resultados.meta.total"
                        :logado="logado"
                        :enviando="enviando"
                        @limpar="selecao.limpar"
                        @exportar="pedirExportacao"
                    />

                    <Paginacao
                        :pagina="resultados.meta.pagina"
                        :ultima-pagina="resultados.meta.ultimaPagina"
                        :desabilitado="carregando"
                        @mudar="mudarPagina"
                    />
                </template>
            </template>

            <EstadoVazio
                v-else
                titulo="Faça sua primeira busca"
                descricao="Informe ao menos um filtro: OAB e UF, nome da parte, nome do advogado, número do processo ou tribunal."
            >
                <template #icone><ScrollText class="size-10" /></template>
            </EstadoVazio>
        </section>
    </div>

    <PainelComunicacao v-model:open="modalAberto" :comunicacao="selecionada" />
</template>
