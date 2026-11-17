<script setup lang="ts">
import { History, LoaderCircle, RotateCw } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ProcessoDatajud } from '@/types/datajud';

/**
 * Andamentos do processo no DataJud (CNJ), sob demanda: a API é lenta e não é
 * em tempo real, então só consulta quando a pessoa pede.
 */
const props = defineProps<{ tribunal: string; numeroProcesso: string }>();

type Estado = 'inicial' | 'carregando' | 'ok' | 'erro';

const estado = ref<Estado>('inicial');
const processos = ref<ProcessoDatajud[]>([]);
const mensagemErro = ref('');
const expandidos = ref<Set<number>>(new Set());
const LIMITE = 10;

let controle: AbortController | null = null;

function cancelar() {
    controle?.abort();
    controle = null;
}

// Outro processo no painel: volta ao início.
watch(
    () => `${props.tribunal}:${props.numeroProcesso}`,
    () => {
        cancelar();
        estado.value = 'inicial';
        processos.value = [];
        expandidos.value = new Set();
    },
);

onBeforeUnmount(cancelar);

async function carregar() {
    cancelar();
    const atual = new AbortController();
    controle = atual;
    estado.value = 'carregando';

    try {
        const resposta = await fetch(
            route('processos.andamentos', {
                numero: props.numeroProcesso,
                tribunal: props.tribunal,
            }),
            {
                headers: { Accept: 'application/json' },
                signal: atual.signal,
            },
        );
        const corpo = await resposta.json().catch(() => null);

        if (!resposta.ok) {
            mensagemErro.value =
                resposta.status === 429
                    ? 'Muitas consultas seguidas. Espere um minuto e tente de novo.'
                    : (corpo?.erro?.mensagem ??
                      'Não foi possível consultar os andamentos agora.');
            estado.value = 'erro';

            return;
        }

        processos.value = (corpo?.data ?? []) as ProcessoDatajud[];
        estado.value = 'ok';
    } catch (e) {
        if ((e as Error).name === 'AbortError') {
            return;
        }

        mensagemErro.value = 'Não foi possível consultar os andamentos agora.';
        estado.value = 'erro';
    } finally {
        if (controle === atual) {
            controle = null;
        }
    }
}

const formatoDataHora = new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
});
const formatoData = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' });

function dataHora(iso: string | null): string {
    return iso ? formatoDataHora.format(new Date(iso)) : '—';
}

function data(iso: string | null): string {
    return iso ? formatoData.format(new Date(iso)) : '—';
}

function alternar(indice: number) {
    const novo = new Set(expandidos.value);

    if (novo.has(indice)) {
        novo.delete(indice);
    } else {
        novo.add(indice);
    }

    expandidos.value = novo;
}

const visiveis = computed(() =>
    processos.value.map((p, i) =>
        expandidos.value.has(i) ? p.movimentos : p.movimentos.slice(0, LIMITE),
    ),
);
</script>

