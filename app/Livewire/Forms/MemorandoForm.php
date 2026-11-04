<?php

namespace App\Livewire\Forms;

use App\Enums\TipoDestino;
use App\Enums\TipoRegistro;
use App\Models\Archivo;
use App\Models\Bitacora;
use App\Models\Gerencia;
use App\Models\Memorando;
use App\Rules\PdfValido;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;
use Throwable;

class MemorandoForm extends Form
{
    public string $tipo = 'emitido';

    public string $siglas = '';

    public string $correlativo = '';

    public ?int $anio = null;

    public ?int $gerencia_remitente_id = null;

    public string $remitente_nombre = '';

    public string $remitente_cargo = '';

    /**
     * Destinatarios de un memo emitido.
     *
     * @var list<array{gerencia_id: int|string|null, persona_nombre: string, tipo: string}>
     */
    public array $destinos = [];

    /**
     * Persona de la gerencia propia a quien va dirigido un memo recibido.
     */
    public string $persona_destinataria = '';

    public string $asunto = '';

    /**
     * Fechas en formato dd/mm/aaaa, tal como se escriben en el formulario.
     */
    public string $fecha_memo = '';

    public string $fecha_movimiento = '';

    public string $observaciones = '';

    /**
     * @var TemporaryUploadedFile|null
     */
    public $pdf = null;

    public bool $confirmarPdfDuplicado = false;

    /**
     * Número del memo que ya tiene cargado el mismo PDF, si lo hay.
     */
    public ?string $pdfDuplicadoEn = null;

    /**
     * Prepara el formulario según el tipo de registro y la gerencia del usuario.
     */
    public function iniciar(TipoRegistro $tipo, Gerencia $propia): void
    {
        $this->tipo = $tipo->value;
        $this->anio = (int) now()->year;
        $this->fecha_movimiento = now()->format('d/m/Y');

        if ($tipo === TipoRegistro::Emitido) {
            $this->siglas = (string) $propia->siglas;
            $this->remitente_nombre = (string) $propia->titular_nombre;
            $this->remitente_cargo = (string) $propia->titular_cargo;
            $this->destinos = [$this->destinoVacio()];
        } else {
            $this->persona_destinataria = (string) $propia->titular_nombre;
        }
    }

    public function esEmitido(): bool
    {
        return $this->tipo === TipoRegistro::Emitido->value;
    }

    /**
     * @return array{gerencia_id: null, persona_nombre: string, tipo: string}
     */
    public function destinoVacio(): array
    {
        return ['gerencia_id' => null, 'persona_nombre' => '', 'tipo' => TipoDestino::Para->value];
    }

    public function agregarDestino(): void
    {
        $this->destinos[] = [...$this->destinoVacio(), 'tipo' => TipoDestino::Copia->value];
    }

    public function quitarDestino(int $indice): void
    {
        unset($this->destinos[$indice]);
        $this->destinos = array_values($this->destinos);
    }

