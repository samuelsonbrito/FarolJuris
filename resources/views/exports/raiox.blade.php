{{-- PDF do Raio-X (renderizado pelo dompdf; não é tela). Todo conteúdo é escapado. --}}
@php
    $pct = fn ($t) => $t === null ? '—' : round($t * 100).'%';
    $brl = fn ($v) => $v === null ? '—' : 'R$ '.number_format($v, 0, ',', '.');
    $vg = $m['visaoGeral'];
    $cores = ['exito' => '#1f3f66', 'parcial' => '#8aa6c4', 'acordo' => '#c4c3bd', 'insucesso' => '#c9b27f'];
    $periodo = $r->inicio->format('d/m/Y').' a '.$r->fim->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Raio-X · OAB {{ $r->oab }}/{{ $r->uf }}</title>
    <style>
        @page { margin: 30px 34px 44px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1f2937; }
        .eyebrow { color: #b8862f; font-size: 8px; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; }
        h1 { font-size: 20px; color: #0f2a44; margin: 4px 0 2px; }
        h2 { font-size: 13px; color: #0f2a44; margin: 18px 0 6px; }
        h3 { font-size: 10px; margin: 0 0 4px; }
        .sub { color: #6b7280; }
        hr { border: 0; border-top: 1.5px solid #d4b26b; margin: 10px 0 4px; }
        table.cards { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-left: -5px; }
        table.cards td { border: 1px solid #e5e7eb; border-radius: 6px; padding: 7px 8px; vertical-align: top; width: 16.6%; }
        table.cards td.destaque { background: #0f2a44; color: #fff; border-color: #0f2a44; }
        .num { font-size: 18px; font-weight: bold; }
        .peq { font-size: 7.5px; color: #6b7280; }
        td.destaque .peq { color: #cbd5e1; }
        table.t { width: 100%; border-collapse: collapse; }
        table.t th { text-align: left; font-size: 7.5px; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #d1d5db; padding: 4px 5px; }
        table.t td { border-bottom: 1px solid #eef0f2; padding: 3.5px 5px; }
        table.t .r { text-align: right; }
        .barra { width: 100%; height: 11px; background: #f3f4f6; }
        .barra div { height: 11px; float: left; }
        .duas { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-left: -8px; }
        .duas > tbody > tr > td { width: 50%; vertical-align: top; }
        .caixa { border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; }
        .custo { background: #0f2a44; color: #fff; border-radius: 6px; padding: 10px; }
        .nota { color: #6b7280; font-size: 8px; line-height: 1.4; }
        .rodape { position: fixed; bottom: -28px; left: 0; right: 0; text-align: center; color: #9ca3af; font-size: 7.5px; }
        .quebra { page-break-before: always; }
    </style>
</head>
<body>
    <div class="rodape">
        FarolJuris · Raio-X da OAB {{ $r->oab }}/{{ $r->uf }} · dados públicos do DJEN (CNJ) · gerado em {{ $geradoEm->format('d/m/Y H:i') }} · uso interno do escritório
    </div>

    <div class="eyebrow">Relatório de desempenho processual · {{ $periodo }}</div>
    <h1>{{ $r->nome_advogado ?? 'OAB '.$r->oab.'/'.$r->uf }}</h1>
    <div class="sub">OAB {{ $r->oab }}/{{ $r->uf }} · Decisões publicadas no DJEN de {{ $periodo }} · Fonte: FarolJuris</div>
    <hr>

    <h2>Visão geral</h2>
    <table class="cards"><tr>
        <td class="destaque"><div class="peq">Taxa de êxito*</div><div class="num">{{ $pct($vg['taxa']) }}</div><div class="peq">{{ $vg['favoraveis'] }} de {{ $vg['decididos'] }} decididos</div></td>
        <td><div class="peq">Processos decididos</div><div class="num">{{ $vg['decididos'] }}</div><div class="peq">{{ $vg['pendentes'] > 0 ? '+ '.$vg['pendentes'].' em conferência' : $vg['ativos'].' ativos' }}</div></td>
        <td><div class="peq">Êxito</div><div class="num">{{ $vg['exito'] }}</div><div class="peq">procedente / provido</div></td>
        <td><div class="peq">Êxito parcial</div><div class="num">{{ $vg['parcial'] }}</div><div class="peq">parcialmente procedente</div></td>
        <td><div class="peq">Acordos</div><div class="num">{{ $vg['acordo'] }}</div><div class="peq">homologados</div></td>
        <td><div class="peq">Insucesso</div><div class="num">{{ $vg['insucesso'] }}</div><div class="peq">inclui {{ $vg['extincoes'] }} extinções sem mérito</div></td>
    </tr></table>
    <p class="nota">* Taxa de êxito = (êxito + êxito parcial) ÷ processos decididos. Cada processo conta uma vez, pela decisão mais recente do período, lida do ponto de vista do cliente.</p>

    <h2>Onde está a diferença: 1º grau × recursos</h2>
    <table class="t">
        @foreach ($m['porInstancia'] as $l)
            <tr>
                <td style="width: 150px"><strong>{{ $l['rotulo'] }}</strong><br><span class="peq">{{ $l['decididos'] }} decididos</span></td>
                <td>
                    <div class="barra">
                        @foreach (['exito', 'parcial', 'acordo', 'insucesso'] as $k)
                            @if ($l['decididos'] > 0 && $l[$k] > 0)
                                <div style="width: {{ round($l[$k] / $l['decididos'] * 100, 2) }}%; background: {{ $cores[$k] }}"></div>
                            @endif
                        @endforeach
                    </div>
                </td>
                <td class="r" style="width: 40px"><strong>{{ $pct($l['taxa']) }}</strong></td>
            </tr>
        @endforeach
    </table>
    <p class="nota">Êxito (azul escuro) · êxito parcial (azul claro) · acordo (cinza) · insucesso (areia).</p>

    @php
        $tabela = function (array $linhas, string $coluna, bool $completa = false) use ($pct) {
            return view('exports.raiox-tabela', compact('linhas', 'coluna', 'completa', 'pct'))->render();
        };
    @endphp

    <h2>Por vara e por colegiado</h2>
    <table class="duas"><tr>
        <td><h3>Por vara de origem (resultado final)</h3>{!! $tabela($m['porOrigem'], 'Órgão') !!}</td>
        <td><h3>Recursos – quem julgou</h3>{!! $tabela($m['porColegiado'], 'Órgão') !!}</td>
    </tr></table>

    @if (count($m['varasCapital']['civeis']) || count($m['varasCapital']['juizados']))
        <h2>Vara a vara em {{ $capital ?? 'na capital' }}</h2>
        <table class="duas"><tr>
            <td><h3>Varas cíveis</h3>{!! $tabela($m['varasCapital']['civeis'], 'Vara') !!}</td>
            <td><h3>Juizados Especiais Cíveis</h3>{!! $tabela($m['varasCapital']['juizados'], 'Vara') !!}</td>
        </tr></table>
        <p class="nota">Com 1 a 3 casos por vara, são sinais para observar, não conclusões.</p>
    @endif

    <h2>Por tema</h2>
    {!! $tabela($m['porTema'], 'Tema', true) !!}

    <h2>Por parte contrária</h2>
    {!! $tabela($m['porParte'], 'Parte contrária', true) !!}

    <h2>Quanto cada êxito precisa pagar</h2>
    @php($c = $m['custo'])
    <table class="duas"><tr>
        <td style="width: 35%">
            <div class="custo">
                <div class="peq" style="color:#cbd5e1">Processos ativos para cada êxito</div>
                <div class="num" style="font-size: 24px">{{ $c['processosPorExito'] !== null ? number_format($c['processosPorExito'], 1, ',', '.') : '—' }}</div>
                <div class="peq" style="color:#cbd5e1">{{ $c['ativos'] }} processos ativos ÷ {{ $c['exitos'] }} êxitos</div>
                <div class="peq" style="color:#cbd5e1; margin-top: 8px">Honorário mínimo por êxito</div>
                <div class="num">{{ $brl($c['honorarioMinimo']) }}</div>
            </div>
        </td>
        <td>
            <div class="caixa">
                @if ($c['custoMensal'] !== null)
                    <p>Com custo fixo de <strong>{{ $brl($c['custoMensal']) }} por mês</strong> ({{ $brl($c['custoTotal']) }} no ano), cada processo ativo custa <strong>{{ $brl($c['custoPorProcesso']) }}</strong>, e cada êxito precisa render pelo menos <strong>{{ $brl($c['honorarioMinimo']) }}</strong> em honorários só para o escritório empatar.</p>
                    @if ($c['margem'] !== null)
                        <p>Com honorário médio de {{ $brl($c['honorarioMedio']) }}, a margem por êxito é de <strong>{{ $brl($c['margem']) }}</strong>.</p>
                    @endif
                @else
                    <p>O custo do escritório ainda não foi informado. Preencha a calculadora no FarolJuris para ver o honorário mínimo por êxito.</p>
                @endif
                <p class="nota">Não entram custas, diligências e correspondentes, que aumentam esse piso. "Ativos" são os processos com alguma publicação no DJEN no período.</p>
            </div>
        </td>
    </tr></table>

    <table class="duas" style="margin-top: 12px"><tr>
        <td><div class="caixa"><h3 style="color:#1f3f66">Onde vai melhor</h3>
            @forelse ($m['destaques']['melhor'] as $t) <div>• {{ $t }}</div> @empty <div class="nota">Nenhum grupo com casos suficientes acima de 40%.</div> @endforelse
        </div></td>
        <td><div class="caixa"><h3 style="color:#9a7b3b">Onde vai pior</h3>
            @forelse ($m['destaques']['pior'] as $t) <div>• {{ $t }}</div> @empty <div class="nota">Nenhum grupo com casos suficientes abaixo de 40%.</div> @endforelse
        </div></td>
    </tr></table>

    <h2>Como ler estes números</h2>
    <p class="nota">
        Base: {{ $vg['publicacoes'] }} publicações em {{ $vg['ativos'] }} processos (OAB {{ $r->oab }}/{{ $r->uf }}).
        Entraram {{ $vg['decididos'] }} processos com decisão de mérito, acordo ou extinção; {{ $vg['semDecisao'] }} tiveram só publicações sem resultado no período.
        @if ($vg['pendentes'] > 0)
            {{ $vg['pendentes'] }} casos estão marcados para conferência nos autos ({{ implode(', ', $m['conferencia']) }}) e ficam fora das taxas.
        @endif
        Fonte: publicações oficiais e públicas do DJEN. Decisões ainda não publicadas, acordos extrajudiciais e valores recebidos não entram na análise.
        O Raio-X descreve o passado e orienta decisões; não garante êxito em processos futuros. As métricas são ferramenta de gestão interna e não devem ser usadas em anúncios (Provimento 205/2021 da OAB).
    </p>
</body>
</html>
