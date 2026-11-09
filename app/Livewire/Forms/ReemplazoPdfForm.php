<?php

namespace App\Livewire\Forms;

use App\Models\Archivo;
use App\Models\Bitacora;
use App\Models\Memorando;
use App\Rules\PdfValido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;
use Throwable;

class ReemplazoPdfForm extends Form
{
    /**
     * @var TemporaryUploadedFile|null
     */
    public $pdf = null;

    public string $motivo = '';

    public bool $confirmarPdfDuplicado = false;

    /**
     * Número del memo que ya tiene cargado el mismo PDF, si lo hay.
     */
    public ?string $pdfDuplicadoEn = null;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'pdf' => MemorandoForm::reglasPdf(),
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'pdf.required' => 'Seleccione el nuevo PDF.',
            'pdf.mimetypes' => 'El archivo debe ser un PDF.',
            'pdf.max' => 'El PDF no debe pesar más de '.round(config('memorandos.pdf_max_kb') / 1024, 1).' MB.',
            'motivo.required' => 'Indique por qué se reemplaza el PDF.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['pdf' => 'PDF', 'motivo' => 'motivo'];
    }

    /**
     * Guarda el nuevo PDF como versión vigente. La versión anterior queda guardada como
     * no vigente; ningún archivo se borra.
     *
     * @throws ValidationException
     */
    public function guardar(Memorando $memorando): Archivo
    {
        $this->motivo = trim($this->motivo);

        $this->validate();

        $anterior = $memorando->archivoVigente;
        $sha256 = hash_file('sha256', $this->pdf->getRealPath());

        if ($anterior && $anterior->sha256 === $sha256) {
            throw ValidationException::withMessages(['form.pdf' => 'Es el mismo archivo que ya está vigente. Seleccione el PDF corregido.']);
        }

        $this->revisarPdfDuplicado($sha256, $memorando);

        $usuario = auth()->user();
        $ruta = now()->format('Y/m').'/'.Str::uuid().'.pdf';
        $disco = Storage::disk(config('memorandos.disco'));

        try {
            $nuevo = DB::transaction(function () use ($memorando, $anterior, $usuario, $sha256, $ruta, $disco): Archivo {
                $anterior?->update(['vigente' => false, 'reemplazado_en' => now()]);

                $disco->putFileAs(dirname($ruta), $this->pdf, basename($ruta));

                return $memorando->archivos()->create([
                    'ruta_interna' => $ruta,
                    'nombre_original' => Str::limit($this->pdf->getClientOriginalName(), 250, ''),
                    'sha256' => $sha256,
                    'tamano_bytes' => $this->pdf->getSize(),
                    'paginas' => PdfValido::contarPaginas($this->pdf->getRealPath()),
                    'version' => (int) $memorando->archivos()->whereNull('registro_id')->max('version') + 1,
                    'vigente' => true,
                    'motivo' => $this->motivo,
                    'subido_por' => $usuario->id,
                ]);
            });
        } catch (Throwable $error) {
            $disco->delete($ruta);

            throw $error;
        }

        Bitacora::registrar('pdf_reemplazado', $memorando,
            antes: $anterior ? ['version' => $anterior->version, 'archivo' => $anterior->nombre_original] : null,
            despues: ['version' => $nuevo->version, 'archivo' => $nuevo->nombre_original, 'motivo' => $nuevo->motivo],
        );

        return $nuevo;
    }

    /**
     * Advierte si el mismo PDF ya está cargado en otro memorando.
     *
     * @throws ValidationException
     */
    protected function revisarPdfDuplicado(string $sha256, Memorando $memorando): void
    {
        $this->pdfDuplicadoEn = Archivo::query()
            ->where('sha256', $sha256)
            ->where('memorando_id', '!=', $memorando->id)
            ->with('memorando:id,numero')
            ->first()?->memorando?->numero;

        if ($this->pdfDuplicadoEn && ! $this->confirmarPdfDuplicado) {
            throw ValidationException::withMessages([
                'form.pdf' => "Este mismo PDF ya está cargado en el memorando {$this->pdfDuplicadoEn}. Revise que sea el archivo correcto o marque “Reemplazar de todos modos”.",
            ]);
        }
    }
}
