@props(['confirmacion'])

<flux:modal name="confirmar-accion" class="w-full max-w-md">
    <div class="space-y-4">
        <flux:heading size="lg">{{ $confirmacion['titulo'] }}</flux:heading>

        <flux:text>{{ $confirmacion['texto'] }}</flux:text>

        <div class="flex justify-end gap-2 pt-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancelar</flux:button>
            </flux:modal.close>

            @if ($confirmacion['peligro'])
                <flux:button variant="ghost" class="!text-red-600 hover:!bg-red-50 dark:!text-red-400 dark:hover:!bg-red-950" wire:click="confirmarAccion">
                    {{ $confirmacion['boton'] }}
                </flux:button>
            @else
                <flux:button variant="primary" wire:click="confirmarAccion">{{ $confirmacion['boton'] }}</flux:button>
            @endif
        </div>
    </div>
</flux:modal>
