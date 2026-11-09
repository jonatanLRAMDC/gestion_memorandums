<?php

use App\Models\Gerencia;
use App\Models\Registro;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Memorandos')] class extends Component {
    use WithPagination;

    #[Url(as: 'tipo', except: '')]
    public string $tipo = '';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'gerencia', except: '')]
    public string $gerenciaId = '';

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    #[Url(except: 'registrado')]
    public string $orden = 'registrado';

    #[Url(as: 'dir', except: 'desc')]
    public string $direccion = 'desc';

    public function updated(string $propiedad): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset('buscar', 'gerenciaId', 'desde', 'hasta');
        $this->resetPage();
    }

    /**
     * Ordena por la columna elegida; un segundo clic invierte el sentido.
     */
    public function ordenarPor(string $columna): void
    {
        if (! array_key_exists($columna, Registro::ORDENES)) {
            return;
        }

        if ($this->orden === $columna) {
            $this->direccion = $this->direccion === 'desc' ? 'asc' : 'desc';
        } else {
            $this->orden = $columna;
            $this->direccion = $columna === 'numero' ? 'asc' : 'desc';
        }

        $this->resetPage();
    }

    /**
     * Filtros activos, en la forma que usan las exportaciones.
     *
     * @return array<string, string>
     */
    public function filtrosActuales(): array
    {
        return array_filter([
            'tipo' => $this->tipo,
            'q' => $this->buscar,
            'gerencia' => $this->gerenciaId,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'orden' => $this->orden,
            'dir' => $this->direccion,
        ], fn (string $valor) => $valor !== '');
    }

    /**
     * @return Collection<int, Gerencia>
     */
    #[Computed]
    public function gerencias(): Collection
    {
        return Gerencia::query()
            ->whereKeyNot(auth()->user()->gerencia_id)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'siglas']);
    }

    /**
     * Registros de la gerencia del usuario con su memorando.
     *
     * @return LengthAwarePaginator<int, Registro>
     */
    #[Computed]
    public function registros(): LengthAwarePaginator
    {
        return Registro::query()
            ->deGerencia(auth()->user()->gerencia_id)
            ->with(['memorando.gerenciaEmisora:id,nombre,siglas', 'memorando.destinos.gerencia:id,nombre,siglas', 'memorando.archivoVigente:id,memorando_id'])
            ->filtrar([
                'tipo' => $this->tipo,
                'buscar' => $this->buscar,
                'gerencia' => $this->gerenciaId,
                'desde' => $this->desde,
                'hasta' => $this->hasta,
            ])
            ->ordenar($this->orden, $this->direccion)
            ->paginate(15);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Memorandos</flux:heading>
            <flux:subheading>Memorandos emitidos y recibidos por {{ auth()->user()->gerencia?->nombre }}.</flux:subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('memorandos.exportar')
                <flux:dropdown position="bottom" align="end">
                    <flux:button icon="arrow-down-tray" icon:trailing="chevron-down">Exportar</flux:button>
                    <flux:menu>
                        <flux:menu.item icon="table-cells" :href="route('memorandos.exportar.excel', $this->filtrosActuales())">Excel (.xlsx)</flux:menu.item>
                        <flux:menu.item icon="printer" :href="route('memorandos.exportar.imprimir', $this->filtrosActuales())" target="_blank">PDF o impresión</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            @endcan
            @can('memorandos.registrar')
                <flux:button icon="paper-airplane" :href="route('memorandos.registrar', 'emitido')" wire:navigate>Registrar emitido</flux:button>
                <flux:button variant="primary" icon="inbox-arrow-down" :href="route('memorandos.registrar', 'recibido')" wire:navigate>Registrar recibido</flux:button>
            @endcan
        </div>
    </div>

    <div class="inline-flex w-fit rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900">
        @foreach (['' => 'Todos', 'emitido' => 'Emitidos', 'recibido' => 'Recibidos'] as $valor => $etiqueta)
            <button type="button" wire:click="$set('tipo', '{{ $valor }}')"
                @class(['rounded-md px-4 py-1.5 text-sm font-medium', 'bg-amdc-700 text-white' => $tipo === $valor, 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-300' => $tipo !== $valor])>{{ $etiqueta }}</button>
        @endforeach
    </div>

    <div class="grid gap-3 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1.2fr)_160px_160px_auto] md:items-end">
        <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" label="Buscar" placeholder="Número, asunto o firmante…" clearable />
        <flux:select wire:model.live="gerenciaId" label="Gerencia (de o para)">
            <flux:select.option value="">Todas las gerencias</flux:select.option>
            @foreach ($this->gerencias as $gerencia)
                <flux:select.option :value="$gerencia->id">{{ $gerencia->siglas ? $gerencia->siglas.' — ' : '' }}{{ $gerencia->nombre }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live="desde" type="date" label="Fecha del memo desde" />
        <flux:input wire:model.live="hasta" type="date" label="Hasta" />
        <flux:button variant="ghost" icon="x-mark" wire:click="limpiarFiltros">Limpiar</flux:button>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white px-4 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$this->registros">
            <flux:table.columns>
                @foreach (['numero' => 'Número', 'tipo' => 'Tipo', 'contraparte' => 'De / Para', 'asunto' => 'Asunto', 'fecha_memo' => 'Fecha memo', 'movimiento' => 'Envío / recepción', 'registrado' => 'Registrado'] as $columna => $titulo)
                    @if (array_key_exists($columna, \App\Models\Registro::ORDENES))
                        <flux:table.column sortable :sorted="$orden === $columna" :direction="$direccion" wire:click="ordenarPor('{{ $columna }}')" data-orden="{{ $columna }}">{{ $titulo }}</flux:table.column>
                    @else
                        <flux:table.column>{{ $titulo }}</flux:table.column>
                    @endif
                @endforeach
                <flux:table.column align="end">Acciones</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->registros as $registro)
                    @php($memo = $registro->memorando)
                    @php($emitido = $registro->tipo === \App\Enums\TipoRegistro::Emitido)
                    <flux:table.row :key="$registro->id">
                        <flux:table.cell class="font-mono text-sm font-medium text-zinc-800 dark:text-white">{{ $memo->numero }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" inset="top bottom" :color="$emitido ? 'blue' : 'amber'">{{ $registro->tipo->etiqueta() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">
                            @if ($emitido)
                                <span class="text-zinc-500">Para:</span> {{ $memo->destinos->map(fn ($destino) => $destino->gerencia->siglas ?? $destino->gerencia->nombre)->join(', ') }}
                            @else
                                <span class="text-zinc-500">De:</span> {{ $memo->gerenciaEmisora->siglas ?? $memo->gerenciaEmisora->nombre }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="max-w-72 truncate" title="{{ $memo->asunto }}">{{ $memo->asunto }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ $memo->fechaMemoTexto() }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ $registro->fecha_movimiento->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell class="text-sm tabular-nums text-zinc-500" title="Registrado el {{ $registro->created_at->format('d/m/Y H:i') }}">
                            {{ $registro->created_at->format('d/m/Y') }}
                            <span class="block text-xs">{{ $registro->created_at->format('H:i') }}</span>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="eye" :href="route('memorandos.ver', $memo)" wire:navigate>Ver</flux:button>
                            @if ($memo->archivoVigente)
                                <flux:button size="sm" variant="ghost" icon="document-text" :href="route('memorandos.pdf', $memo)" target="_blank" aria-label="Abrir PDF">PDF</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500">No hay memorandos con estos filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
