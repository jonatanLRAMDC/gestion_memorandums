<?php

use App\Models\Memorando;
use App\Models\Registro;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Memorando')] class extends Component {
    public Memorando $memorando;

    public function mount(Memorando $memorando): void
    {
        $this->authorize('view', $memorando);

        $this->memorando = $memorando->load([
            'gerenciaEmisora',
            'destinos.gerencia',
            'archivoVigente.subidoPor:id,name',
        ]);
    }

    /**
     * Registro del memo en la gerencia del usuario.
     */
    #[Computed]
    public function registro(): Registro
    {
        return $this->memorando->registros()
            ->with('usuario:id,name')
            ->where('gerencia_id', auth()->user()->gerencia_id)
            ->firstOrFail();
    }
}; ?>

@php($registro = $this->registro)
@php($archivo = $memorando->archivoVigente)

<div class="flex flex-col gap-6">
    @if (session('memorando_registrado'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.heading>Memorando {{ session('memorando_registrado') }} registrado</flux:callout.heading>
            <flux:callout.text>Los datos y el PDF se guardaron correctamente.</flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('memorandos.index')" wire:navigate class="-ms-2 mb-2">Memorandos</flux:button>
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1" class="font-mono">{{ $memorando->numero }}</flux:heading>
                <flux:badge :color="$registro->tipo === \App\Enums\TipoRegistro::Emitido ? 'blue' : 'amber'">{{ $registro->tipo->etiqueta() }}</flux:badge>
            </div>
            <flux:subheading>{{ $memorando->asunto }}</flux:subheading>
        </div>

        @if ($archivo)
            <div class="flex gap-2">
                <flux:button icon="arrow-top-right-on-square" :href="route('memorandos.pdf', $memorando)" target="_blank">Abrir PDF</flux:button>
                @can('download', $memorando)
                    <flux:button variant="primary" icon="arrow-down-tray" :href="route('memorandos.pdf', [$memorando, 'descargar' => 1])">Descargar</flux:button>
                @endcan
            </div>
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <div class="flex flex-col gap-6">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>Datos del memorando</flux:heading>
                <dl class="mt-4 grid grid-cols-[150px_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
                    <dt class="text-zinc-500">De</dt>
                    <dd>
                        {{ $memorando->gerenciaEmisora->nombre }}
                        @if ($memorando->remitente_nombre)
                            <span class="block text-zinc-500">{{ $memorando->remitente_nombre }}{{ $memorando->remitente_cargo ? ', '.$memorando->remitente_cargo : '' }}</span>
                        @endif
                    </dd>

                    <dt class="text-zinc-500">Para</dt>
                    <dd class="space-y-1">
                        @foreach ($memorando->destinos as $destino)
                            <div>
                                <flux:badge size="sm" color="zinc">{{ $destino->tipo->etiqueta() }}</flux:badge>
                                {{ $destino->gerencia->nombre }}
                                @if ($destino->persona_nombre)
                                    <span class="block ps-1 text-zinc-500">{{ $destino->persona_nombre }}</span>
                                @endif
                            </div>
                        @endforeach
                    </dd>

                    <dt class="text-zinc-500">Asunto</dt>
                    <dd>{{ $memorando->asunto }}</dd>

                    <dt class="text-zinc-500">Fecha del memo</dt>
                    <dd class="tabular-nums">{{ $memorando->fechaMemoTexto() }}</dd>
                </dl>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>Registro en {{ $registro->gerencia?->siglas ?? auth()->user()->gerencia->siglas }}</flux:heading>
                <dl class="mt-4 grid grid-cols-[150px_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
                    <dt class="text-zinc-500">{{ $registro->tipo === \App\Enums\TipoRegistro::Emitido ? 'Fecha de envío' : 'Fecha de recepción' }}</dt>
                    <dd class="tabular-nums">{{ $registro->fecha_movimiento->format('d/m/Y') }}</dd>

                    <dt class="text-zinc-500">Registrado por</dt>
                    <dd>{{ $registro->usuario?->name }}</dd>

                    <dt class="text-zinc-500">Fecha de registro</dt>
                    <dd class="tabular-nums">{{ $registro->created_at->format('d/m/Y H:i') }}</dd>

                    <dt class="text-zinc-500">Observaciones</dt>
                    <dd>{{ $registro->observaciones ?? '—' }}</dd>
                </dl>
            </section>

            @if ($archivo)
                <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading>Archivo</flux:heading>
                    <dl class="mt-4 grid grid-cols-[150px_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
                        <dt class="text-zinc-500">Páginas</dt>
                        <dd>{{ $archivo->paginas ?? 'No determinado' }}</dd>
                        <dt class="text-zinc-500">Tamaño</dt>
                        <dd>{{ \App\Models\Archivo::tamanoLegible($archivo->tamano_bytes) }}</dd>
                        <dt class="text-zinc-500">Nombre original</dt>
                        <dd class="truncate">{{ $archivo->nombre_original }}</dd>
                        <dt class="text-zinc-500">Huella SHA-256</dt>
                        <dd class="truncate font-mono text-xs" title="{{ $archivo->sha256 }}">{{ $archivo->sha256 }}</dd>
                    </dl>
                </section>
            @endif
        </div>

        <section class="rounded-xl border border-zinc-200 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900">
            @if ($archivo)
                <iframe src="{{ route('memorandos.pdf', $memorando) }}" title="PDF del memorando {{ $memorando->numero }}" class="h-[78vh] w-full rounded-lg"></iframe>
            @else
                <div class="flex h-64 items-center justify-center text-sm text-zinc-500">Este memorando no tiene PDF disponible.</div>
            @endif
        </section>
    </div>
</div>
