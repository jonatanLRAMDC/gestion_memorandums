<x-layouts.impresion :titulo="$titulo" :contexto="[$filtros, $registros->count().' registro(s)'.($registros->count() >= $maximo ? ' — se muestran los primeros '.$maximo.'; afine los filtros' : '')]">
    <table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Tipo</th>
                <th>De / Para</th>
                <th>Asunto</th>
                <th>Fecha memo</th>
                <th>Envío / recepción</th>
                <th>Registrado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($registros as $registro)
                @php($memo = $registro->memorando)
                <tr>
                    <td class="mono">{{ $memo->numero }}</td>
                    <td>{{ $registro->tipo->etiqueta() }}</td>
                    <td>
                        @if ($registro->tipo === $emitido)
                            <span class="gris">Para:</span> {{ $memo->destinos->map(fn ($destino) => $destino->gerencia->siglas ?? $destino->gerencia->nombre)->join(', ') }}
                        @else
                            <span class="gris">De:</span> {{ $memo->gerenciaEmisora->siglas ?? $memo->gerenciaEmisora->nombre }}
                        @endif
                    </td>
                    <td>{{ $memo->asunto }}</td>
                    <td class="num">{{ $memo->fechaMemoTexto() }}</td>
                    <td class="num">{{ $registro->fecha_movimiento->format('d/m/Y') }}</td>
                    <td class="num">{{ $registro->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="gris">No hay memorandos con estos filtros.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-layouts.impresion>
