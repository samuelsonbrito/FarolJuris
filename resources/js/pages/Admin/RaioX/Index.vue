<script setup lang="ts">
import { Head, Link, router, useForm, usePoll } from '@inertiajs/vue3';
import {
    ClipboardCheck,
    Eye,
    Play,
    Plus,
    Send,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import EstadoVazio from '@/components/EstadoVazio.vue';
import InputError from '@/components/InputError.vue';
import Paginacao from '@/components/Paginacao.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatarData, normalizarOab } from '@/lib/cnj';
import type { RelatorioRaioX } from '@/types/raiox';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administração', href: '/admin' },
            { title: 'Raio-X', href: '/admin/raio-x' },
        ],
    },
});

const props = defineProps<{
    relatorios: RelatorioRaioX[];
    paginacao: { pagina: number; ultimaPagina: number; total: number };
    ufs: string[];
    iaDriver: string;
    iaConfigurada: boolean;
}>();

// Atualiza a lista enquanto algum Raio-X está em processamento.
const emAndamento = computed(() =>
    props.relatorios.some((r) => r.status === 'processando'),
);
const { start, stop } = usePoll(
    4000,
    { only: ['relatorios'] },
    { autoStart: false },
);
watch(emAndamento, (v) => (v ? start() : stop()), { immediate: true });

const form = useForm({ email: '', oab: '', uf: '' });

function criar() {
    form.transform((d) => ({ ...d, oab: normalizarOab(d.oab) })).post(
        route('admin.raiox.store'),
        { preserveScroll: true, onSuccess: () => form.reset() },
    );
}

function acao(nome: 'gerar' | 'liberar', r: RelatorioRaioX) {
    const mensagem =
        nome === 'gerar'
            ? `Gerar o Raio-X da OAB ${r.oab}/${r.uf}? A classificação usa a IA e tem custo.`
            : `Liberar o Raio-X para ${r.usuario?.nome}? A pessoa recebe um e-mail.`;

    if (window.confirm(mensagem)) {
        router.post(
            route(`admin.raiox.${nome}`, r.id),
            {},
            { preserveScroll: true },
        );
    }
}

function excluir(r: RelatorioRaioX) {
    if (window.confirm(`Excluir o Raio-X da OAB ${r.oab}/${r.uf}?`)) {
        router.delete(route('admin.raiox.destroy', r.id), {
            preserveScroll: true,
        });
    }
}

