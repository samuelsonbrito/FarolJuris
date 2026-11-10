<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Copy, ExternalLink, FileX, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatarData } from '@/lib/cnj';
import type { Comunicacao } from '@/types/djen';

/**
 * Painel lateral com os detalhes de uma comunicação: dados do processo,
 * partes, advogados e o teor (formatado, texto puro ou dados).
 */
const props = defineProps<{ comunicacao: Comunicacao | null }>();

const aberto = defineModel<boolean>('open', { default: false });

type Visao = 'formatado' | 'texto' | 'dados';
const visao = ref<Visao>('formatado');
const visoes: { valor: Visao; rotulo: string }[] = [
    { valor: 'formatado', rotulo: 'Formatado' },
    { valor: 'texto', rotulo: 'Texto' },
    { valor: 'dados', rotulo: 'Dados' },
];

// Cada comunicação abre na visão formatada.
watch(
    () => props.comunicacao?.hash,
    () => (visao.value = 'formatado'),
);

const POLOS: Record<string, string> = { A: 'Ativo', P: 'Passivo' };

function rotuloPolo(polo: string | null): string | null {
    if (!polo) {
        return null;
    }

    return POLOS[polo] ?? `Polo ${polo}`;
}

const MEIOS_CURTOS: Record<string, string> = { D: 'Diário', E: 'Editais' };

/** Texto puro do teor, com as quebras de parágrafo (o HTML já vem sanitizado). */
const textoPuro = computed(() => {
    const html = props.comunicacao?.textoHtml ?? '';

    if (typeof DOMParser === 'undefined' || html === '') {
        return '';
    }

    const comQuebras = html
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/(p|div|li|tr|h[1-6]|blockquote|pre)>/gi, '\n');
    const texto =
        new DOMParser().parseFromString(comQuebras, 'text/html').body
            .textContent ?? '';

    return texto.replace(/\n{3,}/g, '\n\n').trim();
});

const dados = computed(() => {
    if (!props.comunicacao) {
        return '';
    }

    const { textoHtml: _html, trecho: _trecho, ...resto } = props.comunicacao;

    return JSON.stringify({ ...resto, texto: textoPuro.value }, null, 2);
});

async function copiar(texto: string, mensagem: string) {
    try {
        await navigator.clipboard.writeText(texto);
        toast.success(mensagem);
    } catch {
        toast.error('Não foi possível copiar. Selecione e copie manualmente.');
    }
}

function copiarTeor() {
    const conteudo = visao.value === 'dados' ? dados.value : textoPuro.value;
    void copiar(conteudo, 'Teor copiado.');
}
</script>

