{{--
    Carga de un PDF con vista previa armada en el navegador (no se pide al servidor).
    Variables: $titulo, $infoPdf, $pdfDuplicadoEn y $etiquetaConfirmar. El campo es form.pdf.
--}}
<section
    class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"
    x-data="{
        vista: null,
        mostrar(evento) {
            if (evento.target.type !== 'file') return
            if (this.vista) URL.revokeObjectURL(this.vista)
            let archivo = evento.target.files[0]
            this.vista = archivo && archivo.type === 'application/pdf' ? URL.createObjectURL(archivo) : null
        },
    }"
    x-on:change="mostrar($event)"
>
    <flux:heading>{{ $titulo }}</flux:heading>
    <flux:subheading>Solo PDF, hasta {{ round(config('memorandos.pdf_max_kb') / 1024) }} MB.</flux:subheading>

    <div class="mt-4">
        <flux:input type="file" wire:model="form.pdf" accept="application/pdf" aria-label="PDF escaneado" />
        <div wire:loading wire:target="form.pdf" class="mt-2 text-sm text-zinc-500">Cargando archivo…</div>
        <flux:error name="form.pdf" class="mt-2" />

        @if ($pdfDuplicadoEn)
            <flux:checkbox wire:model="form.confirmarPdfDuplicado" :label="$etiquetaConfirmar" class="mt-3" />
        @endif
    </div>

    @if ($infoPdf)
        <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
            <dt class="text-zinc-500">Páginas</dt>
            <dd class="text-right font-medium">{{ $infoPdf['paginas'] ?? 'No determinado' }}</dd>
            <dt class="text-zinc-500">Tamaño</dt>
            <dd class="text-right font-medium">{{ $infoPdf['tamano'] }}</dd>
        </dl>
    @endif

    {{-- La vista previa se arma en el navegador con el archivo elegido; no se pide al servidor. --}}
    <div wire:ignore class="mt-4">
        <iframe x-show="vista" x-cloak :src="vista" title="Vista previa del PDF" class="h-[520px] w-full rounded-lg border border-zinc-200 dark:border-zinc-700"></iframe>
        <div x-show="! vista" class="flex h-[300px] flex-col items-center justify-center rounded-lg border-2 border-dashed border-zinc-200 text-center text-sm text-zinc-500 dark:border-zinc-700">
            <flux:icon name="document-arrow-up" class="mb-2 size-8 text-zinc-400" />
            Aquí verá la vista previa del PDF
        </div>
    </div>
</section>
