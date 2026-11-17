<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    BellPlus,
    BellRing,
    Copy,
    FileSearch,
    RotateCw,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AndamentosProcesso from '@/components/AndamentosProcesso.vue';
import SkeletonResultados from '@/components/consulta/SkeletonResultados.vue';
import EstadoVazio from '@/components/EstadoVazio.vue';
import LinhaComunicacao from '@/components/LinhaComunicacao.vue';
import PainelComunicacao from '@/components/PainelComunicacao.vue';
import Paginacao from '@/components/Paginacao.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Comunicacao, ProcessoPageProps } from '@/types/djen';

const props = defineProps<ProcessoPageProps>();

const page = usePage();
const logado = computed(() => Boolean(page.props.auth?.user));

const carregando = ref(false);
const selecionada = ref<Comunicacao | null>(null);
const painelAberto = ref(false);

const total = computed(() => props.resultados?.meta.total ?? 0);
const resumo = computed(() =>
    total.value === 1
        ? '1 publicação no DJEN'
        : `${total.value.toLocaleString('pt-BR')} publicações no DJEN`,
);

function abrir(c: Comunicacao) {
    selecionada.value = c;
    painelAberto.value = true;
}

function mudarPagina(pagina: number) {
    router.get(
        route('processos.show', { numero: props.processo.numero }),
        pagina > 1 ? { pagina } : {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['resultados', 'erro'],
            onStart: () => (carregando.value = true),
            onFinish: () => (carregando.value = false),
        },
    );
}

function tentarNovamente() {
    router.reload({
        only: ['resultados', 'erro'],
        onStart: () => (carregando.value = true),
        onFinish: () => (carregando.value = false),
    });
}

async function copiarNumero() {
    try {
        await navigator.clipboard.writeText(props.processo.numero);
        toast.success('Número do processo copiado (só dígitos).');
    } catch {
        toast.error('Não foi possível copiar. Selecione e copie manualmente.');
    }
}

const form = useForm({ tipo: 'processo', valor: props.processo.numero });

function monitorar() {
    form.post(route('monitoramento.alvos.store'), {
        preserveScroll: true,
        onError: (erros) => {
            toast.error(
                erros.valor ?? 'Não foi possível monitorar este processo.',
            );
        },
    });
}

const descricao = computed(
    () =>
        `Publicações no DJEN${props.processo.andamentos ? ' e andamentos' : ''} do processo ${props.processo.numeroMascara}${props.processo.tribunal ? ` (${props.processo.tribunal})` : ''}.`,
);
</script>

<template>
    <Head :title="`Processo ${processo.numeroMascara}`">
        <meta name="description" :content="descricao" />
        <!-- Nomes de partes vêm do DJEN: a página não é indexada por buscadores (LGPD). -->
        <meta name="robots" content="noindex" />
    </Head>

    <div class="grid gap-6">
        <div>
            <Link
                :href="route('consulta.index')"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Consulta
            </Link>
        </div>

        <header
            class="grid gap-4 rounded-xl border bg-card p-5 shadow-xs sm:p-6"
            data-testid="cabecalho-processo"
        >
            <div class="grid gap-1.5">
                <p class="text-sm text-muted-foreground">Processo</p>
                <h1
                    class="font-mono text-xl font-semibold tracking-tight break-all sm:text-2xl"
                    data-testid="numero-processo"
                >
                    {{ processo.numeroMascara }}
                </h1>
                <div
                    v-if="processo.tribunal"
                    class="flex flex-wrap items-center gap-2 text-sm"
                >
                    <Badge>{{ processo.tribunal }}</Badge>
                    <span class="text-muted-foreground">
                        {{ processo.tribunalNome }}
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    data-testid="copiar-numero"
                    @click="copiarNumero"
                >
                    <Copy class="size-4" aria-hidden="true" />
                    Copiar nº (só dígitos)
                </Button>

                <Button v-if="alvo" as-child variant="secondary" size="sm">
                    <Link
                        :href="route('monitoramento.index')"
                        data-testid="monitorado"
                    >
                        <BellRing class="size-4" aria-hidden="true" />
                        {{
                            alvo.ativo ? 'Monitorado' : 'Monitoramento pausado'
                        }}
                    </Link>
                </Button>
                <Button
                    v-else-if="logado"
                    size="sm"
                    :disabled="form.processing"
                    data-testid="monitorar"
                    @click="monitorar"
                >
                    <BellPlus class="size-4" aria-hidden="true" />
                    Monitorar este processo
                </Button>
                <Button v-else as-child size="sm" variant="outline">
                    <Link :href="route('login')" data-testid="entrar-monitorar">
                        <BellPlus class="size-4" aria-hidden="true" />
                        Entre para monitorar
                    </Link>
                </Button>
            </div>
        </header>

        <AndamentosProcesso
            v-if="processo.andamentos && processo.tribunal"
            class="rounded-xl border bg-card p-5 shadow-xs sm:p-6"
            :tribunal="processo.tribunal"
            :numero-processo="processo.numero"
        />

        <section
            aria-labelledby="titulo-publicacoes"
            aria-live="polite"
            class="grid gap-4"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2
                    id="titulo-publicacoes"
                    class="text-lg font-semibold tracking-tight"
                >
                    Publicações no DJEN
                </h2>
                <p
                    v-if="resultados && resultados.data.length"
                    class="text-sm text-muted-foreground"
                    data-testid="resumo"
                >
                    {{ resumo }} · página {{ resultados.meta.pagina }} de
                    {{ resultados.meta.ultimaPagina }}
                </p>
            </div>

            <SkeletonResultados v-if="carregando" />

            <Alert
                v-else-if="erro"
                variant="destructive"
                data-testid="erro-api"
            >
                <AlertTriangle class="size-4" />
                <AlertTitle>Não foi possível consultar o CNJ</AlertTitle>
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

            <EstadoVazio
                v-else-if="!resultados || resultados.data.length === 0"
                titulo="Nenhuma publicação encontrada"
                descricao="O DJEN não tem intimações, citações ou editais deste processo."
            >
                <template #icone><FileSearch class="size-10" /></template>
            </EstadoVazio>

            <template v-else>
                <div
                    class="overflow-hidden rounded-xl border bg-card shadow-xs"
                    data-testid="lista-comunicacoes"
                >
                    <LinhaComunicacao
                        v-for="c in resultados.data"
                        :key="c.hash"
                        :comunicacao="c"
                        @ver-inteiro-teor="abrir"
                    />
                </div>

                <Paginacao
                    :pagina="resultados.meta.pagina"
                    :ultima-pagina="resultados.meta.ultimaPagina"
                    :desabilitado="carregando"
                    @mudar="mudarPagina"
                />
            </template>
        </section>
    </div>

    <PainelComunicacao
        v-model:open="painelAberto"
        :comunicacao="selecionada"
        :na-pagina-do-processo="true"
    />
</template>
