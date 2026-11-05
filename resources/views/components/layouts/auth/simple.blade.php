<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head', ['sinApariencia' => true])
</head>

<body class="min-h-screen bg-amdc-700 antialiased">
    <div class="flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
        <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-2xl shadow-amdc-900/40">
            <div class="mb-6 flex items-center gap-4">
                <x-escudo class="size-16 shrink-0" />
                <div class="leading-tight">
                    <p class="text-2xl font-bold text-amdc-700">Registro de memorandums</p>
                    <p class="text-sm text-zinc-500">Gerencia de Información y Sistemas</p>
                </div>
            </div>

            <div class="flex flex-col gap-6 [&_[data-flux-label]]:text-zinc-700">
                {{ $slot }}
            </div>
        </div>

        <p class="mt-6 text-xs text-white/70">Alcaldía Municipal del Distrito Central</p>
    </div>
    @fluxScripts
</body>

</html>
