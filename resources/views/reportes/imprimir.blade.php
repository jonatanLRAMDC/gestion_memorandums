<x-layouts.impresion :titulo="$titulo" :contexto="[$periodo, 'Emitidos: '.$datos['totales']['emitidos'].' · Recibidos: '.$datos['totales']['recibidos'].' · Total: '.$datos['totales']['total']]">
    <h2>Por mes</h2>
    <table>
        <thead><tr><th>Mes</th><th class="der">Emitidos</th><th class="der">Recibidos</th><th class="der">Total</th></tr></thead>
        <tbody>
            @foreach ($datos['meses'] as $fila)
                <tr><td>{{ $fila['mes'] }}</td><td class="der num">{{ $fila['emitidos'] }}</td><td class="der num">{{ $fila['recibidos'] }}</td><td class="der num">{{ $fila['total'] }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td>Total</td><td class="der num">{{ $datos['totales']['emitidos'] }}</td><td class="der num">{{ $datos['totales']['recibidos'] }}</td><td class="der num">{{ $datos['totales']['total'] }}</td></tr></tfoot>
    </table>

    <h2>Por gerencia</h2>
    <p class="gris">Emitidos: memos enviados a esa gerencia (para o copia). Recibidos: memos que esa gerencia envió.</p>
    <table>
        <thead><tr><th>Gerencia</th><th>Siglas</th><th class="der">Emitidos</th><th class="der">Recibidos</th><th class="der">Total</th></tr></thead>
        <tbody>
            @forelse ($datos['gerencias'] as $fila)
                <tr><td>{{ $fila['gerencia'] }}</td><td>{{ $fila['siglas'] }}</td><td class="der num">{{ $fila['emitidos'] }}</td><td class="der num">{{ $fila['recibidos'] }}</td><td class="der num">{{ $fila['total'] }}</td></tr>
            @empty
                <tr><td colspan="5" class="gris">No hay memorandos en el período.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-layouts.impresion>
