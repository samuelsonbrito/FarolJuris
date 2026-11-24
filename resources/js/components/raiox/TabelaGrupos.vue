<script setup lang="ts">
import { classeTaxa, percentual } from '@/lib/raiox';
import type { LinhaGrupo } from '@/types/raiox';

/**
 * Tabela de grupos do Raio-X. "completa" mostra êxito, parcial, acordo e insucesso;
 * a versão curta, "êxito + parcial".
 */
withDefaults(
    defineProps<{
        titulo?: string;
        linhas: LinhaGrupo[];
        coluna?: string;
        completa?: boolean;
    }>(),
    { titulo: undefined, coluna: 'Grupo', completa: false },
);
</script>

<template>
    <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
        <h3 v-if="titulo" class="border-b px-4 py-2.5 text-sm font-semibold">
            {{ titulo }}
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ coluna }}</th>
                        <th class="px-3 py-2 text-right font-medium">
                            Decididos
                        </th>
                        <template v-if="completa">
                            <th class="px-3 py-2 text-right font-medium">
                                Êxito
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Parcial
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Acordo
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Insucesso
                            </th>
                        </template>
                        <th v-else class="px-3 py-2 text-right font-medium">
                            Êxito + parcial
                        </th>
                        <th class="px-4 py-2 text-right font-medium">Taxa</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="l in linhas"
                        :key="l.rotulo"
                        class="border-t"
                        data-testid="linha-grupo"
                    >
                        <td class="px-4 py-2">{{ l.rotulo }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ l.decididos }}
                        </td>
                        <template v-if="completa">
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ l.exito }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ l.parcial }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ l.acordo }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ l.insucesso }}
                            </td>
                        </template>
                        <td v-else class="px-3 py-2 text-right tabular-nums">
                            {{ l.favoraveis }}
                        </td>
                        <td
                            class="px-4 py-2 text-right tabular-nums"
                            :class="classeTaxa(l.taxa)"
                        >
                            {{ percentual(l.taxa) }}
                        </td>
                    </tr>
                    <tr v-if="!linhas.length">
                        <td
                            :colspan="completa ? 7 : 4"
                            class="px-4 py-3 text-center text-muted-foreground"
                        >
                            Sem casos decididos.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
