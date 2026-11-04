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
     * @return array{paginas: ?int, tamano: string, url: string}|null
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
            'url' => $pdf->temporaryUrl(),
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

        if ($campo === 'fecha_memo' && ($iso = MemorandoForm::fechaIso((string) $valor))) {
            $this->form->anio = (int) substr($iso, 0, 4);
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
            {{-- Número del memorando --}}
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>Número del memorando</flux:heading>
                <div class="mt-4 grid grid-cols-[88px_12px_minmax(0,110px)_12px_minmax(0,130px)_12px_minmax(0,100px)] items-end gap-x-1 gap-y-1 text-sm">
                    <flux:label>Institución</flux:label><span></span>
                    <flux:label>Siglas</flux:label><span></span>
                    <flux:label>Correlativo</flux:label><span></span>
                    <flux:label>Año</flux:label>

                    <flux:input value="AMDC" readonly disabled aria-label="Institución" />
                    <span class="text-center text-zinc-400">-</span>
                    <flux:input wire:model.live.blur="form.siglas" :readonly="$emitido" :disabled="$emitido" class="uppercase" placeholder="GRH" aria-label="Siglas" />
                    <span class="text-center text-zinc-400">-</span>
                    <flux:input wire:model.live.debounce.400ms="form.correlativo" inputmode="numeric" placeholder="398" aria-label="Correlativo" />
                    <span class="text-center text-zinc-400">-</span>
                    <flux:input wire:model.live.blur="form.anio" type="number" aria-label="Año" />
                </div>
                <div class="mt-2 space-y-1">
                    <flux:error name="form.siglas" />
                    <flux:error name="form.correlativo" />
                    <flux:error name="form.anio" />
                </div>
                <p class="mt-3 text-sm text-zinc-500">
                    Número completo: <span class="font-mono font-semibold text-zinc-800 dark:text-white">{{ $form->numeroCompleto() }}</span>
                    @unless ($emitido)
                        · las siglas se proponen desde el catálogo y puede corregirlas según el documento.
                    @endunless
                </p>
            </section>

            {{-- Remitente --}}
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>Remitente (DE)</flux:heading>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @if ($emitido)
                        <div class="md:col-span-2">
                            <flux:input label="Gerencia emisora" :value="$this->propia->nombre" readonly icon:trailing="lock-closed" />
                            <p class="mt-1 text-xs text-zinc-500">Es su gerencia; no se puede cambiar.</p>
                        </div>
                    @else
                        <div class="md:col-span-2">
                        <flux:select wire:model.live="form.gerencia_remitente_id" label="Gerencia remitente">
                            <flux:select.option value="">Seleccione la gerencia que envía el memo</flux:select.option>
                            @foreach ($this->gerencias as $gerencia)
                                <flux:select.option :value="$gerencia->id">{{ $gerencia->siglas ? $gerencia->siglas.' — ' : '' }}{{ $gerencia->nombre }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        </div>
                    @endif
                    <flux:input wire:model="form.remitente_nombre" label="Persona que firma" placeholder="Ing. Nombre Apellido" />
                    <flux:input wire:model="form.remitente_cargo" label="Cargo" placeholder="Gerente" />
                </div>
            </section>

            {{-- Destinatarios --}}
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>Destinatario{{ $emitido ? 's' : '' }} (PARA)</flux:heading>

                @if ($emitido)
                    <div class="mt-4 space-y-3">
                        @foreach ($form->destinos as $indice => $destino)
                            <div wire:key="destino-{{ $indice }}" class="grid gap-3 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_110px_auto] md:items-start">
                                <div>
                                    <flux:select wire:model.live="form.destinos.{{ $indice }}.gerencia_id" aria-label="Gerencia destinataria">
                                        <flux:select.option value="">Gerencia destinataria</flux:select.option>
                                        @foreach ($this->gerencias as $gerencia)
                                            <flux:select.option :value="$gerencia->id">{{ $gerencia->siglas ? $gerencia->siglas.' — ' : '' }}{{ $gerencia->nombre }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="form.destinos.{{ $indice }}.gerencia_id" />
                                </div>
                                <flux:input wire:model="form.destinos.{{ $indice }}.persona_nombre" placeholder="Persona (opcional)" aria-label="Persona destinataria" />
                                <flux:select wire:model="form.destinos.{{ $indice }}.tipo" aria-label="Tipo de destinatario">
                                    <flux:select.option value="para">PARA</flux:select.option>
                                    <flux:select.option value="copia">COPIA</flux:select.option>
                                </flux:select>
                                <flux:button variant="ghost" icon="trash" wire:click="quitarDestino({{ $indice }})" :disabled="count($form->destinos) === 1" aria-label="Quitar destinatario" />
                            </div>
                        @endforeach
                        <flux:error name="form.destinos" />
                        <flux:button size="sm" variant="ghost" icon="plus" wire:click="agregarDestino">Agregar destinatario o copia</flux:button>
                    </div>
                @else
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <flux:input label="Gerencia receptora" :value="$this->propia->nombre" readonly icon:trailing="lock-closed" />
                            <p class="mt-1 text-xs text-zinc-500">Es su gerencia; no se puede cambiar.</p>
                        </div>
                        <flux:input wire:model="form.persona_destinataria" label="Persona destinataria" placeholder="Opcional" />
                    </div>
                @endif
            </section>

            {{-- Contenido y fechas --}}
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>Datos del memo</flux:heading>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <flux:input wire:model="form.asunto" label="Asunto" placeholder="Organización de turnos y rotación de teletrabajo" />
                    </div>
                    <flux:input wire:model.live.blur="form.fecha_memo" x-mask="99/99/9999" inputmode="numeric" placeholder="dd/mm/aaaa" icon:trailing="calendar" label="Fecha del memo" />
                    <flux:input wire:model="form.fecha_movimiento" x-mask="99/99/9999" inputmode="numeric" placeholder="dd/mm/aaaa" icon:trailing="calendar" :label="$emitido ? 'Fecha de envío' : 'Fecha de recepción'" />
                    <div class="md:col-span-2">
                        <flux:textarea wire:model="form.observaciones" label="Observaciones" rows="2" placeholder="Opcional" />
                    </div>
                    <div class="md:col-span-2">
                        <flux:input label="Fecha y hora de registro" value="Se asigna al guardar, con la hora del servidor" readonly disabled icon:trailing="lock-closed" />
                    </div>
                </div>
            </section>
        </div>

        {{-- PDF --}}
        <aside class="flex flex-col gap-4 xl:sticky xl:top-6 xl:self-start">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>PDF escaneado</flux:heading>
                <flux:subheading>Solo PDF, hasta {{ round(config('memorandos.pdf_max_kb') / 1024) }} MB.</flux:subheading>

                <div class="mt-4">
                    <flux:input type="file" wire:model="form.pdf" accept="application/pdf" aria-label="PDF escaneado" />
                    <div wire:loading wire:target="form.pdf" class="mt-2 text-sm text-zinc-500">Cargando archivo…</div>
                    <flux:error name="form.pdf" class="mt-2" />

                    @if ($form->pdfDuplicadoEn)
                        <flux:checkbox wire:model="form.confirmarPdfDuplicado" label="Registrar de todos modos" class="mt-3" />
                    @endif
                </div>

                @if ($this->infoPdf)
                    <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                        <dt class="text-zinc-500">Páginas</dt>
                        <dd class="text-right font-medium">{{ $this->infoPdf['paginas'] ?? 'No determinado' }}</dd>
                        <dt class="text-zinc-500">Tamaño</dt>
                        <dd class="text-right font-medium">{{ $this->infoPdf['tamano'] }}</dd>
                    </dl>
                    <iframe src="{{ $this->infoPdf['url'] }}" title="Vista previa del PDF" class="mt-4 h-[460px] w-full rounded-lg border border-zinc-200 dark:border-zinc-700"></iframe>
                @else
                    <div class="mt-4 flex h-[300px] flex-col items-center justify-center rounded-lg border-2 border-dashed border-zinc-200 text-center text-sm text-zinc-500 dark:border-zinc-700">
                        <flux:icon name="document-arrow-up" class="mb-2 size-8 text-zinc-400" />
                        Aquí verá la vista previa del PDF
                    </div>
                @endif
            </section>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('memorandos.index')" wire:navigate>Cancelar</flux:button>
                <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="guardar,form.pdf">Guardar memorando</flux:button>
            </div>
        </aside>
    </form>
</div>
