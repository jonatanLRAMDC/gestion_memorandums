<?php

use App\Livewire\Concerns\ConfirmaAcciones;
use App\Models\Bitacora;
use App\Models\Gerencia;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Gerencias')] class extends Component {
    use ConfirmaAcciones;
    use WithPagination;

    public string $buscar = '';

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $siglas = '';

    public string $titular_nombre = '';

    public string $titular_cargo = '';

    public bool $usa_sistema = false;

    public function boot(): void
    {
        Gate::authorize('gerencias.consultar');
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Gerencia>
     */
    #[Computed]
    public function gerencias(): LengthAwarePaginator
    {
        return Gerencia::query()
            ->when($this->buscar !== '', function ($query): void {
                $query->where(fn ($q) => $q
                    ->where('nombre', 'like', "%{$this->buscar}%")
                    ->orWhere('siglas', 'like', "%{$this->buscar}%")
                    ->orWhere('titular_nombre', 'like', "%{$this->buscar}%"));
            })
            ->orderBy('nombre')
            ->paginate(15);
    }

    public function crear(): void
    {
        Gate::authorize('gerencias.crear');

        $this->resetErrorBag();
        $this->reset('editandoId', 'nombre', 'siglas', 'titular_nombre', 'titular_cargo', 'usa_sistema');

        Flux::modal('gerencia')->show();
    }

    public function editar(Gerencia $gerencia): void
    {
        Gate::authorize('gerencias.editar');

        $this->resetErrorBag();
        $this->editandoId = $gerencia->id;
        $this->nombre = $gerencia->nombre;
        $this->siglas = (string) $gerencia->siglas;
        $this->titular_nombre = (string) $gerencia->titular_nombre;
        $this->titular_cargo = (string) $gerencia->titular_cargo;
        $this->usa_sistema = $gerencia->usa_sistema;

        Flux::modal('gerencia')->show();
    }

    public function guardar(): void
    {
        Gate::authorize($this->editandoId ? 'gerencias.editar' : 'gerencias.crear');

        $this->siglas = mb_strtoupper(trim($this->siglas));

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:150', Rule::unique('gerencias', 'nombre')->ignore($this->editandoId)],
            'siglas' => ['nullable', 'string', 'max:20', 'regex:/^[A-ZÁÉÍÓÚÑ]+$/u', Rule::unique('gerencias', 'siglas')->ignore($this->editandoId)],
            'titular_nombre' => ['nullable', 'string', 'max:150'],
            'titular_cargo' => ['nullable', 'string', 'max:150'],
            'usa_sistema' => ['boolean'],
        ], [
            'siglas.regex' => 'Las siglas solo deben contener letras.',
        ]);

        $datos = array_map(fn ($valor) => $valor === '' ? null : $valor, $datos);

        if ($this->editandoId) {
            $gerencia = Gerencia::findOrFail($this->editandoId);
            $antes = $gerencia->only(array_keys($datos));
            $gerencia->update($datos);

            Bitacora::registrar('gerencia_editada', $gerencia, $antes, $gerencia->only(array_keys($datos)));
            Flux::toast(variant: 'success', text: 'Gerencia actualizada.');
        } else {
            $gerencia = Gerencia::create($datos);

            Bitacora::registrar('gerencia_creada', $gerencia, despues: $gerencia->only(array_keys($datos)));
            Flux::toast(variant: 'success', text: 'Gerencia creada.');
        }

        Flux::modal('gerencia')->close();
    }

    public function solicitarCambioEstado(Gerencia $gerencia): void
    {
        $this->pedirConfirmacion(
            'cambiarEstado',
            $gerencia->id,
            $gerencia->activa ? 'Desactivar gerencia' : 'Activar gerencia',
            $gerencia->activa
                ? "¿Desea desactivar {$gerencia->nombre}? Ya no estará disponible para nuevos memorandos, pero permanecerá en el sistema y en los registros históricos."
                : "¿Desea activar {$gerencia->nombre}? Volverá a estar disponible en los formularios.",
            $gerencia->activa ? 'Desactivar' : 'Activar',
            peligro: $gerencia->activa,
        );
    }

    /**
     * @return list<string>
     */
    protected function accionesConfirmables(): array
    {
        return ['cambiarEstado'];
    }

    public function cambiarEstado(Gerencia $gerencia): void
    {
        Gate::authorize('gerencias.activar');

        if ($gerencia->activa && $gerencia->siglas === config('memorandos.siglas_gerencia_propia')) {
            Flux::toast(variant: 'danger', text: 'La gerencia propia del sistema no se puede desactivar.');

            return;
        }

        $gerencia->update(['activa' => ! $gerencia->activa]);

        Bitacora::registrar($gerencia->activa ? 'gerencia_activada' : 'gerencia_desactivada', $gerencia);
        Flux::toast(text: $gerencia->activa ? 'Gerencia activada.' : 'Gerencia desactivada. Sus memorandos se siguen consultando.');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Gerencias</flux:heading>
            <flux:subheading>Catálogo de gerencias que envían y reciben memorandos.</flux:subheading>
        </div>

        @can('gerencias.crear')
            <flux:button variant="primary" icon="plus" wire:click="crear">Nueva gerencia</flux:button>
        @endcan
    </div>

    <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" placeholder="Buscar por nombre, siglas o titular…" class="max-w-md" clearable />

    <div class="rounded-xl border border-zinc-200 bg-white px-4 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$this->gerencias">
            <flux:table.columns>
                <flux:table.column>Siglas</flux:table.column>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Titular</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column align="end">Acciones</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->gerencias as $gerencia)
                    <flux:table.row :key="$gerencia->id">
                        <flux:table.cell class="font-mono text-sm">{{ $gerencia->siglas ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="whitespace-normal font-medium text-zinc-800 dark:text-white">
                            {{ $gerencia->nombre }}
                            @if ($gerencia->usa_sistema)
                                <flux:badge size="sm" color="blue" class="ms-1">Usa el sistema</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-normal">
                            @if ($gerencia->titular_nombre)
                                {{ $gerencia->titular_nombre }}
                                <span class="block text-xs text-zinc-500">{{ $gerencia->titular_cargo }}</span>
                            @else
                                <span class="text-zinc-400">Sin titular</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$gerencia->activa ? 'green' : 'zinc'" inset="top bottom">
                                {{ $gerencia->activa ? 'Activa' : 'Inactiva' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @can('gerencias.editar')
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editar({{ $gerencia->id }})">Editar</flux:button>
                            @endcan
                            @can('gerencias.activar')
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    :icon="$gerencia->activa ? 'no-symbol' : 'check-circle'"
                                    :class="$gerencia->activa ? '!text-red-600 dark:!text-red-400' : ''"
                                    wire:click="solicitarCambioEstado({{ $gerencia->id }})"
                                >
                                    {{ $gerencia->activa ? 'Desactivar' : 'Activar' }}
                                </flux:button>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">No hay gerencias que coincidan con la búsqueda.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <x-modal-confirmacion :confirmacion="$confirmacion" />

    <flux:modal name="gerencia" class="w-full max-w-lg">
        <form wire:submit="guardar" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editandoId ? 'Editar gerencia' : 'Nueva gerencia' }}</flux:heading>
                <flux:subheading>Las siglas forman parte del número de memorando (AMDC-SIGLAS-correlativo-año).</flux:subheading>
            </div>

            <flux:input wire:model="nombre" label="Nombre" placeholder="Gerencia de …" required />
            <flux:input wire:model="siglas" label="Siglas" placeholder="GIS" class="max-w-40" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="titular_nombre" label="Titular" placeholder="Nombre del gerente" />
                <flux:input wire:model="titular_cargo" label="Cargo del titular" placeholder="Gerente" />
            </div>
            <flux:switch wire:model="usa_sistema" label="Usa el sistema" description="Para la expansión a varias gerencias (Fase 5)." />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
