<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ChevronDown, Search, SlidersHorizontal, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    cnjCompleto,
    mascaraCnj,
    normalizarOab,
    somenteDigitos,
} from '@/lib/cnj';
import type { AtalhoData } from '@/lib/datas';
import { ATALHOS_DATA, atalhoAtivo, intervaloDoAtalho } from '@/lib/datas';
import type { FiltrosConsulta, Tribunal } from '@/types/djen';

const props = withDefaults(
    defineProps<{
        filtros: FiltrosConsulta;
        tribunais: Tribunal[];
        ufs: string[];
        /** Meios de publicação (código → nome), de ConsultaRequest::MEIOS. */
        meios?: Record<string, string>;
        erros?: Partial<Record<keyof FiltrosConsulta, string>>;
        carregando?: boolean;
        /** Espera (ms) após a digitação antes de buscar. */
        debounce?: number;
    }>(),
    { meios: () => ({}), erros: () => ({}), carregando: false, debounce: 700 },
);

const emit = defineEmits<{ buscar: [filtros: FiltrosConsulta] }>();

const form = useForm<Record<keyof FiltrosConsulta, string>>({
    oab: props.filtros.oab ?? '',
    uf: props.filtros.uf ?? '',
    parte: props.filtros.parte ?? '',
    advogado: props.filtros.advogado ?? '',
    processo: mascaraCnj(props.filtros.processo ?? ''),
    tribunal: props.filtros.tribunal ?? '',
    inicio: props.filtros.inicio ?? '',
    fim: props.filtros.fim ?? '',
    texto: props.filtros.texto ?? '',
    meio: props.filtros.meio ?? '',
    comunicacao: props.filtros.comunicacao ?? '',
});

/** Os filtros avançados começam abertos quando algum deles veio na URL. */
const avancadosAbertos = ref(
    Boolean(props.filtros.meio || props.filtros.comunicacao),
);

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30';

function normalizado(): FiltrosConsulta {
    const valor = (v: string) => (v.trim() === '' ? null : v.trim());

    return {
        oab: valor(normalizarOab(form.oab)),
        uf: valor(form.uf),
        parte: valor(form.parte),
        advogado: valor(form.advogado),
        processo: valor(somenteDigitos(form.processo)),
        tribunal: valor(form.tribunal),
        inicio: valor(form.inicio),
        fim: valor(form.fim),
        texto: valor(form.texto),
        meio: valor(form.meio),
        comunicacao: valor(somenteDigitos(form.comunicacao)),
    };
}

/** Evita bater no CNJ com filtros que certamente seriam recusados. */
const prontoParaBuscaAutomatica = computed(() => {
    const f = normalizado();
    const temCriterio = Boolean(
        f.oab || f.parte || f.advogado || f.processo || f.tribunal || f.texto,
    );
    const textoCurto = [f.parte, f.advogado, f.texto].some(
        (v) => v !== null && v.length < 3,
    );
    const processoIncompleto = f.processo !== null && !cnjCompleto(f.processo);
    const oabSemUf = Boolean(f.oab) !== Boolean(f.uf);

    return temCriterio && !textoCurto && !processoIncompleto && !oabSemUf;
});

let timer: ReturnType<typeof setTimeout> | undefined;

function cancelarDebounce() {
    clearTimeout(timer);
    timer = undefined;
}

function buscarAgora() {
    // Uma busca imediata invalida qualquer busca com debounce pendente; sem
    // isso, o debounce disparava depois e atropelava a navegação (ex.: página 2).
    cancelarDebounce();
    emit('buscar', normalizado());
}

/** Os filtros do formulário já são os da URL atual? */
function igualAosFiltrosAtuais(): boolean {
    const atual = normalizado();

    return (Object.keys(atual) as (keyof FiltrosConsulta)[]).every(
        (chave) => (atual[chave] ?? null) === (props.filtros[chave] ?? null),
    );
}

function buscarComDebounce() {
    cancelarDebounce();
    timer = setTimeout(() => {
        timer = undefined;

        if (prontoParaBuscaAutomatica.value && !igualAosFiltrosAtuais()) {
            buscarAgora();
        }
    }, props.debounce);
}

