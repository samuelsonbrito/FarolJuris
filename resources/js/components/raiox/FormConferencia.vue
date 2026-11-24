<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Check, ExternalLink } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatarData } from '@/lib/cnj';
import type { Opcao, ProcessoRaioX } from '@/types/raiox';

/** Um processo na conferência: o que a IA concluiu, o texto e o formulário para corrigir. */
const props = defineProps<{
    processo: ProcessoRaioX;
    opcoes: {
        resultados: Opcao[];
        instancias: Opcao[];
        grupos: Opcao[];
        temas: Opcao[];
    };
}>();

const p = props.processo;
const form = useForm({
    resultado: p.resultado ?? 'sem_decisao',
    extincaoSemMerito: p.extincaoSemMerito,
    instancia: p.instancia ?? '',
    orgaoJulgador: p.orgaoJulgador ?? '',
    varaOrigem: p.varaOrigem ?? '',
    grupoOrigem: p.grupoOrigem ?? '',
    tema: p.tema ?? '',
    parteContraria: p.parteContraria ?? '',
    poloCliente: p.poloCliente ?? 'indefinido',
});

const verTexto = ref(false);

function confirmar() {
    form.transform((d) => ({
        ...d,
        instancia: d.instancia || null,
        grupoOrigem: d.grupoOrigem || null,
        tema: d.tema || null,
    })).patch(route('admin.raiox.conferir', p.id), { preserveScroll: true });
}

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
const id = (campo: string) => `conf-${p.id}-${campo}`;
</script>

<template>
    <article
        class="grid gap-4 rounded-xl border bg-card p-4 shadow-xs"
        :class="processo.precisaConferencia && 'border-amber-400/70'"
        data-testid="processo-conferencia"
    >
        <header class="flex flex-wrap items-center gap-2">
            <Link
                :href="route('processos.show', processo.numero)"
                class="inline-flex items-center gap-1 font-mono text-sm font-semibold hover:underline"
                target="_blank"
            >
                {{ processo.numeroMascara }}
                <ExternalLink class="size-3.5" aria-hidden="true" />
            </Link>
            <Badge v-if="processo.tribunal" variant="outline">
                {{ processo.tribunal }}
            </Badge>
            <Badge
                v-if="processo.precisaConferencia"
                class="bg-amber-500 text-white"
            >
                Precisa conferir
            </Badge>
            <Badge v-else-if="processo.conferidoEm" variant="secondary">
                Conferido
            </Badge>
            <span
                v-if="processo.confianca"
                class="text-xs text-muted-foreground"
            >
                Confiança da IA: {{ processo.confianca }}
            </span>
            <span
                v-if="processo.dataDecisao"
                class="ml-auto text-xs text-muted-foreground"
            >
                Decisão em {{ formatarData(processo.dataDecisao) }}
            </span>
        </header>

        <p
            v-if="processo.justificativa"
            class="rounded-lg bg-muted/50 p-3 text-sm"
        >
            {{ processo.justificativa }}
        </p>

        <div v-if="processo.textoDecisao" class="text-sm">
            <button
                type="button"
                class="text-xs font-medium text-primary hover:underline"
                :aria-expanded="verTexto"
                @click="verTexto = !verTexto"
            >
                {{ verTexto ? 'Esconder' : 'Ler' }} a publicação da decisão
            </button>
            <p
                v-if="verTexto"
                class="mt-2 max-h-72 overflow-y-auto rounded-lg border p-3 text-xs leading-relaxed whitespace-pre-wrap"
            >
                {{ processo.textoDecisao }}
            </p>
        </div>

        <form
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
            @submit.prevent="confirmar"
        >
            <div class="grid gap-1">
                <Label :for="id('resultado')">Resultado (para o cliente)</Label>
                <select
                    :id="id('resultado')"
                    v-model="form.resultado"
                    :class="selectClass"
                >
                    <option
                        v-for="o in opcoes.resultados"
                        :key="o.valor"
                        :value="o.valor"
                    >
                        {{ o.rotulo }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1">
                <Label :for="id('instancia')">Instância</Label>
                <select
                    :id="id('instancia')"
                    v-model="form.instancia"
                    :class="selectClass"
                >
                    <option value="">—</option>
                    <option
                        v-for="o in opcoes.instancias"
                        :key="o.valor"
                        :value="o.valor"
                    >
                        {{ o.rotulo }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1">
                <Label :for="id('polo')">Cliente no polo</Label>
                <select
                    :id="id('polo')"
                    v-model="form.poloCliente"
                    :class="selectClass"
                >
                    <option value="ativo">Ativo (autor)</option>
                    <option value="passivo">Passivo (réu)</option>
                    <option value="indefinido">Indefinido</option>
                </select>
            </div>
            <div class="grid gap-1">
                <Label :for="id('tema')">Tema</Label>
                <select
                    :id="id('tema')"
                    v-model="form.tema"
                    :class="selectClass"
                >
                    <option value="">—</option>
                    <option
                        v-for="o in opcoes.temas"
                        :key="o.valor"
                        :value="o.valor"
                    >
                        {{ o.rotulo }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1">
                <Label :for="id('orgao')">Órgão que julgou</Label>
                <Input :id="id('orgao')" v-model="form.orgaoJulgador" />
            </div>
            <div class="grid gap-1">
                <Label :for="id('vara')">Vara de origem</Label>
                <Input
                    :id="id('vara')"
                    v-model="form.varaOrigem"
                    placeholder="Ex.: 22ª Vara Cível"
                />
            </div>
            <div class="grid gap-1">
                <Label :for="id('grupo')">Grupo de origem</Label>
                <select
                    :id="id('grupo')"
                    v-model="form.grupoOrigem"
                    :class="selectClass"
                >
                    <option value="">—</option>
                    <option
                        v-for="o in opcoes.grupos"
                        :key="o.valor"
                        :value="o.valor"
                    >
                        {{ o.rotulo }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1">
                <Label :for="id('parte')">Parte contrária</Label>
                <Input
                    :id="id('parte')"
                    v-model="form.parteContraria"
                    placeholder="Ex.: Bradesco"
                />
            </div>
            <label class="flex items-center gap-2 text-sm sm:col-span-2">
                <input
                    v-model="form.extincaoSemMerito"
                    type="checkbox"
                    class="size-4"
                />
                Extinção sem resolução de mérito
            </label>
            <div class="flex justify-end sm:col-span-2">
                <Button
                    type="submit"
                    size="sm"
                    :disabled="form.processing"
                    data-testid="confirmar-conferencia"
                >
                    <Check class="size-4" aria-hidden="true" />
                    Confirmar classificação
                </Button>
            </div>
        </form>
    </article>
</template>
