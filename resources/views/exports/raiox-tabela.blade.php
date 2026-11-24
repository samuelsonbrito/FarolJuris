{{-- Tabela de grupos do PDF do Raio-X. --}}
<table class="t">
    <tr>
        <th>{{ $coluna }}</th>
        <th class="r">Decididos</th>
        @if ($completa)
            <th class="r">Êxito</th><th class="r">Parcial</th><th class="r">Acordo</th><th class="r">Insucesso</th>
        @else
            <th class="r">Êxito + parcial</th>
        @endif
        <th class="r">Taxa</th>
    </tr>
    @forelse ($linhas as $l)
        <tr>
            <td>{{ $l['rotulo'] }}</td>
            <td class="r">{{ $l['decididos'] }}</td>
            @if ($completa)
                <td class="r">{{ $l['exito'] }}</td><td class="r">{{ $l['parcial'] }}</td><td class="r">{{ $l['acordo'] }}</td><td class="r">{{ $l['insucesso'] }}</td>
            @else
                <td class="r">{{ $l['favoraveis'] }}</td>
            @endif
            <td class="r"><strong>{{ $pct($l['taxa']) }}</strong></td>
        </tr>
    @empty
        <tr><td colspan="{{ $completa ? 7 : 4 }}" class="peq">Sem casos decididos.</td></tr>
    @endforelse
</table>
