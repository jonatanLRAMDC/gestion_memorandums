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
    ];

    /**
     * Nombres visibles de las entidades registradas.
     *
     * @var array<string, string>
     */
    public const ENTIDADES = [
        'User' => 'Usuario',
        'Gerencia' => 'Gerencia',
        'Memorando' => 'Memorando',
        'Registro' => 'Registro',
        'Archivo' => 'Archivo',
    ];

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
