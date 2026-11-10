<script setup lang="ts">
import { computed } from 'vue';
import { corDoTipo } from '@/lib/comunicacao';

/** Selo colorido do tipo de comunicação (Intimação, Citação, Edital...). */
const props = defineProps<{ tipo: string | null }>();

const classe = computed(() => corDoTipo(props.tipo));
</script>

<template>
    <span
        v-if="tipo"
        class="inline-flex w-fit items-center rounded-md border px-2 py-0.5 text-xs font-medium"
        :class="classe"
        data-testid="tipo-comunicacao"
    >
        {{ tipo }}
    </span>
</template>
