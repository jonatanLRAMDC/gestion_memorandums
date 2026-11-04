<?php

use App\Models\Bitacora;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:current_password'],
            ], attributes: [
                'password' => 'nueva contraseña',
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        $user = Auth::user();
        $eraTemporal = $user->debe_cambiar_contrasena;

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'debe_cambiar_contrasena' => false,
        ])->save();

        Bitacora::registrar('cambio_contrasena', $user);

        $this->reset('current_password', 'password', 'password_confirmation');

        if ($eraTemporal) {
            $this->redirectRoute('dashboard', navigate: true);

            return;
        }

        $this->dispatch('password-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="Contraseña" subheading="Use una contraseña larga y difícil de adivinar">
        @if (auth()->user()->debe_cambiar_contrasena)
            <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6">
                <flux:callout.heading>Cambie su contraseña temporal</flux:callout.heading>
                <flux:callout.text>Para continuar debe reemplazar la contraseña que le asignó el administrador.</flux:callout.text>
            </flux:callout>
        @endif

        <form wire:submit="updatePassword" class="mt-6 space-y-6">
            <flux:input
                wire:model="current_password"
                id="update_password_current_password"
                label="Contraseña actual"
                type="password"
                name="current_password"
                required
                autocomplete="current-password"
            />
            <flux:input
                wire:model="password"
                id="update_password_password"
                label="Nueva contraseña"
                type="password"
                name="password"
                required
                autocomplete="new-password"
            />
            <flux:input
                wire:model="password_confirmation"
                id="update_password_password_confirmation"
                label="Confirmar nueva contraseña"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
            />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">Guardar</flux:button>
                </div>

                <x-action-message class="me-3" on="password-updated">
                    Guardado.
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
