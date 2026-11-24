<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    LayoutDashboard,
    ScanSearch,
    Search,
    Server,
    Users,
} from '@lucide/vue';

/** Abas da área administrativa. O período atual acompanha a navegação. */
const props = defineProps<{ query?: Record<string, string> }>();

const abas = [
    {
        rota: 'admin.dashboard',
        titulo: 'Visão geral',
        icone: LayoutDashboard,
        comPeriodo: true,
    },
    { rota: 'admin.buscas', titulo: 'Buscas', icone: Search, comPeriodo: true },
    {
        rota: 'admin.atividade',
        titulo: 'Atividade',
        icone: Activity,
        comPeriodo: true,
    },
    {
        rota: 'admin.usuarios',
        titulo: 'Usuários',
        icone: Users,
        comPeriodo: false,
    },
    {
        rota: 'admin.cnj',
        titulo: 'API do CNJ',
        icone: Server,
        comPeriodo: true,
    },
    {
        rota: 'admin.raiox.index',
        titulo: 'Raio-X',
        icone: ScanSearch,
        comPeriodo: false,
    },
];

function destino(rota: string, comPeriodo: boolean): string {
    return route(rota, comPeriodo ? (props.query ?? {}) : {});
}
</script>

<template>
    <nav
        aria-label="Área administrativa"
        class="-mx-1 flex gap-1 overflow-x-auto border-b pb-px"
    >
        <Link
            v-for="aba in abas"
            :key="aba.rota"
            :href="destino(aba.rota, aba.comPeriodo)"
            class="flex shrink-0 items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
            :class="
                route().current(aba.rota)
                    ? 'border-foreground text-foreground'
                    : 'border-transparent'
            "
            :aria-current="route().current(aba.rota) ? 'page' : undefined"
        >
            <component :is="aba.icone" class="size-4" aria-hidden="true" />
            {{ aba.titulo }}
        </Link>
    </nav>
</template>