<template>
    <section
        aria-labelledby="painel-andamentos"
        class="grid gap-3"
        data-testid="andamentos"
    >
        <div class="flex flex-wrap items-center gap-2">
            <h3
                id="painel-andamentos"
                class="inline-flex items-center gap-1.5 text-sm font-semibold"
            >
                <History class="size-4" aria-hidden="true" />
                Andamentos do processo
            </h3>
            <Badge variant="outline">{{ tribunal }}</Badge>
        </div>

        <div
            v-if="estado === 'inicial'"
            class="grid gap-2 rounded-lg border border-dashed p-4 text-sm"
        >
            <p class="text-muted-foreground">
                Classe, assuntos e todos os andamentos registrados pelo
                tribunal, vindos do DataJud do CNJ. A consulta pode levar até 1
                minuto.
            </p>
            <div>
                <Button
                    size="sm"
                    variant="outline"
                    data-testid="carregar-andamentos"
                    @click="carregar"
                >
                    <History class="size-4" aria-hidden="true" />
                    Ver andamentos
                </Button>
            </div>
        </div>

        <p
            v-else-if="estado === 'carregando'"
            class="inline-flex items-center gap-2 rounded-lg border p-4 text-sm text-muted-foreground"
            role="status"
            data-testid="andamentos-carregando"
        >
            <LoaderCircle class="size-4 animate-spin" aria-hidden="true" />
            Consultando o DataJud do CNJ… pode levar até 1 minuto.
        </p>

        <div
            v-else-if="estado === 'erro'"
            class="grid gap-2 rounded-lg border border-destructive/40 p-4 text-sm"
            role="alert"
            data-testid="andamentos-erro"
        >
            <p>{{ mensagemErro }}</p>
            <div>
                <Button size="sm" variant="outline" @click="carregar">
                    <RotateCw class="size-4" aria-hidden="true" />
                    Tentar de novo
                </Button>
            </div>
        </div>

        <p
            v-else-if="processos.length === 0"
            class="rounded-lg border p-4 text-sm text-muted-foreground"
            data-testid="andamentos-vazio"
        >
            O DataJud não tem este processo. Ele pode estar em segredo de
            justiça ou ainda não ter sido enviado pelo tribunal ao CNJ.
        </p>

        <template v-else>
            <article
                v-for="(p, i) in processos"
                :key="`${p.grau}-${i}`"
                class="grid gap-3 rounded-lg border p-4"
                data-testid="andamentos-processo"
            >
                <header class="grid gap-1.5">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <Badge v-if="p.grauRotulo" variant="secondary">
                            {{ p.grauRotulo }}
                        </Badge>
                        <span v-if="p.orgaoJulgador" class="text-sm">
                            {{ p.orgaoJulgador }}
                        </span>
                    </div>
                    <p v-if="p.classe" class="text-sm font-medium">
                        {{ p.classe }}
                    </p>
                    <div
                        v-if="p.assuntos.length"
                        class="flex flex-wrap gap-1.5"
                    >
                        <Badge
                            v-for="a in p.assuntos"
                            :key="a"
                            variant="outline"
                            class="font-normal"
                        >
                            {{ a }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Ajuizado em {{ data(p.dataAjuizamento) }}
                        <template v-if="p.sistema"> · {{ p.sistema }}</template>
                        · atualizado no CNJ em {{ data(p.atualizadoEm) }}
                    </p>
                </header>

                <ol
                    v-if="p.movimentos.length"
                    class="grid gap-0 border-l pl-4"
                    :aria-label="`Andamentos (${p.movimentos.length})`"
                >
                    <li
                        v-for="(m, j) in visiveis[i]"
                        :key="`${m.dataHora}-${m.codigo}-${j}`"
                        class="relative pb-3 text-sm last:pb-0"
                        data-testid="movimento"
                    >
                        <span
                            class="absolute top-1.5 -left-[21px] size-2 rounded-full bg-primary"
                            aria-hidden="true"
                        />
                        <p class="text-xs text-muted-foreground tabular-nums">
                            {{ dataHora(m.dataHora) }}
                        </p>
                        <p class="font-medium">{{ m.nome }}</p>
                        <p
                            v-if="m.complementos.length"
                            class="text-muted-foreground"
                        >
                            {{ m.complementos.join(' · ') }}
                        </p>
                    </li>
                </ol>
                <p v-else class="text-sm text-muted-foreground">
                    Nenhum andamento registrado.
                </p>

                <div v-if="p.movimentos.length > LIMITE">
                    <Button
                        variant="ghost"
                        size="sm"
                        data-testid="alternar-movimentos"
                        @click="alternar(i)"
                    >
                        {{
                            expandidos.has(i)
                                ? 'Mostrar menos'
                                : `Mostrar todos (${p.movimentos.length})`
                        }}
                    </Button>
                </div>
            </article>

            <p class="text-xs text-muted-foreground">
                Fonte: DataJud (CNJ). Não é em tempo real: o tribunal envia os
                dados ao CNJ em lotes.
            </p>
        </template>
    </section>
</template>
