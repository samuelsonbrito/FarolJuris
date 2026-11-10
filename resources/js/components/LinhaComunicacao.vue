<script setup lang="ts">
import { Copy, Eye } from '@lucide/vue';
import { computed } from 'vue';
import TipoComunicacaoBadge from '@/components/TipoComunicacaoBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatarData } from '@/lib/cnj';
import { copiarTexto, resumoPartes } from '@/lib/comunicacao';
import type { Comunicacao } from '@/types/djen';

/**
 * Uma comunicação em linha compacta (lista da Consulta). Em telas largas as
 * colunas seguem o cabeçalho de ListaComunicacoes; no celular, empilham.
 */
const props = defineProps<{
    comunicacao: Comunicacao;
    /** Mostra a caixa de seleção (exportação em lote). */
    selecionavel?: boolean;
}>();

const selecionada = defineModel<boolean>('selecionada', { default: false });

defineEmits<{ verInteiroTeor: [comunicacao: Comunicacao] }>();

const MAX_OABS = 3;

const partes = computed(() => resumoPartes(props.comunicacao.destinatarios));

const oabs = computed(() =>
    props.comunicacao.advogados
        .filter((a) => a.numeroOab)
        .map((a) => `${a.numeroOab}${a.ufOab ? `/${a.ufOab}` : ''}`),
);

const nomesAdvogados = computed(() =>
    props.comunicacao.advogados.map((a) => a.nome).join(' · '),
);
</script>

<template>
    <article
        class="grid gap-x-4 gap-y-3 border-b px-4 py-4 last:border-b-0 hover:bg-muted/40 lg:grid-cols-[7.5rem_minmax(0,1.7fr)_minmax(0,1fr)_minmax(0,1.5fr)_auto] lg:items-start"
        :class="{ 'bg-primary/5': selecionavel && selecionada }"
        :aria-labelledby="`processo-${comunicacao.hash}`"
        data-testid="linha-comunicacao"
    >
        <!-- Disponibilização, tribunal e meio -->
        <div
            class="flex flex-wrap items-center gap-2 lg:flex-col lg:items-start"
        >
            <div class="flex items-center gap-2">
                <input
                    v-if="selecionavel"
                    v-model="selecionada"
                    type="checkbox"
                    class="size-4 cursor-pointer accent-primary"
                    :aria-label="`Selecionar ${comunicacao.numeroProcesso}`"
                    data-testid="selecionar"
                />
                <time
                    :datetime="comunicacao.dataDisponibilizacao"
                    class="text-sm font-medium tabular-nums"
                >
                    {{ formatarData(comunicacao.dataDisponibilizacao) }}
                </time>
            </div>
            <div class="flex gap-1">
                <Badge data-testid="tribunal">{{ comunicacao.tribunal }}</Badge>
                <Badge
                    v-if="comunicacao.meio"
                    variant="outline"
                    :title="comunicacao.meioCompleto ?? undefined"
                >
                    {{ comunicacao.meio }}
                    <span class="sr-only">{{ comunicacao.meioCompleto }}</span>
                </Badge>
            </div>
        </div>

        <!-- Processo, classe e órgão -->
        <div class="min-w-0">
            <div class="flex items-center gap-1">
                <h3
                    :id="`processo-${comunicacao.hash}`"
                    class="font-mono text-sm font-semibold tracking-tight"
                >
                    <button
                        type="button"
                        class="rounded-sm text-left underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :title="`Ver detalhes de ${comunicacao.numeroProcesso}`"
                        data-testid="abrir-processo"
                        @click="$emit('verInteiroTeor', comunicacao)"
                    >
                        {{ comunicacao.numeroProcesso }}
                    </button>
                </h3>
                <button
                    type="button"
                    class="rounded-sm p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                    :aria-label="`Copiar o número ${comunicacao.numeroProcesso} (só dígitos)`"
                    title="Copiar nº (só dígitos)"
                    data-testid="copiar-processo"
                    @click="
                        copiarTexto(
                            comunicacao.numeroProcessoDigitos,
                            'Número do processo copiado (só dígitos).',
                        )
                    "
                >
                    <Copy class="size-3.5" aria-hidden="true" />
                </button>
            </div>
            <p
                v-if="comunicacao.classe"
                class="mt-0.5 truncate text-xs text-foreground/80"
                :title="comunicacao.classe"
                data-testid="classe"
            >
                {{ comunicacao.classe }}
            </p>
            <p
                v-if="comunicacao.orgaoJulgador"
                class="truncate text-xs text-muted-foreground"
                :title="comunicacao.orgaoJulgador"
            >
                {{ comunicacao.orgaoJulgador }}
            </p>
        </div>

        <!-- Tipo -->
        <div class="flex min-w-0 flex-col gap-1">
            <TipoComunicacaoBadge :tipo="comunicacao.tipoComunicacao" />
            <p
                v-if="comunicacao.tipoDocumento"
                class="truncate text-xs text-muted-foreground uppercase"
                :title="comunicacao.tipoDocumento"
            >
                {{ comunicacao.tipoDocumento }}
            </p>
        </div>

        <!-- Partes e advogados -->
        <div class="min-w-0">
            <p
                class="truncate text-sm font-medium uppercase"
                :title="
                    comunicacao.destinatarios.map((d) => d.nome).join(' · ')
                "
                data-testid="partes"
            >
                <template v-if="partes.ativo.length">{{
                    partes.ativo.join(', ')
                }}</template>
                <template v-if="partes.ativo.length && partes.passivo.length">
                    <span class="px-1 text-muted-foreground" aria-label="contra"
                        >×</span
                    >
                </template>
                <span
                    v-if="partes.passivo.length"
                    class="font-normal text-foreground/80 italic"
                    >{{ partes.passivo.join(', ') }}</span
                >
                <span
                    v-if="partes.restantes"
                    class="ml-1 text-xs font-normal text-muted-foreground"
                    >+{{ partes.restantes }}</span
                >
                <span
                    v-if="!comunicacao.destinatarios.length"
                    class="font-normal text-muted-foreground"
                    >—</span
                >
            </p>
            <div
                v-if="oabs.length"
                class="mt-1 flex flex-wrap gap-1"
                :title="nomesAdvogados"
                data-testid="oabs"
            >
                <span
                    v-for="oab in oabs.slice(0, MAX_OABS)"
                    :key="oab"
                    class="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground"
                >
                    {{ oab }}
                </span>
                <span
                    v-if="oabs.length > MAX_OABS"
                    class="px-1 text-[11px] text-muted-foreground"
                    >+{{ oabs.length - MAX_OABS }}</span
                >
            </div>
        </div>

        <!-- Ações -->
        <div class="flex items-start lg:justify-end">
            <Button
                variant="outline"
                size="sm"
                :aria-label="`Ver detalhes de ${comunicacao.numeroProcesso}`"
                data-testid="ver-inteiro-teor"
                @click="$emit('verInteiroTeor', comunicacao)"
            >
                <Eye class="size-4" aria-hidden="true" />
                <span class="lg:sr-only">Ver detalhes</span>
            </Button>
        </div>
    </article>
</template>
