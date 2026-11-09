<?php

use App\Models\Bitacora;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Bitácora')] class extends Component {
    use WithPagination;

    public string $accion = '';

    public string $buscarUsuario = '';

    public ?int $detalleId = null;

    public function boot(): void
    {
        Gate::authorize('bitacora.consultar');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Muestra los valores de antes y después de una entrada.
     */
    public function verDetalle(int $id): void
    {
        $this->detalleId = $id;

        Flux::modal('detalle-bitacora')->show();
    }

    #[Computed]
    public function detalle(): ?Bitacora
    {
        return $this->detalleId ? Bitacora::with('usuario:id,name')->find($this->detalleId) : null;
    }

    /**
     * @return LengthAwarePaginator<int, Bitacora>
     */
    #[Computed]
    public function entradas(): LengthAwarePaginator
    {
        return Bitacora::query()
            ->with('usuario:id,name,email')
            ->when($this->accion !== '', fn ($query) => $query->where('accion', $this->accion))
            ->when($this->buscarUsuario !== '', function ($query): void {
                $query->whereHas('usuario', fn ($q) => $q
                    ->where('name', 'like', "%{$this->buscarUsuario}%")
                    ->orWhere('email', 'like', "%{$this->buscarUsuario}%"));
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate(25);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">Bitácora</flux:heading>
        <flux:subheading>Registro de solo lectura de accesos y cambios en el sistema.</flux:subheading>
    </div>

    <div class="flex flex-wrap gap-4">
        <flux:select wire:model.live="accion" class="max-w-xs">
            <flux:select.option value="">Todas las acciones</flux:select.option>
            @foreach (\App\Models\Bitacora::ACCIONES as $clave => $etiqueta)
                <flux:select.option :value="$clave">{{ $etiqueta }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model.live.debounce.300ms="buscarUsuario" icon="magnifying-glass" placeholder="Buscar usuario…" class="max-w-xs" clearable />
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white px-4 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$this->entradas">
            <flux:table.columns>
                <flux:table.column>Fecha y hora</flux:table.column>
                <flux:table.column>Usuario</flux:table.column>
                <flux:table.column>Acción</flux:table.column>
                <flux:table.column>Registro</flux:table.column>
                <flux:table.column>IP</flux:table.column>
                <flux:table.column align="end">Detalle</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->entradas as $entrada)
                    <flux:table.row :key="$entrada->id">
                        <flux:table.cell class="tabular-nums">{{ $entrada->created_at?->format('d/m/Y H:i:s') }}</flux:table.cell>
                        <flux:table.cell>{{ $entrada->usuario?->name ?? ($entrada->despues['email'] ?? '—') }}</flux:table.cell>
                        <flux:table.cell class="whitespace-normal">{{ $entrada->etiquetaAccion() }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $entrada->etiquetaEntidad() ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs text-zinc-500">{{ $entrada->ip ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($entrada->antes || $entrada->despues)
                                <flux:button size="sm" variant="ghost" icon="eye" wire:click="verDetalle({{ $entrada->id }})" aria-label="Ver detalle">Ver</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">No hay registros con estos filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="detalle-bitacora" class="w-full max-w-2xl">
        @if ($detalle = $this->detalle)
            <flux:heading size="lg">{{ $detalle->etiquetaAccion() }}</flux:heading>
            <flux:subheading>{{ $detalle->created_at?->format('d/m/Y H:i:s') }} · {{ $detalle->usuario?->name ?? '—' }} · {{ $detalle->etiquetaEntidad() ?? '—' }}</flux:subheading>

            <table class="mt-5 w-full table-fixed text-sm">
                <thead class="text-zinc-500">
                    <tr>
                        <th class="w-1/4 border-b border-zinc-200 py-2 text-start font-medium dark:border-zinc-700">Campo</th>
                        <th class="border-b border-zinc-200 py-2 text-start font-medium dark:border-zinc-700">Antes</th>
                        <th class="border-b border-zinc-200 py-2 text-start font-medium dark:border-zinc-700">Después</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detalle->cambios() as $fila)
                        <tr class="align-top">
                            <td class="border-b border-zinc-100 py-2 pe-3 text-zinc-500 dark:border-zinc-800">{{ $fila['campo'] }}</td>
                            <td class="border-b border-zinc-100 py-2 pe-3 break-words dark:border-zinc-800">{{ $fila['antes'] }}</td>
                            <td class="border-b border-zinc-100 py-2 break-words dark:border-zinc-800">{{ $fila['despues'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </flux:modal>
</div>
