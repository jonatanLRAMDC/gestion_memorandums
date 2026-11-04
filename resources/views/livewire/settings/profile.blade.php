<?php

use App\Models\Bitacora;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $antes = $user->only('name');

        $user->fill($validated)->save();

        Bitacora::registrar('edicion_perfil', $user, $antes, $user->only('name'));

        $this->dispatch('profile-updated', name: $user->name);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="Perfil" subheading="Actualice su nombre. El correo, la gerencia y el rol los administra el Administrador.">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" label="Nombre" type="text" name="name" required autofocus autocomplete="name" />

            <flux:input label="Correo electrónico" :value="auth()->user()->email" readonly disabled />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input label="Gerencia" :value="auth()->user()->gerencia?->nombre ?? 'Sin gerencia'" readonly disabled />
                <flux:input label="Rol" :value="auth()->user()->rol?->nombre ?? 'Sin rol'" readonly disabled />
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">Guardar</flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    Guardado.
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
