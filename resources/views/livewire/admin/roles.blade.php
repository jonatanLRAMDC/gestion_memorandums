<?php

use App\Enums\Permiso;
use App\Livewire\Concerns\ConfirmaAcciones;
use App\Models\Bitacora;
use App\Models\Rol;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Roles')] class extends Component {
    use ConfirmaAcciones;
    use WithPagination;

    public string $buscar = '';

    public ?int $editandoId = null;

    public bool $editandoProtegido = false;

    public bool $slugManual = false;

    public string $nombre = '';

    public string $slug = '';

    public string $descripcion = '';

    /**
     * @var list<string>
     */
    public array $permisos = [];

    public function boot(): void
    {
        Gate::authorize('roles.consultar');
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedNombre(): void
    {
        if (! $this->editandoId && ! $this->slugManual) {
            $this->slug = Str::slug($this->nombre);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugManual = true;
    }

    /**
     * @return LengthAwarePaginator<int, Rol>
     */
    #[Computed]
    public function roles(): LengthAwarePaginator
    {
        return Rol::query()
            ->withCount(['permisos', 'usuarios'])
            ->when($this->buscar !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('nombre', 'like', "%{$this->buscar}%")
                ->orWhere('slug', 'like', "%{$this->buscar}%")))
            ->orderByDesc('protegido')
            ->orderBy('nombre')
            ->paginate(15);
    }

    public function crear(): void
    {
        Gate::authorize('roles.crear');

        $this->resetErrorBag();
        $this->reset('editandoId', 'editandoProtegido', 'slugManual', 'nombre', 'slug', 'descripcion', 'permisos');

        Flux::modal('rol')->show();
    }

    public function editar(Rol $rol): void
    {
        Gate::authorize('roles.editar');

        abort_if($rol->esAdministrador(), 403);

        $this->resetErrorBag();
        $this->editandoId = $rol->id;
        $this->editandoProtegido = $rol->protegido;
        $this->nombre = $rol->nombre;
        $this->slug = $rol->slug;
        $this->descripcion = (string) $rol->descripcion;
        $this->permisos = $rol->listaPermisos();

        Flux::modal('rol')->show();
    }

    public function guardar(): void
    {
        Gate::authorize($this->editandoId ? 'roles.editar' : 'roles.crear');

        $rol = $this->editandoId ? Rol::findOrFail($this->editandoId) : null;

        abort_if($rol?->esAdministrador(), 403);

        if ($rol?->protegido) {
            $this->slug = $rol->slug;
        }

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('roles', 'nombre')->ignore($this->editandoId)],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('roles', 'slug')->ignore($this->editandoId)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'permisos' => ['array'],
            'permisos.*' => [Rule::enum(Permiso::class)],
        ], [
            'slug.regex' => 'El slug solo admite minúsculas, números y guiones (por ejemplo: usuario-de-campo).',
        ]);

        $permisos = $datos['permisos'];
        unset($datos['permisos']);
        $datos['descripcion'] = $datos['descripcion'] === '' ? null : $datos['descripcion'];

        $antes = $rol ? [...$rol->only('nombre', 'slug', 'descripcion'), 'permisos' => $rol->listaPermisos()] : null;

        $rol = DB::transaction(function () use ($rol, $datos, $permisos): Rol {
            $rol ??= new Rol;
            $rol->fill($datos)->save();
            $rol->sincronizarPermisos($permisos);

            return $rol;
        });

        Bitacora::registrar(
            $antes ? 'rol_editado' : 'rol_creado',
            $rol,
            $antes,
            [...$rol->only('nombre', 'slug', 'descripcion'), 'permisos' => $rol->listaPermisos()],
        );

        Flux::modal('rol')->close();
        Flux::toast(variant: 'success', text: $antes ? 'Rol actualizado.' : 'Rol creado.');
    }

    public function solicitarCambioEstado(Rol $rol): void
    {
        $this->pedirConfirmacion(
            'cambiarEstado',
            $rol->id,
            $rol->activo ? 'Desactivar rol' : 'Activar rol',
            $rol->activo
                ? "¿Desea desactivar el rol {$rol->nombre}? No se podrá asignar a usuarios nuevos, pero permanecerá en el sistema."
                : "¿Desea activar el rol {$rol->nombre}? Volverá a estar disponible para asignarlo.",
            $rol->activo ? 'Desactivar' : 'Activar',
            peligro: $rol->activo,
        );
    }

    /**
     * @return list<string>
     */
    protected function accionesConfirmables(): array
    {
        return ['cambiarEstado'];
    }

    public function cambiarEstado(Rol $rol): void
    {
        Gate::authorize('roles.activar');

        if ($rol->protegido) {
            Flux::toast(variant: 'danger', text: 'Los roles protegidos no se pueden desactivar.');

            return;
        }

        if ($rol->activo && ($usuarios = $rol->usuarios()->where('activo', true)->count()) > 0) {
            Flux::toast(variant: 'danger', text: "Reasigne a los {$usuarios} usuarios activos con este rol antes de desactivarlo.");

            return;
        }

        $rol->update(['activo' => ! $rol->activo]);

        Bitacora::registrar($rol->activo ? 'rol_activado' : 'rol_desactivado', $rol);
        Flux::toast(text: $rol->activo ? 'Rol activado.' : 'Rol desactivado.');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Roles</flux:heading>
            <flux:subheading>Roles del sistema y los permisos que otorgan a sus usuarios.</flux:subheading>
        </div>

        @can('roles.crear')
            <flux:button variant="primary" icon="plus" wire:click="crear">Nuevo rol</flux:button>
        @endcan
    </div>

    <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" placeholder="Buscar por nombre o slug…" class="max-w-md" clearable />

    <div class="rounded-xl border border-zinc-200 bg-white px-4 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$this->roles">
            <flux:table.columns>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Slug</flux:table.column>
                <flux:table.column>Permisos</flux:table.column>
                <flux:table.column>Usuarios</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column align="end">Acciones</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->roles as $rol)
                    <flux:table.row :key="$rol->id">
                        <flux:table.cell class="whitespace-normal">
                            <span class="font-medium text-zinc-800 dark:text-white">{{ $rol->nombre }}</span>
                            <span class="block text-xs text-zinc-500">{{ $rol->descripcion }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $rol->slug }}</flux:table.cell>
                        <flux:table.cell>{{ $rol->esAdministrador() ? 'Todos' : $rol->permisos_count }}</flux:table.cell>
                        <flux:table.cell>{{ $rol->usuarios_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$rol->activo ? 'green' : 'zinc'" inset="top bottom">{{ $rol->activo ? 'Activo' : 'Inactivo' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($rol->esAdministrador())
                                <span class="px-3 text-sm text-zinc-400">Protegido</span>
                            @else
                                @can('roles.editar')
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editar({{ $rol->id }})">Editar</flux:button>
                                @endcan
                                @if ($rol->protegido)
                                    <span class="px-3 text-sm text-zinc-400">Protegido</span>
                                @else
                                    @can('roles.activar')
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            :icon="$rol->activo ? 'no-symbol' : 'check-circle'"
                                            :class="$rol->activo ? '!text-red-600 dark:!text-red-400' : ''"
                                            wire:click="solicitarCambioEstado({{ $rol->id }})"
                                        >
                                            {{ $rol->activo ? 'Desactivar' : 'Activar' }}
                                        </flux:button>
                                    @endcan
                                @endif
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">No hay roles que coincidan con la búsqueda.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <x-modal-confirmacion :confirmacion="$confirmacion" />

    <flux:modal name="rol" class="w-full max-w-2xl">
        <form wire:submit="guardar" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editandoId ? 'Editar rol' : 'Nuevo rol' }}</flux:heading>
                <flux:subheading>Modifique el nombre y los permisos del rol.</flux:subheading>
            </div>

            <flux:input wire:model.live.debounce.400ms="nombre" label="Nombre" placeholder="Usuario de campo" required />

            <flux:input
                wire:model.blur="slug"
                label="Slug"
                placeholder="usuario-de-campo"
                :description="$editandoProtegido ? 'El slug de un rol protegido no se puede cambiar.' : 'Identificador interno: minúsculas, números y guiones.'"
                :disabled="$editandoProtegido"
                class="font-mono"
            />

            <flux:textarea wire:model="descripcion" label="Descripción" rows="2" placeholder="Para qué sirve este rol" />

            <div>
                <flux:label>Permisos</flux:label>
                <flux:error name="permisos" />

                <flux:checkbox.group wire:model="permisos" class="mt-3 max-h-[45vh] space-y-3 overflow-y-auto pe-1">
                    @foreach (\App\Enums\Permiso::agrupados() as $modulo => $permisosModulo)
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <p class="mb-3 text-sm font-semibold text-zinc-800 dark:text-white">{{ $modulo }}</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($permisosModulo as $permiso)
                                    <flux:checkbox :value="$permiso->value" :label="$permiso->etiqueta()" />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </flux:checkbox.group>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
