<script setup lang="ts">
import { computed } from 'vue';
import { partes, percentual } from '@/lib/raiox';
import type { LinhaGrupo } from '@/types/raiox';

/** Barra empilhada de resultados (êxito, parcial, acordo, insucesso) com a taxa escrita. */
const props = defineProps<{ linha: LinhaGrupo }>();

const segmentos = computed(() =>
    partes(props.linha).filter((s) => s.valor > 0),
);
const descricao = computed(() =>
    partes(props.linha)
        .map((s) => `${s.rotulo}: ${s.valor}`)
        .join(', '),
);
</script>

<template>
    <div
        class="grid items-center gap-x-4 gap-y-1.5 py-2 sm:grid-cols-[12rem_minmax(0,1fr)_3.5rem]"
        data-testid="barra-resultados"
    >
        <div>
            <p class="text-sm font-medium">{{ linha.rotulo }}</p>
            <p class="text-xs text-muted-foreground">
                {{ linha.decididos }}
                {{ linha.decididos === 1 ? 'decidido' : 'decididos' }}
            </p>
        </div>
        <div
            class="flex h-5 overflow-hidden rounded bg-muted"
            role="img"
            :aria-label="`${linha.rotulo}: ${descricao}`"
        >
            <div
                v-for="s in segmentos"
                :key="s.chave"
                class="h-full"
                :style="{ width: `${s.largura}%`, background: s.cor }"
                :title="`${s.rotulo}: ${s.valor}`"
            />
        </div>
        <p class="text-right text-lg font-semibold tabular-nums">
            {{ percentual(linha.taxa) }}
        </p>
    </div>
</template>
