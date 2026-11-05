<?php

use App\Models\Gerencia;
use App\Models\Registro;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
     * Devuelve la fecha aaaa-mm-dd si es válida; si no, null para ignorar el filtro.
     */
    protected function fechaValida(string $fecha): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && strtotime($fecha) ? $fecha : null;
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
            ->where('gerencia_id', auth()->user()->gerencia_id)
            ->with(['memorando.gerenciaEmisora:id,nombre,siglas', 'memorando.destinos.gerencia:id,nombre,siglas', 'memorando.archivoVigente:id,memorando_id'])
            ->when($this->tipo !== '', fn (Builder $query) => $query->where('tipo', $this->tipo))
            ->when($this->buscar !== '', fn (Builder $query) => $query->whereHas('memorando', fn (Builder $memo) => $memo
                ->where('numero', 'like', '%'.trim($this->buscar).'%')
                ->orWhere('asunto', 'like', '%'.trim($this->buscar).'%')
                ->orWhere('remitente_nombre', 'like', '%'.trim($this->buscar).'%')))
            ->when($this->gerenciaId !== '', fn (Builder $query) => $query->whereHas('memorando', fn (Builder $memo) => $memo
                ->where('gerencia_emisora_id', $this->gerenciaId)
                ->orWhereHas('destinos', fn (Builder $destino) => $destino->where('gerencia_id', $this->gerenciaId))))
            ->when($this->fechaValida($this->desde), fn (Builder $query, string $desde) => $query->whereHas('memorando', fn (Builder $memo) => $memo->whereDate('fecha_memo', '>=', $desde)))
            ->when($this->fechaValida($this->hasta), fn (Builder $query, string $hasta) => $query->whereHas('memorando', fn (Builder $memo) => $memo->whereDate('fecha_memo', '<=', $hasta)))
            ->latest('fecha_movimiento')
            ->latest('id')
            ->paginate(15);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Memorandos</flux:heading>
            <flux:subheading>Memorandos emitidos y recibidos por {{ auth()->user()->gerencia?->nombre }}.</flux:subheading>
        </div>

        @can('memorandos.registrar')
            <div class="flex gap-2">
                <flux:button icon="paper-airplane" :href="route('memorandos.registrar', 'emitido')" wire:navigate>Registrar emitido</flux:button>
                <flux:button variant="primary" icon="inbox-arrow-down" :href="route('memorandos.registrar', 'recibido')" wire:navigate>Registrar recibido</flux:button>
            </div>
        @endcan
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
                <flux:table.column>Número</flux:table.column>
                <flux:table.column>Tipo</flux:table.column>
                <flux:table.column>De / Para</flux:table.column>
                <flux:table.column>Asunto</flux:table.column>
                <flux:table.column>Fecha memo</flux:table.column>
                <flux:table.column>Envío / recepción</flux:table.column>
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
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="eye" :href="route('memorandos.ver', $memo)" wire:navigate>Ver</flux:button>
                            @if ($memo->archivoVigente)
                                <flux:button size="sm" variant="ghost" icon="document-text" :href="route('memorandos.pdf', $memo)" target="_blank" aria-label="Abrir PDF">PDF</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">No hay memorandos con estos filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
