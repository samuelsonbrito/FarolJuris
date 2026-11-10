<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

withDefaults(defineProps<{ quantidade?: number }>(), { quantidade: 5 });
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border bg-card"
        aria-busy="true"
        aria-label="Carregando resultados"
    >
        <div
            v-for="n in quantidade"
            :key="n"
            class="grid gap-x-4 gap-y-2 border-b px-4 py-4 last:border-b-0 lg:grid-cols-[7.5rem_minmax(0,1.7fr)_minmax(0,1fr)_minmax(0,1.5fr)_auto]"
            data-testid="skeleton"
        >
            <div class="grid gap-2">
                <Skeleton class="h-4 w-20" />
                <Skeleton class="h-5 w-16 rounded-full" />
            </div>
            <div class="grid gap-2">
                <Skeleton class="h-4 w-56" />
                <Skeleton class="h-3 w-48" />
                <Skeleton class="h-3 w-40" />
            </div>
            <div class="grid gap-2">
                <Skeleton class="h-5 w-28 rounded-md" />
                <Skeleton class="h-3 w-32" />
            </div>
            <div class="grid gap-2">
                <Skeleton class="h-4 w-64" />
                <Skeleton class="h-4 w-24" />
            </div>
            <Skeleton class="h-8 w-10" />
        </div>
    </div>
</template>
