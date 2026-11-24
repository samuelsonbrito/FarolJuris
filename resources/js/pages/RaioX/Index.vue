<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ScanSearch, Send } from '@lucide/vue';
import EstadoVazio from '@/components/EstadoVazio.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatarData, normalizarOab } from '@/lib/cnj';
import type { RelatorioRaioX } from '@/types/raiox';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Raio-X', href: '/raio-x' }] },
});

defineProps<{ relatorios: RelatorioRaioX[]; ufs: string[]; meses: number }>();

const form = useForm({ oab: '', uf: '' });

function solicitar() {
    form.transform((d) => ({ ...d, oab: normalizarOab(d.oab) })).post(
        route('raiox.store'),
        { preserveScroll: true, onSuccess: () => form.reset() },
    );
}

const variantes: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    liberado: 'default',
    falhou: 'destructive',
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head title="Raio-X" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">
                Raio-X de desempenho
            </h1>
            <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
                Saiba onde você ganha e cresça exatamente ali. A partir da sua
                OAB, reunimos as decisões publicadas no DJEN nos últimos
                {{ meses }} meses, lemos cada uma do ponto de vista do cliente e
                mostramos a taxa de êxito por instância, vara, tema e parte
                contrária, além do custo de cada êxito.
            </p>
        </header>

        <form
            class="grid gap-4 rounded-xl border bg-card p-5 shadow-xs"
            @submit.prevent="solicitar"
        >
            <div
                class="grid grid-cols-[minmax(0,1fr)_6rem] gap-4 sm:grid-cols-[12rem_6rem_auto] sm:items-end"
            >
                <div class="grid gap-1.5">
                    <Label for="raiox-oab">Número da OAB</Label>
                    <Input
                        id="raiox-oab"
                        v-model="form.oab"
                        autocomplete="off"
                        placeholder="Ex.: 12345"
                        :aria-invalid="Boolean(form.errors.oab)"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="raiox-uf">UF</Label>
                    <select
                        id="raiox-uf"
                        v-model="form.uf"
                        :class="selectClass"
                        :aria-invalid="Boolean(form.errors.uf)"
                    >
                        <option value="">—</option>
                        <option v-for="uf in ufs" :key="uf" :value="uf">
                            {{ uf }}
                        </option>
                    </select>
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-testid="solicitar-raiox"
                    >
                        <Send class="size-4" aria-hidden="true" />
                        Solicitar Raio-X
                    </Button>
                </div>
            </div>
            <InputError :message="form.errors.oab ?? form.errors.uf" />
            <ol class="grid gap-1 text-xs text-muted-foreground sm:grid-cols-3">
                <li>1. Você informa a OAB e a UF.</li>
                <li>
                    2. Coletamos as publicações e classificamos cada decisão;
                    casos ambíguos são conferidos pela nossa equipe.
                </li>
                <li>
                    3. Quando estiver pronto, o Raio-X aparece nesta página.
                </li>
            </ol>
        </form>

        <section aria-labelledby="meus-raiox" class="grid gap-3">
            <h2 id="meus-raiox" class="text-lg font-semibold tracking-tight">
                Meus relatórios
            </h2>

            <EstadoVazio
                v-if="!relatorios.length"
                titulo="Nenhum Raio-X ainda"
                descricao="Solicite o primeiro informando o número da OAB e a UF."
            >
                <template #icone><ScanSearch class="size-10" /></template>
            </EstadoVazio>

            <ul v-else class="grid gap-2">
                <li
                    v-for="r in relatorios"
                    :key="r.id"
                    class="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4 shadow-xs"
                    data-testid="relatorio-raiox"
                >
                    <div class="grid gap-0.5">
                        <p class="font-medium">
                            OAB {{ r.oab }}/{{ r.uf }}
                            <span
                                v-if="r.nomeAdvogado"
                                class="font-normal text-muted-foreground"
                            >
                                · {{ r.nomeAdvogado }}
                            </span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatarData(r.inicio) }} a
                            {{ formatarData(r.fim) }} · pedido em
                            {{ formatarData(r.solicitadoEm.slice(0, 10)) }}
                        </p>
                    </div>
                    <Badge :variant="variantes[r.status] ?? 'secondary'">
                        {{ r.statusRotulo }}
                    </Badge>
                    <Button
                        v-if="r.status === 'liberado'"
                        as-child
                        size="sm"
                        class="ml-auto"
                    >
                        <Link :href="route('raiox.show', r.id)">
                            Ver Raio-X
                        </Link>
                    </Button>
                    <p v-else class="ml-auto text-xs text-muted-foreground">
                        Aparece aqui quando estiver pronto.
                    </p>
                </li>
            </ul>
        </section>
    </div>
</template>
