{{--
    Campos del memorando, compartidos por el registro y la edición.
    Variables: $form, $emitido, $gerencias, $propia, $edicion y, al editar, $registradoEn.
--}}
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
                <flux:input label="Gerencia emisora" :value="$propia->nombre" readonly icon:trailing="lock-closed" />
                <p class="mt-1 text-xs text-zinc-500">Es su gerencia; no se puede cambiar.</p>
            </div>
        @else
            <div class="md:col-span-2">
            <flux:select wire:model.live="form.gerencia_remitente_id" label="Gerencia remitente">
                <flux:select.option value="">Seleccione la gerencia que envía el memo</flux:select.option>
                @foreach ($gerencias as $gerencia)
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
                            @foreach ($gerencias as $gerencia)
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
                <flux:input label="Gerencia receptora" :value="$propia->nombre" readonly icon:trailing="lock-closed" />
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
        <div>
            <flux:input wire:model.live="form.fecha_memo" type="date" label="Fecha del memo" :max="now()->toDateString()" />
            <p class="mt-1 min-h-5 text-sm text-zinc-500" data-fecha-en-letras>{{ \App\Livewire\Forms\MemorandoForm::fechaEnLetras($form->fecha_memo) }}</p>
        </div>
        <div>
            <flux:input wire:model.live="form.fecha_movimiento" type="date" :label="$emitido ? 'Fecha de envío' : 'Fecha de recepción'" :max="now()->toDateString()" />
            <p class="mt-1 min-h-5 text-sm text-zinc-500" data-fecha-en-letras>{{ \App\Livewire\Forms\MemorandoForm::fechaEnLetras($form->fecha_movimiento) }}</p>
        </div>
        <div class="md:col-span-2">
            <flux:textarea wire:model="form.observaciones" label="Observaciones" rows="2" placeholder="Opcional" />
        </div>
        <div class="md:col-span-2">
            <flux:input label="Fecha y hora de registro" :value="$edicion ? $registradoEn : 'Se asigna al guardar, con la hora del servidor'" readonly disabled icon:trailing="lock-closed" />
        </div>
    </div>
</section>