<template>
    <Sheet v-model:open="aberto">
        <SheetContent
            v-if="comunicacao"
            side="right"
            class="flex w-full flex-col gap-0 overflow-y-auto p-0 sm:max-w-2xl"
            data-testid="painel-comunicacao"
        >
            <SheetHeader class="border-b px-6 py-5 text-left">
                <SheetTitle class="text-lg">
                    <template v-if="comunicacao.id">
                        Comunicação nº {{ comunicacao.id }}
                    </template>
                    <template v-else>Comunicação</template>
                </SheetTitle>
                <SheetDescription>
                    {{ comunicacao.tribunal }}
                    <template v-if="comunicacao.orgaoJulgador">
                        · {{ comunicacao.orgaoJulgador }}
                    </template>
                </SheetDescription>
                <div class="flex flex-wrap gap-1.5 pt-1">
                    <Badge>{{ comunicacao.tribunal }}</Badge>
                    <Badge
                        v-if="comunicacao.tipoComunicacao"
                        variant="secondary"
                    >
                        {{ comunicacao.tipoComunicacao }}
                    </Badge>
                    <Badge v-if="comunicacao.meio" variant="outline">
                        {{ MEIOS_CURTOS[comunicacao.meio] ?? comunicacao.meio }}
                    </Badge>
                </div>
            </SheetHeader>

            <div class="grid gap-6 px-6 py-5">
                <section aria-label="Processo">
                    <p
                        class="font-mono text-lg font-semibold tracking-tight"
                        data-testid="painel-processo"
                    >
                        {{ comunicacao.numeroProcesso }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            data-testid="copiar-processo"
                            @click="
                                copiar(
                                    comunicacao.numeroProcessoDigitos,
                                    'Número do processo copiado (só dígitos).',
                                )
                            "
                        >
                            <Copy class="size-4" aria-hidden="true" />
                            Copiar nº (só dígitos)
                        </Button>
                        <Button
                            v-if="
                                comunicacao.numeroProcessoDigitos.length === 20
                            "
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <Link
                                :href="
                                    route('consulta.index', {
                                        processo:
                                            comunicacao.numeroProcessoDigitos,
                                    })
                                "
                                data-testid="todas-do-processo"
                                @click="aberto = false"
                            >
                                <Search class="size-4" aria-hidden="true" />
                                Todas deste processo
                            </Link>
                        </Button>
                    </div>
                </section>

                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Disponibilização
                        </dt>
                        <dd>
                            {{ formatarData(comunicacao.dataDisponibilizacao) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Classe
                        </dt>
                        <dd>
                            {{ comunicacao.classe ?? '—' }}
                            <span
                                v-if="comunicacao.codigoClasse"
                                class="text-muted-foreground"
                            >
                                ({{ comunicacao.codigoClasse }})
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Órgão
                        </dt>
                        <dd>{{ comunicacao.orgaoJulgador ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Meio
                        </dt>
                        <dd>
                            {{
                                comunicacao.meioCompleto ??
                                comunicacao.meio ??
                                '—'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Tipo de documento
                        </dt>
                        <dd>{{ comunicacao.tipoDocumento ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted-foreground">
                            Nº da comunicação
                        </dt>
                        <dd>{{ comunicacao.numeroComunicacao ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-muted-foreground">
                            ID / hash
                        </dt>
                        <dd class="font-mono text-xs break-all">
                            <template v-if="comunicacao.id">
                                {{ comunicacao.id }} ·
                            </template>
                            {{ comunicacao.hash }}
                        </dd>
                    </div>
                </dl>

                <div class="grid gap-6 sm:grid-cols-2">
                    <section aria-labelledby="painel-destinatarios">
                        <h3
                            id="painel-destinatarios"
                            class="mb-2 text-xs font-medium text-muted-foreground"
                        >
                            Destinatários
                        </h3>
                        <ul class="grid gap-1.5 text-sm">
                            <li
                                v-for="d in comunicacao.destinatarios"
                                :key="d.nome"
                                class="flex items-start gap-2"
                            >
                                <Badge
                                    v-if="rotuloPolo(d.polo)"
                                    variant="outline"
                                    class="shrink-0"
                                >
                                    {{ rotuloPolo(d.polo) }}
                                </Badge>
                                <span>{{ d.nome }}</span>
                            </li>
                            <li
                                v-if="!comunicacao.destinatarios.length"
                                class="text-muted-foreground"
                            >
                                —
                            </li>
                        </ul>
                    </section>
                    <section aria-labelledby="painel-advogados">
                        <h3
                            id="painel-advogados"
                            class="mb-2 text-xs font-medium text-muted-foreground"
                        >
                            Advogados
                        </h3>
                        <ul class="grid gap-1.5 text-sm">
                            <li
                                v-for="a in comunicacao.advogados"
                                :key="`${a.nome}-${a.numeroOab}`"
                                class="flex flex-wrap items-center gap-2"
                            >
                                <span>{{ a.nome }}</span>
                                <Badge
                                    v-if="a.numeroOab"
                                    variant="secondary"
                                    class="font-mono"
                                >
                                    OAB {{ a.numeroOab
                                    }}<template v-if="a.ufOab"
                                        >/{{ a.ufOab }}</template
                                    >
                                </Badge>
                            </li>
                            <li
                                v-if="!comunicacao.advogados.length"
                                class="text-muted-foreground"
                            >
                                —
                            </li>
                        </ul>
                    </section>
                </div>

                <div>
                    <Button v-if="comunicacao.linkDocumento" as-child size="sm">
                        <a
                            :href="comunicacao.linkDocumento"
                            target="_blank"
                            rel="noopener noreferrer"
                            data-testid="link-documento"
                        >
                            <ExternalLink class="size-4" aria-hidden="true" />
                            Abrir documento no tribunal
                        </a>
                    </Button>
                    <p
                        v-else
                        class="inline-flex items-center gap-1.5 text-sm text-muted-foreground"
                    >
                        <FileX class="size-4" aria-hidden="true" />
                        Sem link do documento no tribunal.
                    </p>
                </div>

                <section aria-labelledby="painel-teor" class="grid gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 id="painel-teor" class="text-sm font-semibold">
                            Teor da comunicação
                        </h3>
                        <div
                            class="ml-auto inline-flex rounded-lg border p-0.5"
                            role="tablist"
                            aria-label="Forma de exibir o teor"
                        >
                            <button
                                v-for="v in visoes"
                                :key="v.valor"
                                type="button"
                                role="tab"
                                class="rounded-md px-2.5 py-1 text-xs"
                                :class="
                                    visao === v.valor
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-accent'
                                "
                                :aria-selected="visao === v.valor"
                                :data-testid="`visao-${v.valor}`"
                                @click="visao = v.valor"
                            >
                                {{ v.rotulo }}
                            </button>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            data-testid="copiar-teor"
                            @click="copiarTeor"
                        >
                            <Copy class="size-4" aria-hidden="true" />
                            Copiar
                        </Button>
                    </div>

                    <!-- textoHtml é sanitizado no servidor (App\Support\HtmlSanitizer). -->
                    <div
                        v-if="visao === 'formatado'"
                        class="space-y-3 rounded-lg border bg-muted/30 p-4 text-sm leading-relaxed [&_table]:w-full [&_td]:border [&_td]:p-1 [&_th]:border [&_th]:p-1"
                        data-testid="inteiro-teor"
                        v-html="comunicacao.textoHtml"
                    />
                    <pre
                        v-else-if="visao === 'texto'"
                        class="rounded-lg border bg-muted/30 p-4 text-sm leading-relaxed whitespace-pre-wrap"
                        data-testid="teor-texto"
                        >{{ textoPuro }}</pre>
                    <pre
                        v-else
                        class="overflow-x-auto rounded-lg border bg-muted/30 p-4 font-mono text-xs leading-relaxed"
                        data-testid="teor-dados"
                        >{{ dados }}</pre>
                </section>
            </div>
        </SheetContent>
    </Sheet>
</template>
