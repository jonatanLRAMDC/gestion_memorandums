<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="dark border-r-0 bg-linear-to-b from-amdc-700 to-amdc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mr-5 flex items-center gap-2" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group heading="Principal" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Panel</flux:navlist.item>
                    @can('memorandos.consultar')
                        <flux:navlist.item icon="document-text" :href="route('memorandos.index')" :current="request()->routeIs('memorandos.*')" wire:navigate>Memorandos</flux:navlist.item>
                    @endcan
                </flux:navlist.group>

                @canany(['gerencias.consultar', 'usuarios.consultar', 'roles.consultar', 'bitacora.consultar'])
                    <flux:navlist.group heading="Administración" class="grid">
                        @can('gerencias.consultar')
                            <flux:navlist.item icon="building-office-2" :href="route('admin.gerencias')" :current="request()->routeIs('admin.gerencias')" wire:navigate>Gerencias</flux:navlist.item>
                        @endcan
                        @can('usuarios.consultar')
                            <flux:navlist.item icon="users" :href="route('admin.usuarios')" :current="request()->routeIs('admin.usuarios')" wire:navigate>Usuarios</flux:navlist.item>
                        @endcan
                        @can('roles.consultar')
                            <flux:navlist.item icon="shield-check" :href="route('admin.roles')" :current="request()->routeIs('admin.roles')" wire:navigate>Roles y permisos</flux:navlist.item>
                        @endcan
                        @can('bitacora.consultar')
                            <flux:navlist.item icon="clipboard-document-list" :href="route('admin.bitacora')" :current="request()->routeIs('admin.bitacora')" wire:navigate>Bitácora</flux:navlist.item>
                        @endcan
                    </flux:navlist.group>
                @endcanany
            </flux:navlist>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <x-menu-usuario class="w-[240px]" />
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <x-menu-usuario />
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast />
        @endpersist

        @fluxScripts
    </body>
</html>