onBeforeUnmount(cancelarDebounce);

function aoDigitarProcesso(valor: string | number) {
    form.processo = mascaraCnj(String(valor));
    buscarComDebounce();
}

function aoMudarSelecaoOuData() {
    if (prontoParaBuscaAutomatica.value) {
        buscarAgora();
    }
}

const atalho = computed(() => atalhoAtivo(form.inicio, form.fim));

function aplicarAtalho(chave: AtalhoData) {
    const { inicio, fim } = intervaloDoAtalho(chave);
    form.inicio = inicio ?? '';
    form.fim = fim ?? '';
    aoMudarSelecaoOuData();
}

function limpar() {
    form.oab = '';
    form.uf = '';
    form.parte = '';
    form.advogado = '';
    form.processo = '';
    form.tribunal = '';
    form.inicio = '';
    form.fim = '';
    form.texto = '';
    form.meio = '';
    form.comunicacao = '';
    buscarAgora();
}

defineExpose({ form });
</script>

<template>
    <form
        class="grid gap-4 rounded-xl border bg-card p-5 shadow-xs"
        role="search"
        aria-label="Filtros da consulta"
        @submit.prevent="buscarAgora"
    >
        <div class="grid gap-4 md:grid-cols-[1fr_6rem_1fr]">
            <div class="grid gap-1.5">
                <Label for="filtro-oab">Número da OAB</Label>
                <Input
                    id="filtro-oab"
                    v-model="form.oab"
                    name="oab"
                    autocapitalize="characters"
                    autocomplete="off"
                    placeholder="Ex.: 12345 ou 12345-A"
                    :aria-invalid="Boolean(erros.oab)"
                    @update:model-value="buscarComDebounce"
                />
                <InputError :message="erros.oab" />
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-uf">UF</Label>
                <select
                    id="filtro-uf"
                    v-model="form.uf"
                    name="uf"
                    :class="selectClass"
                    :aria-invalid="Boolean(erros.uf)"
                    @change="aoMudarSelecaoOuData"
                >
                    <option value="">—</option>
                    <option v-for="uf in ufs" :key="uf" :value="uf">
                        {{ uf }}
                    </option>
                </select>
                <InputError :message="erros.uf" />
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-processo">Número do processo</Label>
                <Input
                    id="filtro-processo"
                    :model-value="form.processo"
                    name="processo"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="0000000-00.0000.0.00.0000"
                    class="font-mono"
                    :aria-invalid="Boolean(erros.processo)"
                    @update:model-value="aoDigitarProcesso"
                />
                <InputError :message="erros.processo" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-1.5">
                <Label for="filtro-parte">Nome da parte</Label>
                <Input
                    id="filtro-parte"
                    v-model="form.parte"
                    name="parte"
                    autocomplete="off"
                    placeholder="Pessoa ou empresa"
                    :aria-invalid="Boolean(erros.parte)"
                    @update:model-value="buscarComDebounce"
                />
                <InputError :message="erros.parte" />
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-advogado">Nome do advogado</Label>
                <Input
                    id="filtro-advogado"
                    v-model="form.advogado"
                    name="advogado"
                    autocomplete="off"
                    :aria-invalid="Boolean(erros.advogado)"
                    @update:model-value="buscarComDebounce"
                />
                <InputError :message="erros.advogado" />
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-texto">Palavra-chave no teor</Label>
                <Input
                    id="filtro-texto"
                    v-model="form.texto"
                    name="texto"
                    autocomplete="off"
                    placeholder="Ex.: alienação fiduciária"
                    aria-describedby="filtro-texto-ajuda"
                    :aria-invalid="Boolean(erros.texto)"
                    @update:model-value="buscarComDebounce"
                />
                <p
                    id="filtro-texto-ajuda"
                    class="text-xs text-muted-foreground"
                >
                    Procura dentro do texto da comunicação.
                </p>
                <InputError :message="erros.texto" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-1.5">
                <Label for="filtro-tribunal">Tribunal</Label>
                <select
                    id="filtro-tribunal"
                    v-model="form.tribunal"
                    name="tribunal"
                    :class="selectClass"
                    :aria-invalid="Boolean(erros.tribunal)"
                    @change="aoMudarSelecaoOuData"
                >
                    <option value="">Todos</option>
                    <option
                        v-for="t in tribunais"
                        :key="t.sigla"
                        :value="t.sigla"
                    >
                        {{ t.sigla }} — {{ t.nome }}
                    </option>
                </select>
                <InputError :message="erros.tribunal" />
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-inicio">Disponibilizada de</Label>
                <Input
                    id="filtro-inicio"
                    v-model="form.inicio"
                    name="inicio"
                    type="date"
                    :aria-invalid="Boolean(erros.inicio)"
                    @change="aoMudarSelecaoOuData"
                />
                <InputError :message="erros.inicio" />
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-fim">até</Label>
                <Input
                    id="filtro-fim"
                    v-model="form.fim"
                    name="fim"
                    type="date"
                    :min="form.inicio || undefined"
                    :aria-invalid="Boolean(erros.fim)"
                    @change="aoMudarSelecaoOuData"
                />
                <InputError :message="erros.fim" />
            </div>
        </div>

        <div
            class="flex flex-wrap gap-1.5"
            role="group"
            aria-label="Atalhos de período"
        >
            <button
                v-for="a in ATALHOS_DATA"
                :key="a.chave"
                type="button"
                class="rounded-full border px-3 py-1 text-xs transition-colors"
                :class="
                    atalho === a.chave
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                "
                :aria-pressed="atalho === a.chave"
                :data-testid="`atalho-${a.chave}`"
                @click="aplicarAtalho(a.chave)"
            >
                {{ a.rotulo }}
            </button>
        </div>

        <div class="rounded-lg border border-dashed">
            <button
                type="button"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-muted-foreground hover:text-foreground"
                :aria-expanded="avancadosAbertos"
                aria-controls="filtros-avancados"
                data-testid="alternar-avancados"
                @click="avancadosAbertos = !avancadosAbertos"
            >
                <SlidersHorizontal class="size-4" aria-hidden="true" />
                Filtros avançados
                <span class="font-normal">(meio e nº da comunicação)</span>
                <ChevronDown
                    class="ml-auto size-4 transition-transform"
                    :class="avancadosAbertos && 'rotate-180'"
                    aria-hidden="true"
                />
            </button>
            <div
                v-show="avancadosAbertos"
                id="filtros-avancados"
                class="grid gap-4 border-t px-3 pt-3 pb-4 md:grid-cols-3"
            >
                <div class="grid gap-1.5">
                    <Label for="filtro-meio">Meio de publicação</Label>
                    <select
                        id="filtro-meio"
                        v-model="form.meio"
                        name="meio"
                        :class="selectClass"
                        :aria-invalid="Boolean(erros.meio)"
                        @change="aoMudarSelecaoOuData"
                    >
                        <option value="">Todos</option>
                        <option
                            v-for="(nome, codigo) in meios"
                            :key="codigo"
                            :value="codigo"
                        >
                            {{ nome }}
                        </option>
                    </select>
                    <InputError :message="erros.meio" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="filtro-comunicacao">Nº da comunicação</Label>
                    <Input
                        id="filtro-comunicacao"
                        v-model="form.comunicacao"
                        name="comunicacao"
                        inputmode="numeric"
                        autocomplete="off"
                        :aria-invalid="Boolean(erros.comunicacao)"
                        @update:model-value="buscarComDebounce"
                    />
                    <InputError :message="erros.comunicacao" />
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <Button
                type="button"
                variant="ghost"
                data-testid="limpar"
                @click="limpar"
            >
                <X class="size-4" aria-hidden="true" />
                Limpar
            </Button>
            <Button type="submit" :disabled="carregando" data-testid="buscar">
                <Search class="size-4" aria-hidden="true" />
                Buscar
            </Button>
        </div>
    </form>
</template>