    /**
     * Número completo con el formato AMDC-SIGLAS-correlativo-año.
     */
    public function numeroCompleto(): string
    {
        return Memorando::armarNumero(
            $this->siglas !== '' ? $this->siglas : 'SIGLAS',
            $this->correlativo !== '' ? $this->correlativo : '000',
            $this->anio ?: (int) now()->year,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $propiaId = auth()->user()->gerencia_id;
        $gerenciaActivaExterna = [Rule::exists('gerencias', 'id')->where('activa', true), Rule::notIn([$propiaId])];

        return [
            'tipo' => ['required', Rule::enum(TipoRegistro::class)],
            'siglas' => ['required', 'string', 'max:20', 'regex:/^[A-ZÁÉÍÓÚÑ]+$/u'],
            'correlativo' => ['required', 'regex:/^\d{1,10}$/'],
            'anio' => ['required', 'integer', 'between:2000,'.(now()->year + 1)],
            'gerencia_remitente_id' => $this->esEmitido() ? ['nullable'] : ['required', ...$gerenciaActivaExterna],
            'remitente_nombre' => ['nullable', 'string', 'max:150'],
            'remitente_cargo' => ['nullable', 'string', 'max:150'],
            'destinos' => $this->esEmitido() ? ['required', 'array', 'min:1'] : ['array'],
            'destinos.*.gerencia_id' => ['required', 'distinct', ...$gerenciaActivaExterna],
            'destinos.*.persona_nombre' => ['nullable', 'string', 'max:150'],
            'destinos.*.tipo' => ['required', Rule::enum(TipoDestino::class)],
            'persona_destinataria' => ['nullable', 'string', 'max:150'],
            'asunto' => ['required', 'string', 'max:255'],
            'fecha_memo' => ['required', 'date_format:d/m/Y', 'before_or_equal:today'],
            'fecha_movimiento' => ['required', 'date_format:d/m/Y', 'after_or_equal:fecha_memo', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('memorandos.pdf_max_kb'), new PdfValido],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'siglas.regex' => 'Las siglas solo deben contener letras en mayúsculas.',
            'correlativo.regex' => 'El correlativo solo admite números (hasta 10 dígitos).',
            'gerencia_remitente_id.not_in' => 'La gerencia remitente debe ser otra gerencia.',
            'destinos.required' => 'Agregue al menos una gerencia destinataria.',
            'destinos.*.gerencia_id.required' => 'Seleccione la gerencia destinataria.',
            'destinos.*.gerencia_id.distinct' => 'Esta gerencia ya está en la lista.',
            'destinos.*.gerencia_id.not_in' => 'Un memo emitido no puede dirigirse a la propia gerencia.',
            'fecha_memo.date_format' => 'Escriba la fecha como dd/mm/aaaa.',
            'fecha_movimiento.date_format' => 'Escriba la fecha como dd/mm/aaaa.',
            'fecha_memo.before_or_equal' => 'La fecha del memo no puede ser futura.',
            'fecha_movimiento.after_or_equal' => 'La fecha no puede ser anterior a la fecha del memo.',
            'fecha_movimiento.before_or_equal' => 'La fecha no puede ser futura.',
            'pdf.mimetypes' => 'El archivo debe ser un PDF.',
            'pdf.max' => 'El PDF no debe pesar más de '.round(config('memorandos.pdf_max_kb') / 1024, 1).' MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'siglas' => 'siglas',
            'correlativo' => 'correlativo',
            'anio' => 'año',
            'gerencia_remitente_id' => 'gerencia remitente',
            'asunto' => 'asunto',
            'fecha_memo' => 'fecha del memo',
            'fecha_movimiento' => $this->esEmitido() ? 'fecha de envío' : 'fecha de recepción',
            'pdf' => 'PDF',
        ];
    }

    /**
     * Valida y registra el memorando, sus destinatarios, el registro de la gerencia y el PDF
     * en una sola operación. Si algo falla no queda nada guardado.
     *
     * @throws ValidationException
     */
    public function guardar(): Memorando
    {
        $usuario = auth()->user();
        $propia = $usuario->gerencia;

        if ($this->esEmitido()) {
            $this->siglas = (string) $propia->siglas;
        }

        $this->siglas = mb_strtoupper(trim($this->siglas));
        $this->correlativo = trim($this->correlativo);

        $this->validate();

        $correlativoValor = (int) $this->correlativo;
        $numero = Memorando::armarNumero($this->siglas, $this->correlativo, (int) $this->anio);

        $this->asegurarQueNoExiste($correlativoValor, $numero);

        $sha256 = hash_file('sha256', $this->pdf->getRealPath());
        $this->revisarPdfDuplicado($sha256);

        $ruta = now()->format('Y/m').'/'.Str::uuid().'.pdf';
        $disco = Storage::disk(config('memorandos.disco'));

        try {
            $memorando = DB::transaction(function () use ($usuario, $propia, $correlativoValor, $numero, $sha256, $ruta, $disco): Memorando {
                $memorando = Memorando::create([
                    'numero' => $numero,
                    'siglas' => $this->siglas,
                    'correlativo' => $this->correlativo,
                    'correlativo_valor' => $correlativoValor,
                    'anio' => $this->anio,
                    'gerencia_emisora_id' => $this->esEmitido() ? $propia->id : $this->gerencia_remitente_id,
                    'remitente_nombre' => $this->vacioANulo($this->remitente_nombre),
                    'remitente_cargo' => $this->vacioANulo($this->remitente_cargo),
                    'asunto' => trim($this->asunto),
                    'fecha_memo' => self::fechaIso($this->fecha_memo),
                    'creado_por' => $usuario->id,
                ]);

                $destinos = $this->esEmitido()
                    ? $this->destinos
                    : [['gerencia_id' => $propia->id, 'persona_nombre' => $this->persona_destinataria, 'tipo' => TipoDestino::Para->value]];

                foreach ($destinos as $destino) {
                    $memorando->destinos()->create([
                        'gerencia_id' => $destino['gerencia_id'],
                        'persona_nombre' => $this->vacioANulo($destino['persona_nombre']),
                        'tipo' => $destino['tipo'],
                    ]);
                }

                $memorando->registros()->create([
                    'gerencia_id' => $propia->id,
                    'tipo' => $this->tipo,
                    'fecha_movimiento' => self::fechaIso($this->fecha_movimiento),
                    'observaciones' => $this->vacioANulo($this->observaciones),
                    'usuario_id' => $usuario->id,
                ]);

                $disco->putFileAs(dirname($ruta), $this->pdf, basename($ruta));

                $memorando->archivos()->create([
                    'ruta_interna' => $ruta,
                    'nombre_original' => Str::limit($this->pdf->getClientOriginalName(), 250, ''),
                    'sha256' => $sha256,
                    'tamano_bytes' => $this->pdf->getSize(),
                    'paginas' => PdfValido::contarPaginas($this->pdf->getRealPath()),
                    'version' => 1,
                    'vigente' => true,
                    'subido_por' => $usuario->id,
                ]);

                return $memorando;
            });
        } catch (UniqueConstraintViolationException) {
            $disco->delete($ruta);

            throw ValidationException::withMessages(['form.correlativo' => "El memorando {$numero} ya está registrado."]);
        } catch (Throwable $error) {
            $disco->delete($ruta);

            throw $error;
        }

        Bitacora::registrar('memorando_registrado', $memorando, despues: [
            'numero' => $memorando->numero,
            'tipo' => $this->tipo,
            'archivo' => $ruta,
        ]);

        return $memorando;
    }

    /**
     * @throws ValidationException
     */
    protected function asegurarQueNoExiste(int $correlativoValor, string $numero): void
    {
        $existe = Memorando::query()
            ->where('siglas', $this->siglas)
            ->where('correlativo_valor', $correlativoValor)
            ->where('anio', $this->anio)
            ->value('numero');

        if ($existe) {
            throw ValidationException::withMessages([
                'form.correlativo' => "El memorando {$existe} ya está registrado. No se puede registrar dos veces.",
            ]);
        }
    }

    /**
     * Advierte si el mismo PDF ya está cargado en otro memorando.
     *
     * @throws ValidationException
     */
    protected function revisarPdfDuplicado(string $sha256): void
    {
        $this->pdfDuplicadoEn = Archivo::query()
            ->where('sha256', $sha256)
            ->with('memorando:id,numero')
            ->first()?->memorando?->numero;

        if ($this->pdfDuplicadoEn && ! $this->confirmarPdfDuplicado) {
            throw ValidationException::withMessages([
                'form.pdf' => "Este mismo PDF ya está cargado en el memorando {$this->pdfDuplicadoEn}. Revise que sea el archivo correcto o marque “Registrar de todos modos”.",
            ]);
        }
    }

    /**
     * Convierte una fecha dd/mm/aaaa al formato de la base de datos (aaaa-mm-dd).
     * Devuelve null si el texto no es una fecha válida.
     */
    public static function fechaIso(?string $fecha): ?string
    {
        $fecha = trim((string) $fecha);

        if (! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha)) {
            return null;
        }

        $resultado = Carbon::createFromFormat('!d/m/Y', $fecha);

        return $resultado && $resultado->format('d/m/Y') === $fecha ? $resultado->toDateString() : null;
    }

    protected function vacioANulo(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
