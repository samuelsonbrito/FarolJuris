<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import { duracao } from '@/lib/raiox';
import type { ProgressoRaioX } from '@/types/raiox';

/** Barra de progresso do Raio-X em processamento, com a etapa e o tempo restante estimado. */
const props = defineProps<{ progresso: ProgressoRaioX }>();

const texto = computed(() => {
    const p = props.progresso;

    if (p.etapa === 'coleta') {
        return p.paginasTotal > 0
            ? `Coletando publicações no DJEN: página ${p.paginas} de ${p.paginasTotal}`
            : 'Coletando publicações no DJEN';
    }

    return `Lendo as decisões: ${p.classificados} de ${p.classificarTotal} processos`;
});
</script>

<template>
    <div class="grid min-w-52 gap-1.5" data-testid="progresso-raiox">
        <div class="flex items-center justify-between gap-2 text-xs">
            <span class="inline-flex items-center gap-1.5">
                <LoaderCircle
                    class="size-3.5 animate-spin text-primary"
                    aria-hidden="true"
                />
                {{ texto }}
            </span>
            <span class="font-semibold tabular-nums">
                {{ progresso.percentual }}%
            </span>
        </div>
        <div
            class="h-2 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="progresso.percentual"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="texto"
        >
            <div
                class="h-full rounded-full bg-primary transition-[width] duration-700"
                :style="{ width: `${progresso.percentual}%` }"
            />
        </div>
        <p class="text-xs text-muted-foreground">
            <template v-if="progresso.restanteSegundos !== null">
                Faltam cerca de {{ duracao(progresso.restanteSegundos) }}
            </template>
            <template v-if="progresso.decorridoSegundos !== null">
                · em andamento há {{ duracao(progresso.decorridoSegundos) }}
            </template>
        </p>
    </div>
</template>
