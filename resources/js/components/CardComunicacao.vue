<script setup lang="ts">
import { CalendarDays, FileText, Landmark, Scale, Users } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatarData } from '@/lib/cnj';
import type { Comunicacao } from '@/types/djen';

const props = defineProps<{
    comunicacao: Comunicacao;
    /** Mostra a caixa de seleção (exportação em lote). */
    selecionavel?: boolean;
}>();

const selecionada = defineModel<boolean>('selecionada', { default: false });

defineEmits<{ verInteiroTeor: [comunicacao: Comunicacao] }>();

const advogados = computed(() =>
    props.comunicacao.advogados.map((a) =>
        a.numeroOab
            ? `${a.nome} (OAB ${a.numeroOab}${a.ufOab ? `/${a.ufOab}` : ''})`
            : a.nome,
    ),
);
</script>

<template>
    <article
        class="rounded-xl border bg-card p-5 text-card-foreground shadow-xs transition-shadow hover:shadow-md"
        :class="{ 'ring-2 ring-primary/40': selecionavel && selecionada }"
        :aria-labelledby="`processo-${comunicacao.hash}`"
    >
        <header class="flex flex-wrap items-center gap-2">
            <input
                v-if="selecionavel"
                v-model="selecionada"
                type="checkbox"
                class="size-4 cursor-pointer accent-primary"
                :aria-label="`Selecionar ${comunicacao.numeroProcesso}`"
                data-testid="selecionar"
            />
            <Badge data-testid="tribunal">{{ comunicacao.tribunal }}</Badge>
            <Badge v-if="comunicacao.tipoComunicacao" variant="secondary">
                {{ comunicacao.tipoComunicacao }}
            </Badge>
            <Badge v-if="comunicacao.tipoDocumento" variant="outline">
                {{ comunicacao.tipoDocumento }}
            </Badge>
            <span
                class="ml-auto inline-flex items-center gap-1 text-xs text-muted-foreground"
            >
                <CalendarDays class="size-3.5" aria-hidden="true" />
                <span class="sr-only">Disponibilizada em</span>
                <time :datetime="comunicacao.dataDisponibilizacao">{{
                    formatarData(comunicacao.dataDisponibilizacao)
                }}</time>
            </span>
        </header>

        <h3
            :id="`processo-${comunicacao.hash}`"
            class="mt-3 font-mono text-base font-semibold tracking-tight"
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

        <dl class="mt-3 grid gap-2 text-sm">
            <div v-if="comunicacao.orgaoJulgador" class="flex gap-2">
                <dt class="shrink-0 text-muted-foreground">
                    <Landmark class="size-4" aria-hidden="true" />
                    <span class="sr-only">Órgão julgador</span>
                </dt>
                <dd>{{ comunicacao.orgaoJulgador }}</dd>
            </div>
            <div v-if="comunicacao.destinatarios.length" class="flex gap-2">
                <dt class="shrink-0 text-muted-foreground">
                    <Users class="size-4" aria-hidden="true" />
                    <span class="sr-only">Destinatários</span>
                </dt>
                <dd data-testid="destinatarios">
                    {{
                        comunicacao.destinatarios.map((d) => d.nome).join(' · ')
                    }}
                </dd>
            </div>
            <div v-if="advogados.length" class="flex gap-2">
                <dt class="shrink-0 text-muted-foreground">
                    <Scale class="size-4" aria-hidden="true" />
                    <span class="sr-only">Advogados</span>
                </dt>
                <dd data-testid="advogados">{{ advogados.join(' · ') }}</dd>
            </div>
        </dl>

        <p
            class="mt-3 line-clamp-3 text-sm leading-relaxed text-muted-foreground"
            data-testid="trecho"
        >
            {{ comunicacao.trecho }}
        </p>

        <footer class="mt-4 flex items-center justify-between gap-2">
            <slot name="acoes" />
            <Button
                variant="outline"
                size="sm"
                class="ml-auto"
                data-testid="ver-inteiro-teor"
                @click="$emit('verInteiroTeor', comunicacao)"
            >
                <FileText class="size-4" aria-hidden="true" />
                Ver detalhes
            </Button>
        </footer>
    </article>
</template>
