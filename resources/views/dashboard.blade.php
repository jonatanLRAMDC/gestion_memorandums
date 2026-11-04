@php
    $indicadores = [
        ['etiqueta' => 'Memorandos registrados', 'valor' => \App\Models\Memorando::count(), 'nota' => 'Desde el inicio del sistema', 'icono' => 'document-text'],
        ['etiqueta' => 'Gerencias activas', 'valor' => \App\Models\Gerencia::activas()->count(), 'nota' => 'En el catálogo', 'icono' => 'building-office-2'],
        ['etiqueta' => 'Usuarios activos', 'valor' => \App\Models\User::where('activo', true)->count(), 'nota' => 'Con acceso al sistema', 'icono' => 'users'],
    ];
@endphp

<x-layouts.app title="Panel">
    <div class="flex w-full flex-1 flex-col gap-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Panel principal</flux:heading>
                <flux:subheading>
                    {{ auth()->user()->gerencia?->nombre ?? 'Sin gerencia asignada' }} · {{ auth()->user()->rol->etiqueta() }} · {{ now()->format('d/m/Y') }}
                </flux:subheading>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
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

        <flux:callout icon="information-circle" color="blue">
            <flux:callout.heading>Fase 1 lista: acceso, catálogos y bitácora</flux:callout.heading>
            <flux:callout.text>El registro y la consulta de memorandos emitidos y recibidos se habilitan en la Fase 2.</flux:callout.text>
        </flux:callout>
    </div>
</x-layouts.app>
