<?php

use App\Enums\Rol;
use App\Models\Bitacora;
use App\Models\Gerencia;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Usuarios')] class extends Component {
    use WithPagination;

    public string $buscar = '';

    public ?int $editandoId = null;

    public string $name = '';

    public string $email = '';

    public ?int $gerencia_id = null;

    public string $rol = 'consulta';

    /**
     * Contraseña temporal recién generada; se muestra una sola vez.
     */
    public ?string $contrasenaTemporal = null;

    public ?string $usuarioContrasena = null;

    public function boot(): void
    {
        Gate::authorize('administrar');
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function usuarios(): LengthAwarePaginator
    {
        return User::query()
            ->with('gerencia')
            ->when($this->buscar !== '', function ($query): void {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->buscar}%")
                    ->orWhere('email', 'like', "%{$this->buscar}%"));
            })
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * @return Collection<int, Gerencia>
     */
    #[Computed]
    public function gerencias(): Collection
    {
        return Gerencia::activas()->orderBy('nombre')->get(['id', 'nombre', 'siglas']);
    }

    public function crear(): void
    {
        $this->resetErrorBag();
        $this->reset('editandoId', 'name', 'email');
        $this->rol = Rol::Consulta->value;
        $this->gerencia_id = Gerencia::propia()?->id;

        Flux::modal('usuario')->show();
    }

    public function editar(User $usuario): void
    {
        $this->resetErrorBag();
        $this->editandoId = $usuario->id;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->gerencia_id = $usuario->gerencia_id;
        $this->rol = $usuario->rol->value;

        Flux::modal('usuario')->show();
    }

    public function guardar(): void
    {
        $this->email = Str::lower(trim($this->email));

        $datos = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editandoId)],
            'gerencia_id' => ['required', Rule::exists('gerencias', 'id')->where('activa', true)],
            'rol' => ['required', Rule::enum(Rol::class)],
        ]);

        if ($this->editandoId === auth()->id() && $datos['rol'] !== Rol::Administrador->value) {
            $this->addError('rol', 'No puede quitarse a sí mismo el rol de Administrador.');

            return;
        }

        if ($this->editandoId) {
            $usuario = User::findOrFail($this->editandoId);
            $antes = $this->resumen($usuario);
            $usuario->update($datos);

            Bitacora::registrar('usuario_editado', $usuario, $antes, $this->resumen($usuario));
            Flux::toast(variant: 'success', text: 'Usuario actualizado.');
            Flux::modal('usuario')->close();

            return;
        }

        $contrasena = $this->generarContrasena();

        $usuario = User::create([
            ...$datos,
            'password' => $contrasena,
            'activo' => true,
            'debe_cambiar_contrasena' => true,
        ]);

        Bitacora::registrar('usuario_creado', $usuario, despues: $this->resumen($usuario));

        Flux::modal('usuario')->close();
        $this->mostrarContrasena($usuario, $contrasena);
    }

    public function restablecerContrasena(User $usuario): void
    {
        if ($usuario->is(auth()->user())) {
            Flux::toast(variant: 'danger', text: 'Cambie su propia contraseña desde Configuración.');

            return;
        }

        $contrasena = $this->generarContrasena();

        $usuario->forceFill([
            'password' => $contrasena,
            'debe_cambiar_contrasena' => true,
            'intentos_fallidos' => 0,
            'bloqueado_en' => null,
        ])->save();

        Bitacora::registrar('contrasena_restablecida', $usuario);

        $this->mostrarContrasena($usuario, $contrasena);
    }

    public function desbloquear(User $usuario): void
    {
        $usuario->desbloquear();

        Bitacora::registrar('usuario_desbloqueado', $usuario);
        Flux::toast(variant: 'success', text: 'Cuenta desbloqueada.');
    }

    public function cambiarEstado(User $usuario): void
    {
        if ($usuario->is(auth()->user())) {
            Flux::toast(variant: 'danger', text: 'No puede desactivar su propia cuenta.');

            return;
        }

        $usuario->forceFill(['activo' => ! $usuario->activo])->save();

        Bitacora::registrar($usuario->activo ? 'usuario_activado' : 'usuario_desactivado', $usuario);
        Flux::toast(text: $usuario->activo ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    protected function generarContrasena(): string
    {
        return Str::password(12, symbols: false);
    }

    protected function mostrarContrasena(User $usuario, string $contrasena): void
    {
        $this->usuarioContrasena = $usuario->email;
        $this->contrasenaTemporal = $contrasena;

        Flux::modal('contrasena-temporal')->show();
    }

    public function cerrarContrasena(): void
    {
        $this->reset('contrasenaTemporal', 'usuarioContrasena');
    }

    /**
     * @return array<string, mixed>
     */
    protected function resumen(User $usuario): array
    {
        return [
            'name' => $usuario->name,
            'email' => $usuario->email,
            'gerencia_id' => $usuario->gerencia_id,
            'rol' => $usuario->rol->value,
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Usuarios</flux:heading>
            <flux:subheading>Cuentas con acceso al sistema. Las crea y restablece el Administrador.</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="crear">Nuevo usuario</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" placeholder="Buscar por nombre o correo…" class="max-w-md" clearable />

    <div class="rounded-xl border border-zinc-200 bg-white px-4 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$this->usuarios">
            <flux:table.columns>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Gerencia</flux:table.column>
                <flux:table.column>Rol</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column align="end">Acciones</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->usuarios as $usuario)
                    @php($estado = $usuario->estadoCuenta())
                    <flux:table.row :key="$usuario->id">
                        <flux:table.cell>
                            <span class="font-medium text-zinc-800 dark:text-white">{{ $usuario->name }}</span>
                            <span class="block text-xs text-zinc-500">{{ $usuario->email }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $usuario->gerencia?->siglas ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $usuario->rol->etiqueta() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" inset="top bottom" :color="match ($estado) { 'Activo' => 'green', 'Bloqueado' => 'red', default => 'zinc' }">
                                {{ $estado }}
                            </flux:badge>
                            @if ($usuario->debe_cambiar_contrasena)
                                <span class="block text-xs text-amber-600">Contraseña temporal</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="Acciones" />

                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="editar({{ $usuario->id }})">Editar</flux:menu.item>
                                    @if ($usuario->estaBloqueado())
                                        <flux:menu.item icon="lock-open" wire:click="desbloquear({{ $usuario->id }})">Desbloquear</flux:menu.item>
                                    @endif
                                    @unless ($usuario->is(auth()->user()))
                                        <flux:menu.item icon="key" wire:click="restablecerContrasena({{ $usuario->id }})" wire:confirm="¿Generar una contraseña temporal nueva para {{ $usuario->name }}?">Restablecer contraseña</flux:menu.item>
                                        <flux:menu.item
                                            :icon="$usuario->activo ? 'no-symbol' : 'check-circle'"
                                            :variant="$usuario->activo ? 'danger' : 'default'"
                                            wire:click="cambiarEstado({{ $usuario->id }})"
                                            wire:confirm="{{ $usuario->activo ? '¿Desactivar esta cuenta? El usuario no podrá ingresar.' : '¿Activar esta cuenta?' }}"
                                        >
                                            {{ $usuario->activo ? 'Desactivar' : 'Activar' }}
                                        </flux:menu.item>
                                    @endunless
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">No hay usuarios que coincidan con la búsqueda.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="usuario" class="w-full max-w-lg">
        <form wire:submit="guardar" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editandoId ? 'Editar usuario' : 'Nuevo usuario' }}</flux:heading>
                <flux:subheading>
                    {{ $editandoId ? 'Los cambios de rol y gerencia quedan en la bitácora.' : 'Al guardar se genera una contraseña temporal que el usuario cambiará al entrar.' }}
                </flux:subheading>
            </div>

            <flux:input wire:model="name" label="Nombre completo" required />
            <flux:input wire:model="email" type="email" label="Correo electrónico" placeholder="usuario@amdc.hn" required />

            <flux:select wire:model="gerencia_id" label="Gerencia">
                <flux:select.option value="">Seleccione una gerencia</flux:select.option>
                @foreach ($this->gerencias as $gerencia)
                    <flux:select.option :value="$gerencia->id">{{ $gerencia->nombre }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="rol" label="Rol">
                @foreach (\App\Enums\Rol::cases() as $opcion)
                    <flux:select.option :value="$opcion->value">{{ $opcion->etiqueta() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="contrasena-temporal" class="w-full max-w-md" @close="cerrarContrasena">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Contraseña temporal</flux:heading>
                <flux:subheading>Entréguela a {{ $usuarioContrasena }}. No se volverá a mostrar; se pedirá cambiarla al ingresar.</flux:subheading>
            </div>

            <flux:input :value="$contrasenaTemporal" readonly copyable class="font-mono" />

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary">Listo</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
