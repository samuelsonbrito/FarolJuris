<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Calculator } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { calcularCusto, decimal, dinheiro } from '@/lib/raiox';
import type { CustoRaioX } from '@/types/raiox';

/**
 * "Quanto cada êxito precisa pagar". O custo é informado pelo advogado; a conta
 * acompanha a digitação e o botão salva no relatório.
 */
const props = defineProps<{
    relatorioId: number;
    custo: CustoRaioX;
    /** Pode salvar (dono ou admin). */
    editavel?: boolean;
}>();

const form = useForm({
    custoMensal: (props.custo.custoMensal ?? '') as number | string,
    custoVariavel: (props.custo.custoVariavel ?? '') as number | string,
    honorarioMedio: (props.custo.honorarioMedio ?? '') as number | string,
});

const numero = (v: unknown): number | null =>
    v === null || v === '' || v === undefined || Number.isNaN(Number(v))
        ? null
        : Number(v);

const conta = computed(() =>
    calcularCusto(
        props.custo.ativos,
        props.custo.exitos,
        numero(form.custoMensal),
        numero(form.custoVariavel),
        numero(form.honorarioMedio),
    ),
);

function salvar() {
    form.transform((d) => ({
        custoMensal: numero(d.custoMensal),
        custoVariavel: numero(d.custoVariavel),
        honorarioMedio: numero(d.honorarioMedio),
    })).put(route('raiox.custo', props.relatorioId), { preserveScroll: true });
}
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <div
            class="grid content-start gap-3 rounded-xl bg-[var(--marca-marinho)] p-5 text-white shadow-xs"
            data-testid="destaque-custo"
        >
            <div>
                <p class="text-xs text-white/70">
                    Processos ativos para cada êxito
                </p>
                <p class="text-4xl font-semibold tabular-nums">
                    {{ decimal(conta.processosPorExito) }}
                </p>
                <p class="text-xs text-white/70">
                    {{ custo.ativos }} processos ativos ÷ {{ custo.exitos }}
                    êxitos no período
                </p>
            </div>
            <div>
                <p class="text-xs text-white/70">Honorário mínimo por êxito</p>
                <p
                    class="text-2xl font-semibold tabular-nums"
                    data-testid="honorario-minimo"
                >
                    {{ dinheiro(conta.honorarioMinimo) }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 rounded-xl border bg-card p-5 shadow-xs">
            <p class="text-sm text-muted-foreground">
                Na advocacia por êxito, só o processo ganho remunera. Informe o
                custo do escritório para saber o piso abaixo do qual um processo
                não se paga.
            </p>

            <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="salvar">
                <div class="grid gap-1.5">
                    <Label for="custo-mensal">Custo fixo mensal (R$)</Label>
                    <Input
                        id="custo-mensal"
                        v-model="form.custoMensal"
                        type="number"
                        min="0"
                        step="100"
                        inputmode="decimal"
                        :disabled="!editavel"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="custo-variavel">
                        Custo variável por processo/ano (R$)
                    </Label>
                    <Input
                        id="custo-variavel"
                        v-model="form.custoVariavel"
                        type="number"
                        min="0"
                        step="10"
                        inputmode="decimal"
                        :disabled="!editavel"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="honorario-medio">
                        Honorário médio por êxito (R$)
                    </Label>
                    <Input
                        id="honorario-medio"
                        v-model="form.honorarioMedio"
                        type="number"
                        min="0"
                        step="100"
                        inputmode="decimal"
                        :disabled="!editavel"
                    />
                </div>
                <div v-if="editavel" class="sm:col-span-3">
                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        :disabled="form.processing"
                    >
                        <Calculator class="size-4" aria-hidden="true" />
                        Salvar custos
                    </Button>
                </div>
            </form>

            <dl class="grid gap-3 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-xs text-muted-foreground">Custo no ano</dt>
                    <dd class="font-semibold tabular-nums">
                        {{ dinheiro(conta.custoTotal) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Custo por processo ativo
                    </dt>
                    <dd class="font-semibold tabular-nums">
                        {{ dinheiro(conta.custoPorProcesso) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Honorário mínimo por êxito
                    </dt>
                    <dd class="font-semibold tabular-nums">
                        {{ dinheiro(conta.honorarioMinimo) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Margem por êxito
                    </dt>
                    <dd
                        class="font-semibold tabular-nums"
                        :class="
                            conta.margem !== null && conta.margem < 0
                                ? 'text-destructive'
                                : ''
                        "
                        data-testid="margem"
                    >
                        {{ dinheiro(conta.margem) }}
                    </dd>
                </div>
            </dl>

            <p class="text-xs text-muted-foreground">
                Não entram custas, diligências e correspondentes, que aumentam
                esse piso. "Ativos" são os processos com alguma publicação no
                DJEN no período; processos parados não aparecem, então o número
                real pode ser maior.
            </p>
        </div>
    </div>
</template>
