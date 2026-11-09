<?php

use App\Models\Archivo;
use App\Models\Bitacora;
use App\Models\Memorando;
use App\Models\Registro;
use Illuminate\Support\Collection;
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

    /**
     * Todas las versiones del PDF, de la más reciente a la más antigua.
     *
     * @return Collection<int, Archivo>
     */
    #[Computed]
    public function versiones(): Collection
    {
        return $this->memorando->archivos()
            ->whereNull('registro_id')
            ->with('subidoPor:id,name')
            ->orderByDesc('version')
            ->get();
    }

    /**
     * Registro, ediciones y reemplazos del PDF, del más reciente al más antiguo.
     *
     * @return Collection<int, Bitacora>
     */
    #[Computed]
    public function cambios(): Collection
    {
        return Bitacora::query()
            ->where('entidad', 'Memorando')
            ->where('entidad_id', $this->memorando->id)
            ->whereIn('accion', ['memorando_registrado', 'memorando_editado', 'pdf_reemplazado'])
            ->with('usuario:id,name')
            ->latest('id')
            ->get();
    }
}; ?>

@php($registro = $this->registro)
@php($archivo = $memorando->archivoVigente)

<div class="flex flex-col gap-6">
    @if (session('memorando_editado'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.heading>Cambios guardados</flux:callout.heading>
            <flux:callout.text>Los datos del memorando {{ session('memorando_editado') }} se actualizaron y el cambio quedó en la bitácora.</flux:callout.text>
        </flux:callout>
    @endif

    @if (session('pdf_reemplazado'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.heading>PDF reemplazado</flux:callout.heading>
            <flux:callout.text>La versión {{ session('pdf_reemplazado') }} es ahora la vigente. La anterior quedó guardada como no vigente.</flux:callout.text>
        </flux:callout>
    @endif

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

        <div class="flex flex-wrap gap-2">
            @can('update', $memorando)
                <flux:button icon="pencil-square" :href="route('memorandos.editar', $memorando)" wire:navigate>Editar</flux:button>
            @endcan
            @can('replacePdf', $memorando)
                <flux:button icon="arrow-path" :href="route('memorandos.reemplazar-pdf', $memorando)" wire:navigate>Reemplazar PDF</flux:button>
            @endcan
            @if ($archivo)
                <flux:button icon="arrow-top-right-on-square" :href="route('memorandos.pdf', $memorando)" target="_blank">Abrir PDF</flux:button>
                @can('download', $memorando)
                    <flux:button variant="primary" icon="arrow-down-tray" :href="route('memorandos.pdf', [$memorando, 'descargar' => 1])">Descargar</flux:button>
                @endcan
            @endif
        </div>
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

                    @foreach ([\App\Enums\TipoDestino::Para, \App\Enums\TipoDestino::Copia] as $tipoDestino)
                        @php($grupo = $memorando->destinos->where('tipo', $tipoDestino))
                        @if ($grupo->isNotEmpty())
                            <dt class="text-zinc-500">{{ $tipoDestino === \App\Enums\TipoDestino::Para ? 'Para' : 'Con copia a' }}</dt>
                            <dd class="space-y-2" data-destinos="{{ $tipoDestino->value }}">
                                @foreach ($grupo as $destino)
                                    <div>
                                        {{ $destino->gerencia->nombre }}
                                        @if ($destino->persona_nombre)
                                            <span class="block text-zinc-500">{{ $destino->persona_nombre }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </dd>
                        @endif
                    @endforeach

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
                    <div class="flex items-center justify-between gap-2">
                        <flux:heading>Archivo</flux:heading>
                        <flux:badge size="sm" color="green">Versión {{ $archivo->version }} · vigente</flux:badge>
                    </div>
                    <dl class="mt-4 grid grid-cols-[150px_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
                        <dt class="text-zinc-500">Páginas</dt>
                        <dd>{{ $archivo->paginas ?? 'No determinado' }}</dd>
                        <dt class="text-zinc-500">Tamaño</dt>
                        <dd>{{ \App\Models\Archivo::tamanoLegible($archivo->tamano_bytes) }}</dd>
                        <dt class="text-zinc-500">Nombre original</dt>
                        <dd class="truncate">{{ $archivo->nombre_original }}</dd>
                        <dt class="text-zinc-500">Huella SHA-256</dt>
                        <dd class="truncate font-mono text-xs" title="{{ $archivo->sha256 }}">{{ $archivo->sha256 }}</dd>
                        @if ($archivo->motivo)
                            <dt class="text-zinc-500">Motivo de la versión</dt>
                            <dd>{{ $archivo->motivo }}</dd>
                        @endif
                    </dl>
                </section>
            @endif

            @can('viewHistory', $memorando)
                <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900" data-historial>
                    <flux:heading>Versiones del PDF</flux:heading>
                    <ul class="mt-4 divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                        @foreach ($this->versiones as $version)
                            <li class="flex items-start justify-between gap-3 py-2.5">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">Versión {{ $version->version }}</span>
                                        @if ($version->vigente)
                                            <flux:badge size="sm" color="green">Vigente</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc">No vigente</flux:badge>
                                        @endif
                                    </div>
                                    <p class="text-zinc-500">{{ $version->created_at->format('d/m/Y H:i') }} · {{ $version->subidoPor?->name }} · {{ $version->paginas ?? '?' }} pág.</p>
                                    @if ($version->motivo)
                                        <p class="text-zinc-600 dark:text-zinc-300">{{ $version->motivo }}</p>
                                    @endif
                                </div>
                                <flux:button size="sm" variant="ghost" icon="eye" :href="route('memorandos.pdf', $version->vigente ? $memorando : [$memorando, 'version' => $version->id])" target="_blank">Ver</flux:button>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading>Historial de cambios</flux:heading>
                    <ol class="mt-4 space-y-4 text-sm">
                        @forelse ($this->cambios as $cambio)
                            <li class="border-s-2 border-zinc-200 ps-3 dark:border-zinc-700">
                                <p class="font-medium">{{ $cambio->etiquetaAccion() }}</p>
                                <p class="text-zinc-500">{{ $cambio->created_at->format('d/m/Y H:i') }} · {{ $cambio->usuario?->name ?? 'Sistema' }}</p>
                                @if ($cambio->accion !== 'memorando_registrado' && $cambio->cambios())
                                    <table class="mt-2 w-full table-fixed text-xs">
                                        <thead class="text-start text-zinc-500">
                                            <tr><th class="w-1/4 py-1 text-start font-normal">Campo</th><th class="py-1 text-start font-normal">Antes</th><th class="py-1 text-start font-normal">Después</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($cambio->cambios() as $fila)
                                                <tr class="align-top">
                                                    <td class="py-1 pe-2 text-zinc-500">{{ $fila['campo'] }}</td>
                                                    <td class="py-1 pe-2 text-red-700 line-through decoration-red-300 dark:text-red-400">{{ $fila['antes'] }}</td>
                                                    <td class="py-1 text-green-700 dark:text-green-400">{{ $fila['despues'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </li>
                        @empty
                            <li class="text-zinc-500">Sin cambios registrados.</li>
                        @endforelse
                    </ol>
                </section>
            @endcan
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
