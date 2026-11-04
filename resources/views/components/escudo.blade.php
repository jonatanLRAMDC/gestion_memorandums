@php($ruta = 'images/escudo-amdc.png')

@if (file_exists(public_path($ruta)))
    <img src="{{ asset($ruta) }}" alt="Escudo de la Alcaldía Municipal del Distrito Central" {{ $attributes->merge(['class' => 'object-contain']) }}>
@else
    <span {{ $attributes->merge(['class' => 'flex items-center justify-center rounded-full bg-amdc-700 text-white']) }}>
        <x-app-logo-icon class="size-1/2" />
    </span>
@endif
