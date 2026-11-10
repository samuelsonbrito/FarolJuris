<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BellRing,
    CheckCheck,
    Inbox,
    Mail,
    MailOpen,
    Radar,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BarraExportacao from '@/components/BarraExportacao.vue';
import CardComunicacao from '@/components/CardComunicacao.vue';
import EstadoVazio from '@/components/EstadoVazio.vue';
import PainelComunicacao from '@/components/PainelComunicacao.vue';
import FormAlvo from '@/components/monitoramento/FormAlvo.vue';
import ListaAlvos from '@/components/monitoramento/ListaAlvos.vue';
import PreferenciasNotificacao from '@/components/monitoramento/PreferenciasNotificacao.vue';
import Paginacao from '@/components/Paginacao.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useExportar } from '@/composables/useExportar';
import { useSelecao } from '@/composables/useSelecao';
import type { Comunicacao } from '@/types/djen';
import type {
    Alerta,
    MonitoramentoPageProps,
    StatusAlerta,
} from '@/types/monitoramento';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Monitoramento', href: '/monitoramento' }],
    },
});

const props = defineProps<MonitoramentoPageProps>();

const carregando = ref(false);
const selecionada = ref<Comunicacao | null>(null);
const modalAberto = ref(false);

const abas: { valor: StatusAlerta; rotulo: string }[] = [
    { valor: 'nao_lidas', rotulo: 'Não lidas' },
    { valor: 'todas', rotulo: 'Todas' },
    { valor: 'lidas', rotulo: 'Lidas' },
];

const alvoSelecionado = computed(
    () => props.alvos.find((a) => a.id === props.filtros.alvo) ?? null,
);

function visitar(
    alteracoes: Partial<{
        alvo: number | null;
        status: StatusAlerta;
        pagina: number;
    }>,
) {
    const proximo = { ...props.filtros, pagina: 1, ...alteracoes };
    const query: Record<string, string | number> = {};

    if (proximo.alvo) {
        query.alvo = proximo.alvo;
    }

    if (proximo.status !== 'nao_lidas') {
        query.status = proximo.status;
    }

    if (proximo.pagina > 1) {
        query.pagina = proximo.pagina;
    }

    router.get(route('monitoramento.index'), query, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => (carregando.value = true),
        onFinish: () => (carregando.value = false),
    });
}

function marcar(alerta: Alerta, lida: boolean) {
    router.patch(
        route('monitoramento.alertas.update', alerta.id),
        { lida },
        { preserveScroll: true, preserveState: true },
    );
}

function marcarTodas() {
    router.post(
        route('monitoramento.alertas.marcar-lidas'),
        props.filtros.alvo ? { alvo: props.filtros.alvo } : {},
        { preserveScroll: true },
    );
}

function abrir(alerta: Alerta) {
    selecionada.value = alerta.comunicacao;
    modalAberto.value = true;

    if (!alerta.lida) {
        marcar(alerta, true);
    }
}

const selecao = useSelecao<number>();
const { enviando, exportar } = useExportar();
const idsDaPagina = computed(() => props.alertas.data.map((a) => a.id));

// Filtro novo (alvo/status), seleção nova.
watch(
    () => [props.filtros.alvo, props.filtros.status],
    () => selecao.limpar(),
);

function pedirExportacao({
    formato,
    todos,
}: {
    formato: 'csv' | 'xlsx' | 'pdf';
    todos: boolean;
}) {
    exportar({
        origem: 'monitoramento',
        formato,
        todos,
        filtros: { alvo: props.filtros.alvo, status: props.filtros.status },
        selecao: todos ? [] : selecao.chaves(),
    });
}

const vazio = computed(() => {
    if (props.alvos.length === 0) {
        return {
            titulo: 'Nenhum alvo monitorado ainda',
            descricao:
                'Cadastre uma OAB, um nome de parte ou um número de processo. Avisaremos por e-mail quando surgirem comunicações novas.',
        };
    }

    return props.filtros.status === 'nao_lidas'
        ? {
              titulo: 'Tudo em dia',
              descricao: 'Nenhuma comunicação nova por aqui.',
          }
        : {
              titulo: 'Nenhuma comunicação',
              descricao: 'Ainda não encontramos comunicações para este filtro.',
          };
});
</script>

