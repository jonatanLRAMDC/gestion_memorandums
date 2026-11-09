<?php

use App\Enums\TipoRegistro;
use App\Livewire\Forms\MemorandoForm;
use App\Models\Archivo;
use App\Models\Gerencia;
use App\Rules\PdfValido;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Title('Registrar memorando')] class extends Component {
    use WithFileUploads;

    public MemorandoForm $form;

    public function mount(string $tipo): void
    {
        $propia = auth()->user()->gerencia;

        abort_unless($propia, 403, 'Su usuario no tiene una gerencia asignada. Contacte al administrador.');

        $this->form->iniciar(TipoRegistro::from($tipo), $propia);
    }

    #[Computed]
    public function propia(): Gerencia
    {
        return auth()->user()->gerencia;
    }

    /**
     * Gerencias activas que pueden ser contraparte del memo.
     *
     * @return Collection<int, Gerencia>
     */
    #[Computed]
    public function gerencias(): Collection
    {
        return Gerencia::activas()
            ->whereKeyNot(auth()->user()->gerencia_id)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'siglas', 'titular_nombre', 'titular_cargo']);
    }

    /**
     * Datos del PDF cargado, para mostrarlos antes de guardar.
     *
     * @return array{paginas: ?int, tamano: string}|null
     */
    #[Computed]
    public function infoPdf(): ?array
    {
        $pdf = $this->form->pdf;

        if (! $pdf || $this->getErrorBag()->has('form.pdf')) {
            return null;
        }

        return [
            'paginas' => PdfValido::contarPaginas($pdf->getRealPath()),
            'tamano' => Archivo::tamanoLegible($pdf->getSize()),
        ];
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

        if ($campo === 'fecha_memo' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $valor)) {
            $this->form->anio = (int) substr((string) $valor, 0, 4);
        }

        if (preg_match('/^destinos\.(\d+)\.gerencia_id$/', $campo, $partes)) {
            $gerencia = $this->gerencias->firstWhere('id', (int) $valor);

            if ($gerencia && blank($this->form->destinos[$partes[1]]['persona_nombre'] ?? null)) {
                $this->form->destinos[$partes[1]]['persona_nombre'] = (string) $gerencia->titular_nombre;
            }
        }

        if ($campo === 'pdf') {
            $this->form->confirmarPdfDuplicado = false;
            $this->form->pdfDuplicadoEn = null;
            $this->form->validateOnly('pdf');
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
        $memorando = $this->form->guardar();

        session()->flash('memorando_registrado', $memorando->numero);

        $this->redirectRoute('memorandos.ver', $memorando, navigate: true);
    }
}; ?>

@php($emitido = $form->esEmitido())

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Registrar memorando</flux:heading>
            <flux:subheading>Capture los datos del memo físico y adjunte el PDF escaneado.</flux:subheading>
        </div>

        <div class="inline-flex rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900">
            <a href="{{ route('memorandos.registrar', 'emitido') }}" wire:navigate
               @class(['rounded-md px-4 py-1.5 text-sm font-medium', 'bg-amdc-700 text-white' => $emitido, 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-300' => ! $emitido])>Emitido</a>
            <a href="{{ route('memorandos.registrar', 'recibido') }}" wire:navigate
               @class(['rounded-md px-4 py-1.5 text-sm font-medium', 'bg-amdc-700 text-white' => ! $emitido, 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-300' => $emitido])>Recibido</a>
        </div>
    </div>

    <form wire:submit="guardar" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="flex flex-col gap-6">
            @include('partials.memorando-campos', ['gerencias' => $this->gerencias, 'propia' => $this->propia, 'edicion' => false])
        </div>

        {{-- PDF --}}
        <aside class="flex flex-col gap-4 xl:sticky xl:top-6 xl:self-start">
            @include('partials.pdf-carga', [
                'titulo' => 'PDF escaneado',
                'infoPdf' => $this->infoPdf,
                'pdfDuplicadoEn' => $form->pdfDuplicadoEn,
                'etiquetaConfirmar' => 'Registrar de todos modos',
            ])

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('memorandos.index')" wire:navigate>Cancelar</flux:button>
                <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="guardar,form.pdf">Guardar memorando</flux:button>
            </div>
        </aside>
    </form>
</div>
