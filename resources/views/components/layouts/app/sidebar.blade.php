<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-800">
        @php($usuario = auth()->user())

        <flux:sidebar sticky collapsible class="dark border-r-0 bg-linear-to-b from-amdc-700 to-amdc-900">
            {{-- Logo y botón para plegar --}}
            <flux:sidebar.header>
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2 in-data-flux-sidebar-collapsed-desktop:hidden" wire:navigate>
                    <x-app-logo />
                </a>
                <a href="{{ route('dashboard') }}" class="hidden in-data-flux-sidebar-collapsed-desktop:flex" wire:navigate aria-label="Panel">
                    <x-escudo class="size-10 !bg-white/15 ring-1 ring-white/30" />
                </a>
                <flux:sidebar.collapse tooltip="Plegar o desplegar el menú" />
            </flux:sidebar.header>

            <flux:separator class="!bg-white/15" />

            {{-- Usuario actual --}}
            <div class="flex items-center gap-3 px-1 in-data-flux-sidebar-collapsed-desktop:justify-center in-data-flux-sidebar-collapsed-desktop:px-0" data-usuario-actual>
                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/15 text-sm font-semibold text-white" title="{{ $usuario->name }}">
                    {{ $usuario->initials() }}
                </span>
                <div class="grid min-w-0 flex-1 leading-tight in-data-flux-sidebar-collapsed-desktop:hidden">
                    <span class="truncate text-sm font-semibold text-white">{{ $usuario->name }}</span>
                    <span class="truncate text-xs text-white/70">{{ $usuario->email }}</span>
                    <span class="truncate text-xs text-white/55">{{ $usuario->rol?->nombre }}{{ $usuario->gerencia?->siglas ? ' · '.$usuario->gerencia->siglas : '' }}</span>
                </div>
            </div>

            <flux:separator class="!bg-white/15" />

            {{-- Navegación --}}
            <flux:sidebar.nav>
                <p class="px-3 pb-1 text-xs font-medium text-white/50 in-data-flux-sidebar-collapsed-desktop:hidden">Principal</p>
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Panel</flux:sidebar.item>
                @can('memorandos.consultar')
                    <flux:sidebar.item icon="document-text" :href="route('memorandos.index')" :current="request()->routeIs('memorandos.*')" wire:navigate>Memorandos</flux:sidebar.item>
                @endcan
                @can('reportes.consultar')
                    <flux:sidebar.item icon="chart-bar" :href="route('reportes.index')" :current="request()->routeIs('reportes.*')" wire:navigate>Reportes</flux:sidebar.item>
                @endcan

                @canany(['gerencias.consultar', 'usuarios.consultar', 'roles.consultar', 'bitacora.consultar'])
                    <p class="px-3 pb-1 pt-4 text-xs font-medium text-white/50 in-data-flux-sidebar-collapsed-desktop:hidden">Administración</p>
                    @can('gerencias.consultar')
                        <flux:sidebar.item icon="building-office-2" :href="route('admin.gerencias')" :current="request()->routeIs('admin.gerencias')" wire:navigate>Gerencias</flux:sidebar.item>
                    @endcan
                    @can('usuarios.consultar')
                        <flux:sidebar.item icon="users" :href="route('admin.usuarios')" :current="request()->routeIs('admin.usuarios')" wire:navigate>Usuarios</flux:sidebar.item>
                    @endcan
                    @can('roles.consultar')
                        <flux:sidebar.item icon="shield-check" :href="route('admin.roles')" :current="request()->routeIs('admin.roles')" wire:navigate>Roles y permisos</flux:sidebar.item>
                    @endcan
                    @can('bitacora.consultar')
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.bitacora')" :current="request()->routeIs('admin.bitacora')" wire:navigate>Bitácora</flux:sidebar.item>
                    @endcan
                @endcanany
            </flux:sidebar.nav>

            <flux:sidebar.spacer />

            <flux:separator class="!bg-white/15" />

            {{-- Configuración y salida --}}
            <flux:sidebar.nav>
                <flux:sidebar.item icon="cog-6-tooth" :href="route('settings.profile')" :current="request()->routeIs('settings.*')" wire:navigate>Configuración</flux:sidebar.item>

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:sidebar.item icon="arrow-right-start-on-rectangle" type="submit" class="hover:!bg-red-500/20 hover:!text-white">Cerrar sesión</flux:sidebar.item>
                </form>
            </flux:sidebar.nav>
        </flux:sidebar>

        {{-- Barra superior en teléfonos --}}
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <span class="ms-2 text-sm font-semibold text-zinc-800 dark:text-white">{{ config('app.name') }}</span>

            <flux:spacer />

            <span class="flex size-8 items-center justify-center rounded-lg bg-amdc-100 text-xs font-semibold text-amdc-800 dark:bg-zinc-700 dark:text-white">{{ $usuario->initials() }}</span>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast />
        @endpersist

        @fluxScripts
    </body>
</html>
