<?php

use App\Livewire\Forms\MemorandoForm;
use App\Models\Gerencia;
use App\Models\Memorando;
use App\Models\Registro;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Editar memorando')] class extends Component {
    public MemorandoForm $form;

    public Memorando $memorando;

    public Registro $registro;

    public function mount(Memorando $memorando): void
    {
        $this->authorize('update', $memorando);

        $this->memorando = $memorando;
        $this->registro = $memorando->registros()->where('gerencia_id', auth()->user()->gerencia_id)->firstOrFail();

        $this->form->cargar($memorando, $this->registro);
    }

    #[Computed]
    public function propia(): Gerencia
    {
        return auth()->user()->gerencia;
    }

    /**
     * Gerencias activas, más las que el memo ya tenía aunque hoy estén inactivas.
     *
     * @return Collection<int, Gerencia>
     */
    #[Computed]
    public function gerencias(): Collection
    {
        $yaAsignadas = [$this->memorando->gerencia_emisora_id, ...$this->memorando->destinos()->pluck('gerencia_id')];

        return Gerencia::query()
            ->where(fn ($query) => $query->where('activa', true)->orWhereIn('id', $yaAsignadas))
            ->whereKeyNot(auth()->user()->gerencia_id)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'siglas', 'titular_nombre', 'titular_cargo']);
    }

    public function updatedForm(mixed $valor, string $campo): void
    {
        if ($campo === 'gerencia_remitente_id') {
            $gerencia = $this->gerencias->firstWhere('id', (int) $valor);
            $this->form->siglas = (string) $gerencia?->siglas;
            $this->form->remitente_nombre = (string) $gerencia?->titular_nombre;
            $this->form->remitente_cargo = (string) $gerencia?->titular_cargo;
        }

        if ($campo === 'siglas') {
            $this->form->siglas = mb_strtoupper(trim((string) $valor));
        }

        if (preg_match('/^destinos\.(\d+)\.gerencia_id$/', $campo, $partes)) {
            $gerencia = $this->gerencias->firstWhere('id', (int) $valor);

            if ($gerencia && blank($this->form->destinos[$partes[1]]['persona_nombre'] ?? null)) {
                $this->form->destinos[$partes[1]]['persona_nombre'] = (string) $gerencia->titular_nombre;
            }
        }
    }

    public function agregarDestino(): void
    {
        $this->form->agregarDestino();
    }

    public function quitarDestino(int $indice): void
    {
        $this->form->quitarDestino($indice);
    }

    public function guardar(): void
    {
        $this->authorize('update', $this->memorando);

        $cambios = $this->form->actualizar($this->memorando, $this->registro);

        if ($cambios === []) {
            Flux::toast(text: 'No hubo cambios que guardar.');

            return;
        }

        session()->flash('memorando_editado', $this->memorando->numero);

        $this->redirectRoute('memorandos.ver', $this->memorando, navigate: true);
    }
}; ?>

@php($emitido = $form->esEmitido())

<div class="flex flex-col gap-6">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('memorandos.ver', $memorando)" wire:navigate class="-ms-2 mb-2">{{ $memorando->numero }}</flux:button>
        <div class="flex items-center gap-3">
            <flux:heading size="xl" level="1">Editar memorando</flux:heading>
            <flux:badge :color="$emitido ? 'blue' : 'amber'">{{ $registro->tipo->etiqueta() }}</flux:badge>
        </div>
        <flux:subheading>Corrija los datos capturados. Cada cambio queda en la bitácora con su valor anterior.</flux:subheading>
    </div>

    <form wire:submit="guardar" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="flex flex-col gap-6">
            @include('partials.memorando-campos', [
                'gerencias' => $this->gerencias,
                'propia' => $this->propia,
                'edicion' => true,
                'registradoEn' => $registro->created_at->format('d/m/Y H:i'),
            ])
        </div>

        <aside class="flex flex-col gap-4 xl:sticky xl:top-6 xl:self-start">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>PDF escaneado</flux:heading>
                <flux:subheading>El PDF no se cambia desde aquí.</flux:subheading>

                @if ($memorando->archivoVigente)
                    <iframe src="{{ route('memorandos.pdf', $memorando) }}" title="PDF del memorando {{ $memorando->numero }}" class="mt-4 h-[420px] w-full rounded-lg border border-zinc-200 dark:border-zinc-700"></iframe>
                @endif

                @can('replacePdf', $memorando)
                    <flux:button class="mt-4 w-full" icon="arrow-path" :href="route('memorandos.reemplazar-pdf', $memorando)" wire:navigate>Reemplazar PDF</flux:button>
                @endcan
            </section>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('memorandos.ver', $memorando)" wire:navigate>Cancelar</flux:button>
                <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="guardar">Guardar cambios</flux:button>
            </div>
        </aside>
    </form>
</div>