function mudarPagina(pagina: number) {
    router.get(
        route('admin.raiox.index'),
        { page: pagina },
        { preserveScroll: true },
    );
}

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head title="Raio-X · Administração" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 md:p-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Raio-X</h1>
            <p class="text-sm text-muted-foreground">
                Pedidos dos usuários. Confira se a OAB é de quem pediu, gere (a
                classificação usa IA), confira os casos ambíguos e libere.
            </p>
        </header>

        <AdminNav />

        <p
            v-if="!iaConfigurada"
            class="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            A chave da API da Anthropic não está configurada (ANTHROPIC_API_KEY
            no .env). Sem ela, a classificação falha e todos os casos vão para
            conferência.
        </p>
        <p
            v-else-if="iaDriver === 'fake'"
            class="rounded-lg border p-3 text-sm text-muted-foreground"
        >
            Modo de demonstração (RAIOX_IA_DRIVER=fake): a classificação usa
            regras simples e dados de exemplo, sem IA.
        </p>

        <form
            class="grid gap-3 rounded-xl border bg-card p-4 shadow-xs sm:grid-cols-[minmax(0,1fr)_10rem_6rem_auto] sm:items-end"
            @submit.prevent="criar"
        >
            <div class="grid gap-1.5">
                <Label for="admin-raiox-email">E-mail da conta</Label>
                <Input
                    id="admin-raiox-email"
                    v-model="form.email"
                    type="email"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="admin-raiox-oab">OAB</Label>
                <Input
                    id="admin-raiox-oab"
                    v-model="form.oab"
                    autocomplete="off"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="admin-raiox-uf">UF</Label>
                <select
                    id="admin-raiox-uf"
                    v-model="form.uf"
                    :class="selectClass"
                >
                    <option value="">—</option>
                    <option v-for="uf in ufs" :key="uf" :value="uf">
                        {{ uf }}
                    </option>
                </select>
            </div>
            <Button type="submit" variant="outline" :disabled="form.processing">
                <Plus class="size-4" aria-hidden="true" />
                Criar para a conta
            </Button>
            <InputError
                class="sm:col-span-4"
                :message="
                    form.errors.email ?? form.errors.oab ?? form.errors.uf
                "
            />
        </form>

        <EstadoVazio
            v-if="!relatorios.length"
            titulo="Nenhum pedido de Raio-X"
            descricao="Os pedidos feitos pelos usuários no menu Raio-X aparecem aqui."
        />

        <div v-else class="overflow-x-auto rounded-xl border bg-card shadow-xs">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-2 font-medium">Conta</th>
                        <th class="px-3 py-2 font-medium">OAB</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 text-right font-medium">
                            Processos
                        </th>
                        <th class="px-3 py-2 text-right font-medium">
                            Conferir
                        </th>
                        <th class="px-3 py-2 text-right font-medium">IA</th>
                        <th class="px-4 py-2 text-right font-medium">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="r in relatorios"
                        :key="r.id"
                        class="border-t align-top"
                        data-testid="pedido-raiox"
                    >
                        <td class="px-4 py-2">
                            <p class="font-medium">{{ r.usuario?.nome }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ r.usuario?.email }}
                            </p>
                        </td>
                        <td class="px-3 py-2">
                            <p>{{ r.oab }}/{{ r.uf }}</p>
                            <p
                                v-if="r.nomeAdvogado"
                                class="text-xs text-muted-foreground"
                            >
                                {{ r.nomeAdvogado }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                pedido em
                                {{ formatarData(r.solicitadoEm.slice(0, 10)) }}
                            </p>
                        </td>
                        <td class="px-3 py-2">
                            <Badge
                                :variant="
                                    r.status === 'falhou'
                                        ? 'destructive'
                                        : r.status === 'liberado'
                                          ? 'default'
                                          : 'secondary'
                                "
                            >
                                {{ r.statusRotulo }}
                            </Badge>
                            <p
                                v-if="r.etapa"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ r.etapa }}
                            </p>
                            <p
                                v-if="r.erro"
                                class="mt-1 max-w-xs text-xs text-destructive"
                            >
                                {{ r.erro }}
                            </p>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ r.processos }}
                            <p class="text-xs text-muted-foreground">
                                {{ r.publicacoes }} publ.
                            </p>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ r.pendentes ?? 0 }}
                        </td>
                        <td
                            class="px-3 py-2 text-right text-xs text-muted-foreground tabular-nums"
                        >
                            <template v-if="r.ia?.modelo">
                                {{ r.ia.modelo }}<br />
                                {{
                                    (
                                        (r.ia.tokensEntrada +
                                            r.ia.tokensSaida) /
                                        1000
                                    ).toLocaleString('pt-BR', {
                                        maximumFractionDigits: 0,
                                    })
                                }}
                                mil tokens
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap justify-end gap-1.5">
                                <Button
                                    v-if="r.status !== 'processando'"
                                    size="sm"
                                    variant="outline"
                                    data-testid="gerar"
                                    @click="acao('gerar', r)"
                                >
                                    <Play class="size-4" aria-hidden="true" />
                                    {{
                                        r.status === 'solicitado' ||
                                        r.status === 'falhou'
                                            ? 'Gerar'
                                            : 'Gerar de novo'
                                    }}
                                </Button>
                                <Button
                                    v-if="
                                        [
                                            'conferencia',
                                            'pronto',
                                            'liberado',
                                        ].includes(r.status)
                                    "
                                    as-child
                                    size="sm"
                                    variant="outline"
                                >
                                    <Link
                                        :href="
                                            route(
                                                'admin.raiox.conferencia',
                                                r.id,
                                            )
                                        "
                                    >
                                        <ClipboardCheck
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Conferir
                                    </Link>
                                </Button>
                                <Button
                                    v-if="
                                        [
                                            'conferencia',
                                            'pronto',
                                            'liberado',
                                        ].includes(r.status)
                                    "
                                    as-child
                                    size="sm"
                                    variant="outline"
                                >
                                    <Link :href="route('raiox.show', r.id)">
                                        <Eye
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Ver
                                    </Link>
                                </Button>
                                <Button
                                    v-if="r.status === 'pronto'"
                                    size="sm"
                                    data-testid="liberar"
                                    @click="acao('liberar', r)"
                                >
                                    <Send class="size-4" aria-hidden="true" />
                                    Liberar
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    :aria-label="`Excluir Raio-X da OAB ${r.oab}/${r.uf}`"
                                    @click="excluir(r)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Paginacao
            v-if="paginacao.ultimaPagina > 1"
            :pagina="paginacao.pagina"
            :ultima-pagina="paginacao.ultimaPagina"
            @mudar="mudarPagina"
        />
    </div>
</template>
