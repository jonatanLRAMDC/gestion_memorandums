<?php

namespace App\Livewire\Forms;

use App\Enums\TipoDestino;
use App\Enums\TipoRegistro;
use App\Models\Archivo;
use App\Models\Bitacora;
use App\Models\Gerencia;
use App\Models\Memorando;
use App\Models\Registro;
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
     * Fechas en formato aaaa-mm-dd, como las entrega el selector de fecha.
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
     * Memorando que se está editando. Solo vive durante la petición que guarda los cambios.
     */
    protected ?Memorando $enEdicion = null;

    /**
     * Prepara el formulario según el tipo de registro y la gerencia del usuario.
     */
    public function iniciar(TipoRegistro $tipo, Gerencia $propia): void
    {
        $this->tipo = $tipo->value;
        $this->anio = (int) now()->year;
        $this->fecha_movimiento = now()->toDateString();

        if ($tipo === TipoRegistro::Emitido) {
            $this->siglas = (string) $propia->siglas;
            $this->remitente_nombre = (string) $propia->titular_nombre;
            $this->remitente_cargo = (string) $propia->titular_cargo;
            $this->destinos = [$this->destinoVacio()];
        } else {
            $this->persona_destinataria = (string) $propia->titular_nombre;
        }
    }

    /**
     * Carga los datos de un memorando ya registrado para corregirlos.
     */
    public function cargar(Memorando $memorando, Registro $registro): void
    {
        $memorando->loadMissing('destinos');

        $this->tipo = $registro->tipo->value;
        $this->siglas = $memorando->siglas;
        $this->correlativo = $memorando->correlativo;
        $this->anio = $memorando->anio;
        $this->remitente_nombre = (string) $memorando->remitente_nombre;
        $this->remitente_cargo = (string) $memorando->remitente_cargo;
        $this->asunto = $memorando->asunto;
        $this->fecha_memo = $memorando->fecha_memo->toDateString();
        $this->fecha_movimiento = $registro->fecha_movimiento->toDateString();
        $this->observaciones = (string) $registro->observaciones;

        if ($this->esEmitido()) {
            $this->destinos = $memorando->destinos
                ->map(fn ($destino) => [
                    'gerencia_id' => $destino->gerencia_id,
                    'persona_nombre' => (string) $destino->persona_nombre,
                    'tipo' => $destino->tipo->value,
                ])
                ->values()
                ->all();
        } else {
            $this->gerencia_remitente_id = $memorando->gerencia_emisora_id;
            $this->persona_destinataria = (string) $memorando->destinos->firstWhere('gerencia_id', $registro->gerencia_id)?->persona_nombre;
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

        // Al editar se aceptan también las gerencias que el memo ya tenía, aunque hoy estén inactivas.
        $yaAsignadas = $this->enEdicion
            ? [$this->enEdicion->gerencia_emisora_id, ...$this->enEdicion->destinos->pluck('gerencia_id')]
            : [];

        $gerenciaActivaExterna = [
            Rule::exists('gerencias', 'id')->where(fn ($query) => $query->where('activa', true)->orWhereIn('id', $yaAsignadas)),
            Rule::notIn([$propiaId]),
        ];

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
            'fecha_memo' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'fecha_movimiento' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_memo', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'pdf' => $this->enEdicion ? ['exclude'] : self::reglasPdf(),
        ];
    }

    /**
     * Reglas del archivo PDF, compartidas con el reemplazo de versiones.
     *
     * @return list<mixed>
     */
    public static function reglasPdf(): array
    {
        return ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('memorandos.pdf_max_kb'), new PdfValido];
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
            'fecha_memo.date_format' => 'Seleccione una fecha válida en el calendario.',
            'fecha_movimiento.date_format' => 'Seleccione una fecha válida en el calendario.',
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
                    'fecha_memo' => $this->fecha_memo,
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
                    'fecha_movimiento' => $this->fecha_movimiento,
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
     * Guarda las correcciones del memorando y de su registro. Anota en la bitácora solo los
     * campos que cambiaron, con su valor anterior y el nuevo.
     *
     * @return list<string> Campos que cambiaron.
     *
     * @throws ValidationException
     */
    public function actualizar(Memorando $memorando, Registro $registro): array
    {
        $this->enEdicion = $memorando->load(['destinos.gerencia', 'gerenciaEmisora']);

        if ($this->esEmitido()) {
            $this->siglas = $memorando->siglas;
        }

        $this->siglas = mb_strtoupper(trim($this->siglas));
        $this->correlativo = trim($this->correlativo);

        $this->validate();

        $correlativoValor = (int) $this->correlativo;
        $numero = Memorando::armarNumero($this->siglas, $this->correlativo, (int) $this->anio);

        $this->asegurarQueNoExiste($correlativoValor, $numero);

        $antes = self::instantanea($memorando, $registro);

        try {
            DB::transaction(function () use ($memorando, $registro, $correlativoValor, $numero): void {
                $memorando->update([
                    'numero' => $numero,
                    'siglas' => $this->siglas,
                    'correlativo' => $this->correlativo,
                    'correlativo_valor' => $correlativoValor,
                    'anio' => $this->anio,
                    'gerencia_emisora_id' => $this->esEmitido() ? $memorando->gerencia_emisora_id : $this->gerencia_remitente_id,
                    'remitente_nombre' => $this->vacioANulo($this->remitente_nombre),
                    'remitente_cargo' => $this->vacioANulo($this->remitente_cargo),
                    'asunto' => trim($this->asunto),
                    'fecha_memo' => $this->fecha_memo,
                ]);

                if ($this->esEmitido()) {
                    $memorando->destinos()->delete();

                    foreach ($this->destinos as $destino) {
                        $memorando->destinos()->create([
                            'gerencia_id' => $destino['gerencia_id'],
                            'persona_nombre' => $this->vacioANulo($destino['persona_nombre']),
                            'tipo' => $destino['tipo'],
                        ]);
                    }
                } else {
                    $memorando->destinos()
                        ->where('gerencia_id', $registro->gerencia_id)
                        ->update(['persona_nombre' => $this->vacioANulo($this->persona_destinataria)]);
                }

                $registro->update([
                    'fecha_movimiento' => $this->fecha_movimiento,
                    'observaciones' => $this->vacioANulo($this->observaciones),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['form.correlativo' => "El memorando {$numero} ya está registrado."]);
        }

        $despues = self::instantanea($memorando->refresh(), $registro->refresh());
        $cambios = array_keys(array_diff_assoc(array_map('strval', $despues), array_map('strval', $antes)));

        if ($cambios !== []) {
            Bitacora::registrar(
                'memorando_editado',
                $memorando,
                antes: array_intersect_key($antes, array_flip($cambios)),
                despues: array_intersect_key($despues, array_flip($cambios)),
            );
        }

        return $cambios;
    }

    /**
     * Valores legibles del memorando y su registro, para comparar antes y después de editar.
     *
     * @return array<string, string|null>
     */
    public static function instantanea(Memorando $memorando, Registro $registro): array
    {
        $memorando->loadMissing(['gerenciaEmisora', 'destinos.gerencia']);

        $destinos = fn (TipoDestino $tipo): ?string => $memorando->destinos
            ->where('tipo', $tipo)
            ->sortBy('id')
            ->map(fn ($destino) => $destino->gerencia->nombre.($destino->persona_nombre ? " ({$destino->persona_nombre})" : ''))
            ->join('; ') ?: null;

        return [
            'numero' => $memorando->numero,
            'gerencia_emisora' => $memorando->gerenciaEmisora?->nombre,
            'remitente_nombre' => $memorando->remitente_nombre,
            'remitente_cargo' => $memorando->remitente_cargo,
            'para' => $destinos(TipoDestino::Para),
            'copia' => $destinos(TipoDestino::Copia),
            'asunto' => $memorando->asunto,
            'fecha_memo' => $memorando->fecha_memo->format('d/m/Y'),
            'fecha_movimiento' => $registro->fecha_movimiento->format('d/m/Y'),
            'observaciones' => $registro->observaciones,
        ];
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
            ->when($this->enEdicion, fn ($query, Memorando $memorando) => $query->whereKeyNot($memorando->id))
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
     * Fecha aaaa-mm-dd escrita en letras, p. ej. "05 de noviembre de 2026".
     * Devuelve una cadena vacía si la fecha no es válida.
     */
    public static function fechaEnLetras(?string $fecha): string
    {
        $fecha = trim((string) $fecha);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return '';
        }

        $resultado = Carbon::createFromFormat('!Y-m-d', $fecha);

        if (! $resultado || $resultado->toDateString() !== $fecha) {
            return '';
        }

        return $resultado->locale('es')->translatedFormat('d \d\e F \d\e Y');
    }

    protected function vacioANulo(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
