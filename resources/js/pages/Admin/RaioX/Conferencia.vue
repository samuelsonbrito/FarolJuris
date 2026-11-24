<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Eye, Send } from '@lucide/vue';
import EstadoVazio from '@/components/EstadoVazio.vue';
import FormConferencia from '@/components/raiox/FormConferencia.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Opcao, ProcessoRaioX, RelatorioRaioX } from '@/types/raiox';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administração', href: '/admin' },
            { title: 'Raio-X', href: '/admin/raio-x' },
            { title: 'Conferência', href: '#' },
        ],
    },
});

const props = defineProps<{
    relatorio: RelatorioRaioX;
    processos: ProcessoRaioX[];
    filtro: 'pendentes' | 'decididos' | 'todos';
    opcoes: {
        resultados: Opcao[];
        instancias: Opcao[];
        grupos: Opcao[];
        temas: Opcao[];
    };
}>();

const filtros = [
    { valor: 'pendentes', rotulo: 'Precisam conferir' },
    { valor: 'decididos', rotulo: 'Decididos' },
    { valor: 'todos', rotulo: 'Todos' },
] as const;

function filtrar(valor: string) {
    router.get(
        route('admin.raiox.conferencia', props.relatorio.id),
        { filtro: valor },
        { preserveScroll: true, preserveState: true },
    );
}

function liberar() {
    if (
        window.confirm(
            `Liberar o Raio-X para ${props.relatorio.usuario?.nome}?`,
        )
    ) {
        router.post(
            route('admin.raiox.liberar', props.relatorio.id),
            {},
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head :title="`Conferência · Raio-X ${relatorio.oab}/${relatorio.uf}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 md:p-6">
        <Link
            :href="route('admin.raiox.index')"
            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            Pedidos de Raio-X
        </Link>

        <header class="flex flex-wrap items-start gap-3">
            <div class="grid gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Conferência · OAB {{ relatorio.oab }}/{{ relatorio.uf }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ relatorio.nomeAdvogado ?? 'Advogado não identificado' }}
                    · {{ relatorio.usuario?.nome }} ·
                    {{ relatorio.processos }} processos ·
                    {{ relatorio.pendentes }} para conferir
                </p>
            </div>
            <Badge variant="secondary">{{ relatorio.statusRotulo }}</Badge>
            <div class="ml-auto flex flex-wrap gap-2">
                <Button as-child variant="outline" size="sm">
                    <Link :href="route('raiox.show', relatorio.id)">
                        <Eye class="size-4" aria-hidden="true" />
                        Ver relatório
                    </Link>
                </Button>
                <Button
                    v-if="relatorio.status === 'pronto'"
                    size="sm"
                    data-testid="liberar"
                    @click="liberar"
                >
                    <Send class="size-4" aria-hidden="true" />
                    Liberar para o cliente
                </Button>
            </div>
        </header>

        <div
            class="flex flex-wrap gap-1.5"
            role="tablist"
            aria-label="Filtrar processos"
        >
            <button
                v-for="f in filtros"
                :key="f.valor"
                type="button"
                role="tab"
                class="rounded-full border px-3 py-1 text-xs"
                :class="
                    filtro === f.valor
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-accent'
                "
                :aria-selected="filtro === f.valor"
                @click="filtrar(f.valor)"
            >
                {{ f.rotulo }}
            </button>
        </div>

        <EstadoVazio
            v-if="!processos.length"
            :titulo="
                filtro === 'pendentes'
                    ? 'Nada para conferir'
                    : 'Nenhum processo'
            "
            :descricao="
                filtro === 'pendentes'
                    ? 'Todos os casos ambíguos foram conferidos. O Raio-X pode ser liberado.'
                    : 'Não há processos neste filtro.'
            "
        />

        <FormConferencia
            v-for="p in processos"
            :key="`${p.id}-${p.conferidoEm}`"
            :processo="p"
            :opcoes="opcoes"
        />
    </div>
</template>
