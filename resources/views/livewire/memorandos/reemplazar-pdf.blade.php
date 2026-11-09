<?php

use App\Livewire\Forms\ReemplazoPdfForm;
use App\Models\Archivo;
use App\Models\Memorando;
use App\Rules\PdfValido;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Title('Reemplazar PDF')] class extends Component {
    use WithFileUploads;

    public ReemplazoPdfForm $form;

    public Memorando $memorando;

    public function mount(Memorando $memorando): void
    {
        $this->authorize('replacePdf', $memorando);

        $this->memorando = $memorando;
    }

    #[Computed]
    public function vigente(): ?Archivo
    {
        return $this->memorando->archivoVigente()->with('subidoPor:id,name')->first();
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
        if ($campo === 'pdf') {
            $this->form->confirmarPdfDuplicado = false;
            $this->form->pdfDuplicadoEn = null;
            $this->form->validateOnly('pdf');
        }
    }

    public function guardar(): void
    {
        $this->authorize('replacePdf', $this->memorando);

        $archivo = $this->form->guardar($this->memorando);

        session()->flash('pdf_reemplazado', $archivo->version);

        $this->redirectRoute('memorandos.ver', $this->memorando, navigate: true);
    }
}; ?>

@php($vigente = $this->vigente)

<div class="flex flex-col gap-6">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('memorandos.ver', $memorando)" wire:navigate class="-ms-2 mb-2">{{ $memorando->numero }}</flux:button>
        <flux:heading size="xl" level="1">Reemplazar PDF</flux:heading>
        <flux:subheading>El PDF actual no se borra: queda guardado como versión anterior, no vigente.</flux:subheading>
    </div>

    <form wire:submit="guardar" class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-2">
                <flux:heading>PDF vigente</flux:heading>
                @if ($vigente)
                    <flux:badge size="sm" color="green">Versión {{ $vigente->version }}</flux:badge>
                @endif
            </div>

            @if ($vigente)
                <dl class="mt-4 grid grid-cols-[120px_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                    <dt class="text-zinc-500">Nombre original</dt>
                    <dd class="truncate">{{ $vigente->nombre_original }}</dd>
                    <dt class="text-zinc-500">Páginas</dt>
                    <dd>{{ $vigente->paginas ?? 'No determinado' }} · {{ Archivo::tamanoLegible($vigente->tamano_bytes) }}</dd>
                    <dt class="text-zinc-500">Cargado</dt>
                    <dd>{{ $vigente->created_at->format('d/m/Y H:i') }} por {{ $vigente->subidoPor?->name }}</dd>
                </dl>
                <iframe src="{{ route('memorandos.pdf', $memorando) }}" title="PDF vigente" class="mt-4 h-[520px] w-full rounded-lg border border-zinc-200 dark:border-zinc-700"></iframe>
            @else
                <p class="mt-4 text-sm text-zinc-500">Este memorando no tiene PDF vigente.</p>
            @endif
        </section>

        <div class="flex flex-col gap-4">
            @include('partials.pdf-carga', [
                'titulo' => 'Nuevo PDF',
                'infoPdf' => $this->infoPdf,
                'pdfDuplicadoEn' => $form->pdfDuplicadoEn,
                'etiquetaConfirmar' => 'Reemplazar de todos modos',
            ])

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:textarea wire:model="form.motivo" label="Motivo del reemplazo" rows="2" placeholder="Ej.: el escaneo anterior estaba incompleto o ilegible" />
            </section>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('memorandos.ver', $memorando)" wire:navigate>Cancelar</flux:button>
                <flux:button type="submit" variant="primary" icon="arrow-path" wire:loading.attr="disabled" wire:target="guardar,form.pdf">Reemplazar PDF</flux:button>
            </div>
        </div>
    </form>
</div>
