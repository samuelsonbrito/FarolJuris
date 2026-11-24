<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { FileDown, Radar, ScanSearch, Search, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const itensBase: NavItem[] = [
    {
        title: 'Monitoramento',
        href: route('monitoramento.index'),
        icon: Radar,
    },
    {
        title: 'Consulta',
        href: route('consulta.index'),
        icon: Search,
    },
    {
        title: 'Exportações',
        href: route('exportacoes.index'),
        icon: FileDown,
    },
    {
        title: 'Raio-X',
        href: route('raiox.index'),
        icon: ScanSearch,
    },
];

// Área administrativa: só para quem tem acesso (users.is_admin).
const mainNavItems = computed<NavItem[]>(() =>
    page.props.auth?.user?.is_admin
        ? [
              ...itensBase,
              {
                  title: 'Administração',
                  href: route('admin.dashboard'),
                  icon: ShieldCheck,
              },
          ]
        : itensBase,
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
