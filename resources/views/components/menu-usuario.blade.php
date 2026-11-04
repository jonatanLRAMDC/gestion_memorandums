<flux:menu {{ $attributes }}>
    <flux:menu.radio.group>
        <div class="p-0 text-sm font-normal">
            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                    <span class="flex h-full w-full items-center justify-center rounded-lg bg-amdc-100 text-amdc-800 dark:bg-neutral-700 dark:text-white">
                        {{ auth()->user()->initials() }}
                    </span>
                </span>

                <div class="grid flex-1 text-left text-sm leading-tight">
                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                    <span class="truncate text-xs text-zinc-500">{{ auth()->user()->rol?->nombre }}</span>
                </div>
            </div>
        </div>
    </flux:menu.radio.group>

    <flux:menu.separator />

    <flux:menu.radio.group>
        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>Configuración</flux:menu.item>
    </flux:menu.radio.group>

    <flux:menu.separator />

    <form method="POST" action="{{ route('logout') }}" class="w-full">
        @csrf
        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
            Cerrar sesión
        </flux:menu.item>
    </form>
</flux:menu>
