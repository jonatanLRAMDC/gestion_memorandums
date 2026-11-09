<?php

use App\Support\ReporteMemorandos;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('Reportes')] class extends Component {
    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->gerencia_id, 403, 'Su usuario no tiene una gerencia asignada. Contacte al administrador.');

        $this->desde = $this->desde ?: now()->startOfYear()->toDateString();
        $this->hasta = $this->hasta ?: now()->toDateString();
    }

    /**
     * Atajos de período.
     */
    public function periodo(string $atajo): void
    {
        [$desde, $hasta] = match ($atajo) {
            'mes' => [now()->startOfMonth(), now()],
            'mes_anterior' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'anio_anterior' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            default => [now()->startOfYear(), now()],
        };

        $this->desde = $desde->toDateString();
        $this->hasta = $hasta->toDateString();
    }

    #[Computed]
    public function reporte(): ReporteMemorandos
    {
        return ReporteMemorandos::desdeFiltros(auth()->user()->gerencia_id, $this->desde, $this->hasta);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function datos(): array
    {
        return $this->reporte->datos();
    }
}; ?>

@php($datos = $this->datos)
@php($maximoMes = max(1, ...array_column($datos['meses'], 'emitidos'), ...array_column($datos['meses'], 'recibidos')))
@php($maximoGerencia = max(1, ...array_column($datos['gerencias'], 'emitidos'), ...array_column($datos['gerencias'], 'recibidos')))
@php($filtros = ['desde' => $this->reporte->desde->toDateString(), 'hasta' => $this->reporte->hasta->toDateString()])

<div class="flex flex-col gap-6" style="--serie-emitidos: #2f63c4; --serie-recibidos: #d97706;">
    <style>
        .dark [data-reporte] { --serie-emitidos: #5b87db; --serie-recibidos: #c97a0a; }
    </style>

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Reportes</flux:heading>
            <flux:subheading>Memorandos emitidos y recibidos por {{ auth()->user()->gerencia?->nombre }}. {{ $this->reporte->descripcionPeriodo() }}.</flux:subheading>
        </div>

        <flux:dropdown position="bottom" align="end">
            <flux:button icon="arrow-down-tray" icon:trailing="chevron-down">Exportar</flux:button>
            <flux:menu>
                <flux:menu.item icon="table-cells" :href="route('reportes.exportar.excel', $filtros)">Excel (.xlsx)</flux:menu.item>
                <flux:menu.item icon="printer" :href="route('reportes.exportar.imprimir', $filtros)" target="_blank">PDF o impresión</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <flux:input wire:model.live="desde" type="date" label="Desde" class="max-w-44" />
        <flux:input wire:model.live="hasta" type="date" label="Hasta" class="max-w-44" />
        <div class="flex flex-wrap gap-1">
            <flux:button size="sm" variant="ghost" wire:click="periodo('mes')">Este mes</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="periodo('mes_anterior')">Mes anterior</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="periodo('anio')">Este año</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="periodo('anio_anterior')">Año anterior</flux:button>
        </div>
    </div>

    <div data-reporte class="flex flex-col gap-6">
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach (['total' => 'Total', 'emitidos' => 'Emitidos', 'recibidos' => 'Recibidos'] as $clave => $etiqueta)
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        @if ($clave !== 'total')
                            <span class="size-2.5 rounded-sm" style="background: var(--serie-{{ $clave }})"></span>
                        @endif
                        {{ $etiqueta }}
                    </div>
                    <div class="mt-1 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white" data-total="{{ $clave }}">{{ number_format($datos['totales'][$clave]) }}</div>
                </div>
            @endforeach
        </div>

        @php($leyenda = '<div class="flex items-center gap-4 text-xs text-zinc-500"><span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background: var(--serie-emitidos)"></span>Emitidos</span><span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background: var(--serie-recibidos)"></span>Recibidos</span></div>')

        <div class="grid gap-6 xl:grid-cols-2">
            @foreach ([
                ['titulo' => 'Por mes', 'columna' => 'Mes', 'filas' => $datos['meses'], 'clave' => 'mes', 'maximo' => $maximoMes, 'vacio' => 'Sin meses en el período.'],
                ['titulo' => 'Por gerencia', 'columna' => 'Gerencia', 'filas' => $datos['gerencias'], 'clave' => 'gerencia', 'maximo' => $maximoGerencia, 'vacio' => 'No hay memorandos en el período.'],
            ] as $tabla)
                <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:heading>{{ $tabla['titulo'] }}</flux:heading>
                        {!! $leyenda !!}
                    </div>
                    @if ($tabla['clave'] === 'gerencia')
                        <p class="mt-1 text-xs text-zinc-500">Emitidos: memos enviados a esa gerencia (para o copia). Recibidos: memos que esa gerencia envió.</p>
                    @endif
                    <table class="mt-4 w-full text-sm">
                        <thead class="text-zinc-500">
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="py-2 text-start font-medium">{{ $tabla['columna'] }}</th>
                                <th class="w-2/5 py-2 text-start font-medium"><span class="sr-only">Gráfico</span></th>
                                <th class="py-2 text-end font-medium">Emit.</th>
                                <th class="py-2 text-end font-medium">Recib.</th>
                                <th class="py-2 text-end font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tabla['filas'] as $fila)
                                <tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800">
                                    <td class="py-2 pe-3" title="{{ $fila[$tabla['clave']] }}">
                                        {{ $tabla['clave'] === 'gerencia' && $fila['siglas'] ? $fila['siglas'] : $fila[$tabla['clave']] }}
                                    </td>
                                    <td class="py-2 pe-3" aria-hidden="true">
                                        <div class="flex flex-col gap-0.5">
                                            @foreach (['emitidos', 'recibidos'] as $serie)
                                                <div class="h-2 rounded-e-sm" style="width: {{ round($fila[$serie] / $tabla['maximo'] * 100, 1) }}%; min-width: {{ $fila[$serie] ? '2px' : '0' }}; background: var(--serie-{{ $serie }})" title="{{ ucfirst($serie) }}: {{ $fila[$serie] }}"></div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-2 text-end tabular-nums">{{ $fila['emitidos'] }}</td>
                                    <td class="py-2 text-end tabular-nums">{{ $fila['recibidos'] }}</td>
                                    <td class="py-2 text-end font-medium tabular-nums">{{ $fila['total'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-zinc-500">{{ $tabla['vacio'] }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            @endforeach
        </div>
    </div>
</div>
