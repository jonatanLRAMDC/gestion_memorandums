@php
    $gerenciaId = auth()->user()->gerencia_id;
    $registros = \App\Models\Registro::query()->where('gerencia_id', $gerenciaId);
    $inicioMes = now()->startOfMonth();

    $indicadores = [
        ['etiqueta' => 'Memorandos registrados', 'valor' => (clone $registros)->count(), 'nota' => 'Desde el inicio del sistema', 'icono' => 'document-text'],
        ['etiqueta' => 'Emitidos este mes', 'valor' => (clone $registros)->where('tipo', 'emitido')->where('fecha_movimiento', '>=', $inicioMes)->count(), 'nota' => ucfirst(now()->translatedFormat('F Y')), 'icono' => 'paper-airplane'],
        ['etiqueta' => 'Recibidos este mes', 'valor' => (clone $registros)->where('tipo', 'recibido')->where('fecha_movimiento', '>=', $inicioMes)->count(), 'nota' => ucfirst(now()->translatedFormat('F Y')), 'icono' => 'inbox-arrow-down'],
        ['etiqueta' => 'Registrados hoy', 'valor' => (clone $registros)->whereDate('created_at', today())->count(), 'nota' => now()->format('d/m/Y'), 'icono' => 'calendar-days'],
    ];

    $recientes = (clone $registros)
        ->with(['memorando.gerenciaEmisora:id,siglas,nombre', 'memorando.destinos.gerencia:id,siglas,nombre'])
        ->latest('id')
        ->limit(6)
        ->get();
@endphp

<x-layouts.app title="Panel">
    <div class="flex w-full flex-1 flex-col gap-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Panel principal</flux:heading>
                <flux:subheading>
                    {{ auth()->user()->gerencia?->nombre ?? 'Sin gerencia asignada' }} · {{ auth()->user()->rol?->nombre }} · {{ now()->format('d/m/Y') }}
                </flux:subheading>
            </div>

            @can('memorandos.registrar')
                <div class="flex gap-2">
                    <flux:button icon="paper-airplane" :href="route('memorandos.registrar', 'emitido')" wire:navigate>Registrar emitido</flux:button>
                    <flux:button variant="primary" icon="inbox-arrow-down" :href="route('memorandos.registrar', 'recibido')" wire:navigate>Registrar recibido</flux:button>
                </div>
            @endcan
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($indicadores as $indicador)
                <div class="flex items-start gap-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-amdc-50 text-amdc-700 dark:bg-amdc-900 dark:text-amdc-200">
                        <flux:icon :name="$indicador['icono']" class="size-5" />
                    </span>
                    <div>
                        <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ $indicador['etiqueta'] }}</p>
                        <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $indicador['valor'] }}</p>
                        <p class="text-xs text-zinc-500">{{ $indicador['nota'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        @can('memorandos.consultar')
            <section class="rounded-xl border border-zinc-200 bg-white px-4 pt-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between px-1">
                    <flux:heading>Memorandos recientes</flux:heading>
                    <flux:link :href="route('memorandos.index')" wire:navigate class="text-sm">Ver todos</flux:link>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Número</flux:table.column>
                        <flux:table.column>Tipo</flux:table.column>
                        <flux:table.column>De / Para</flux:table.column>
                        <flux:table.column>Asunto</flux:table.column>
                        <flux:table.column>Fecha</flux:table.column>
                        <flux:table.column align="end"></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($recientes as $registro)
                            @php($memo = $registro->memorando)
                            @php($emitido = $registro->tipo === \App\Enums\TipoRegistro::Emitido)
                            <flux:table.row :key="$registro->id">
                                <flux:table.cell class="font-mono text-sm font-medium">{{ $memo->numero }}</flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" inset="top bottom" :color="$emitido ? 'blue' : 'amber'">{{ $registro->tipo->etiqueta() }}</flux:badge></flux:table.cell>
                                <flux:table.cell class="text-sm">
                                    {{ $emitido ? 'Para: '.$memo->destinos->map(fn ($destino) => $destino->gerencia->siglas ?? $destino->gerencia->nombre)->join(', ') : 'De: '.($memo->gerenciaEmisora->siglas ?? $memo->gerenciaEmisora->nombre) }}
                                </flux:table.cell>
                                <flux:table.cell class="max-w-72 truncate">{{ $memo->asunto }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $registro->fecha_movimiento->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell align="end"><flux:link :href="route('memorandos.ver', $memo)" wire:navigate class="text-sm">Ver detalle</flux:link></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">Todavía no hay memorandos registrados.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </section>
        @endcan
    </div>
</x-layouts.app>
