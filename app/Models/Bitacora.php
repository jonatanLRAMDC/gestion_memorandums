<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class Bitacora extends Model
{
    /**
     * La bitácora solo registra la fecha de creación.
     */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'accion',
        'entidad',
        'entidad_id',
        'antes',
        'despues',
        'ip',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'despues' => 'array',
        ];
    }

    /**
     * Impide editar o borrar entradas de la bitácora desde la aplicación.
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('La bitácora no se puede modificar.'));
        static::deleting(fn () => throw new LogicException('La bitácora no se puede borrar.'));
    }

    /**
     * Registra una acción en la bitácora.
     *
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    public static function registrar(
        string $accion,
        ?Model $entidad = null,
        ?array $antes = null,
        ?array $despues = null,
        ?int $usuarioId = null,
    ): self {
        $request = request();

        return static::create([
            'usuario_id' => $usuarioId ?? auth()->id(),
            'accion' => $accion,
            'entidad' => $entidad ? class_basename($entidad) : null,
            'entidad_id' => $entidad?->getKey(),
            'antes' => $antes,
            'despues' => $despues,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 250, '') : null,
        ]);
    }

    /**
     * Nombres visibles de las acciones registradas.
     *
     * @var array<string, string>
     */
    public const ACCIONES = [
        'inicio_sesion' => 'Inicio de sesión',
        'cierre_sesion' => 'Cierre de sesión',
        'ingreso_fallido' => 'Intento de ingreso fallido',
        'ingreso_rechazado' => 'Ingreso rechazado (cuenta bloqueada o inactiva)',
        'cuenta_bloqueada' => 'Cuenta bloqueada por intentos',
        'cambio_contrasena' => 'Cambio de contraseña',
        'edicion_perfil' => 'Edición de perfil',
        'gerencia_creada' => 'Gerencia creada',
        'gerencia_editada' => 'Gerencia editada',
        'gerencia_activada' => 'Gerencia activada',
        'gerencia_desactivada' => 'Gerencia desactivada',
        'usuario_creado' => 'Usuario creado',
        'usuario_editado' => 'Usuario editado',
        'usuario_activado' => 'Usuario activado',
        'usuario_desactivado' => 'Usuario desactivado',
        'usuario_desbloqueado' => 'Usuario desbloqueado',
        'contrasena_restablecida' => 'Contraseña restablecida por el Administrador',
        'rol_creado' => 'Rol creado',
        'rol_editado' => 'Rol editado',
        'rol_activado' => 'Rol activado',
        'rol_desactivado' => 'Rol desactivado',
        'memorando_registrado' => 'Memorando registrado',
        'memorando_editado' => 'Memorando editado',
        'pdf_reemplazado' => 'PDF reemplazado por una nueva versión',
        'listado_exportado' => 'Listado exportado',
        'reporte_exportado' => 'Reporte exportado',
        'documento_consultado' => 'PDF consultado',
        'documento_descargado' => 'PDF descargado',
    ];

    /**
     * Nombres visibles de las entidades registradas.
     *
     * @var array<string, string>
     */
    public const ENTIDADES = [
        'User' => 'Usuario',
        'Rol' => 'Rol',
        'Gerencia' => 'Gerencia',
        'Memorando' => 'Memorando',
        'Registro' => 'Registro',
        'Archivo' => 'Archivo',
    ];

    /**
     * Nombres visibles de los campos que se guardan en "antes" y "después".
     *
     * @var array<string, string>
     */
    public const CAMPOS = [
        'numero' => 'Número',
        'gerencia_emisora' => 'Gerencia remitente',
        'remitente_nombre' => 'Persona que firma',
        'remitente_cargo' => 'Cargo',
        'para' => 'Para',
        'copia' => 'Con copia a',
        'asunto' => 'Asunto',
        'fecha_memo' => 'Fecha del memo',
        'fecha_movimiento' => 'Fecha de envío o recepción',
        'observaciones' => 'Observaciones',
        'version' => 'Versión del PDF',
        'motivo' => 'Motivo',
        'archivo' => 'Archivo',
        'archivo_id' => 'Id. del archivo',
        'formato' => 'Formato',
        'filtros' => 'Filtros',
        'registros' => 'Registros',
    ];

    /**
     * Nombre visible de un campo de "antes" o "después".
     */
    public static function etiquetaCampo(string $campo): string
    {
        return self::CAMPOS[$campo] ?? Str::ucfirst(str_replace('_', ' ', $campo));
    }

    /**
     * Cambios de la entrada como filas campo / antes / después.
     *
     * @return list<array{campo: string, antes: string, despues: string}>
     */
    public function cambios(): array
    {
        $antes = $this->antes ?? [];
        $despues = $this->despues ?? [];

        return array_values(array_map(fn (string $campo) => [
            'campo' => self::etiquetaCampo($campo),
            'antes' => self::valorLegible($antes[$campo] ?? null),
            'despues' => self::valorLegible($despues[$campo] ?? null),
        ], array_unique([...array_keys($antes), ...array_keys($despues)])));
    }

    protected static function valorLegible(mixed $valor): string
    {
        return match (true) {
            $valor === null, $valor === '' => '—',
            is_bool($valor) => $valor ? 'Sí' : 'No',
            is_array($valor) => collect($valor)->map(fn ($item, $clave) => is_string($clave) ? "{$clave}: ".self::valorLegible($item) : self::valorLegible($item))->join(', '),
            default => (string) $valor,
        };
    }

    public function etiquetaEntidad(): ?string
    {
        if (! $this->entidad) {
            return null;
        }

        return (self::ENTIDADES[$this->entidad] ?? $this->entidad).' #'.$this->entidad_id;
    }

    public function etiquetaAccion(): string
    {
        return self::ACCIONES[$this->accion] ?? $this->accion;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