<template>
    <Head title="Monitoramento" />

    <div
        class="grid gap-6 p-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] lg:items-start"
    >
        <aside class="grid min-w-0 gap-6">
            <section
                class="rounded-xl border bg-card p-4"
                aria-labelledby="novo-alvo"
            >
                <h2
                    id="novo-alvo"
                    class="mb-3 flex items-center gap-2 font-semibold"
                >
                    <Radar class="size-4" aria-hidden="true" />
                    Novo alvo
                </h2>
                <FormAlvo
                    :tipos-alvo="tiposAlvo"
                    :ufs="ufs"
                    :desabilitado="alvos.length >= limiteAlvos"
                />
            </section>

            <section
                v-if="alvos.length"
                class="rounded-xl border bg-card p-4"
                aria-labelledby="meus-alvos"
            >
                <h2 id="meus-alvos" class="mb-3 font-semibold">
                    Meus alvos
                    <span class="text-sm font-normal text-muted-foreground"
                        >({{ alvos.length }}/{{ limiteAlvos }})</span
                    >
                </h2>
                <ListaAlvos
                    :alvos="alvos"
                    :selecionado="filtros.alvo"
                    @filtrar="(alvo) => visitar({ alvo })"
                />
            </section>

            <section
                class="rounded-xl border bg-card p-4"
                aria-labelledby="avisos"
            >
                <h2
                    id="avisos"
                    class="mb-3 flex items-center gap-2 font-semibold"
                >
                    <BellRing class="size-4" aria-hidden="true" />
                    Avisos
                </h2>
                <PreferenciasNotificacao :preferencias="notificacoes" />
            </section>
        </aside>

        <section class="grid min-w-0 gap-4" aria-labelledby="comunicacoes">
            <div class="flex flex-wrap items-center gap-3">
                <h1
                    id="comunicacoes"
                    class="text-xl font-semibold tracking-tight"
                >
                    Comunicações
                    <Badge v-if="totalNaoLidas" class="ml-1 align-middle">
                        {{ totalNaoLidas }} não lidas
                    </Badge>
                </h1>
                <Button
                    v-if="alertas.data.some((a) => !a.lida)"
                    variant="outline"
                    size="sm"
                    class="ml-auto"
                    data-testid="marcar-todas"
                    @click="marcarTodas"
                >
                    <CheckCheck class="size-4" aria-hidden="true" />
                    Marcar todas como lidas
                </Button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div
                    class="inline-flex rounded-lg border p-0.5"
                    role="tablist"
                    aria-label="Filtrar por status"
                >
                    <button
                        v-for="aba in abas"
                        :key="aba.valor"
                        type="button"
                        role="tab"
                        :aria-selected="filtros.status === aba.valor"
                        class="rounded-md px-3 py-1 text-sm transition-colors"
                        :class="
                            filtros.status === aba.valor
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-accent'
                        "
                        @click="visitar({ status: aba.valor })"
                    >
                        {{ aba.rotulo }}
                    </button>
                </div>
                <label
                    v-if="alertas.data.length"
                    class="inline-flex cursor-pointer items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        class="size-4 accent-primary"
                        :checked="selecao.paginaToda(idsDaPagina)"
                        data-testid="selecionar-pagina"
                        @change="
                            selecao.alternarPagina(
                                idsDaPagina,
                                alertas.meta.pagina,
                            )
                        "
                    />
                    Selecionar página
                </label>
                <Badge
                    v-if="alvoSelecionado"
                    variant="secondary"
                    as="button"
                    class="cursor-pointer"
                    @click="visitar({ alvo: null })"
                >
                    {{ alvoSelecionado.descricao }} ✕
                </Badge>
            </div>

            <div
                class="grid gap-4"
                :class="{ 'opacity-60': carregando }"
                aria-live="polite"
            >
                <EstadoVazio
                    v-if="alertas.data.length === 0"
                    :titulo="vazio.titulo"
                    :descricao="vazio.descricao"
                >
                    <template #icone><Inbox class="size-10" /></template>
                </EstadoVazio>

                <div
                    v-for="alerta in alertas.data"
                    :key="alerta.id"
                    class="relative"
                    :data-testid="`alerta-${alerta.id}`"
                >
                    <span
                        v-if="!alerta.lida"
                        class="absolute top-5 -left-1.5 size-3 rounded-full bg-primary ring-4 ring-background"
                        aria-hidden="true"
                    />
                    <CardComunicacao
                        selecionavel
                        :selecionada="selecao.selecionado(alerta.id)"
                        :comunicacao="alerta.comunicacao"
                        :class="{ 'border-primary/40': !alerta.lida }"
                        @update:selecionada="
                            (v) =>
                                selecao.alternar(
                                    alerta.id,
                                    alertas.meta.pagina,
                                    v,
                                )
                        "
                        @ver-inteiro-teor="abrir(alerta)"
                    >
                        <template #acoes>
                            <div class="flex min-w-0 items-center gap-2">
                                <Badge v-if="!alerta.lida">Nova</Badge>
                                <span
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ alerta.alvo.descricao }}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 px-2"
                                    :aria-label="
                                        alerta.lida
                                            ? 'Marcar como não lida'
                                            : 'Marcar como lida'
                                    "
                                    @click="marcar(alerta, !alerta.lida)"
                                >
                                    <component
                                        :is="alerta.lida ? Mail : MailOpen"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span class="hidden sm:inline">{{
                                        alerta.lida
                                            ? 'Marcar não lida'
                                            : 'Marcar lida'
                                    }}</span>
                                </Button>
                            </div>
                        </template>
                    </CardComunicacao>
                </div>

                <BarraExportacao
                    :quantidade="selecao.quantidade.value"
                    :total="alertas.meta.total"
                    logado
                    :enviando="enviando"
                    @limpar="selecao.limpar"
                    @exportar="pedirExportacao"
                />

                <Paginacao
                    :pagina="alertas.meta.pagina"
                    :ultima-pagina="alertas.meta.ultimaPagina"
                    :desabilitado="carregando"
                    @mudar="(pagina) => visitar({ ...filtros, pagina })"
                />
            </div>
        </section>
    </div>

    <PainelComunicacao v-model:open="modalAberto" :comunicacao="selecionada" />
</template>
