<?php

namespace App\Models;

use App\Enums\Rol;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'gerencia_id',
        'rol',
        'activo',
        'debe_cambiar_contrasena',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => Rol::class,
            'activo' => 'boolean',
            'intentos_fallidos' => 'integer',
            'bloqueado_en' => 'datetime',
            'debe_cambiar_contrasena' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Gerencia, $this>
     */
    public function gerencia(): BelongsTo
    {
        return $this->belongsTo(Gerencia::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function esAdministrador(): bool
    {
        return $this->rol === Rol::Administrador;
    }

    public function puedeRegistrarMemos(): bool
    {
        return in_array($this->rol, [Rol::Recepcion, Rol::Administrador], true);
    }

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_en !== null;
    }

    /**
     * Indica si la cuenta puede usar el sistema.
     */
    public function puedeIngresar(): bool
    {
        return $this->activo && ! $this->estaBloqueado();
    }

    /**
     * Suma un intento fallido y bloquea la cuenta al alcanzar el límite configurado.
     *
     * @return bool Verdadero si la cuenta quedó bloqueada con este intento.
     */
    public function registrarIntentoFallido(): bool
    {
        $this->intentos_fallidos++;

        $seBloquea = $this->intentos_fallidos >= config('auth.max_intentos');

        if ($seBloquea) {
            $this->bloqueado_en = now();
        }

        $this->save();

        return $seBloquea;
    }

    public function reiniciarIntentos(): void
    {
        if ($this->intentos_fallidos === 0) {
            return;
        }

        $this->forceFill(['intentos_fallidos' => 0])->save();
    }

    public function desbloquear(): void
    {
        $this->forceFill([
            'intentos_fallidos' => 0,
            'bloqueado_en' => null,
        ])->save();
    }

    /**
     * Estado visible de la cuenta.
     */
    public function estadoCuenta(): string
    {
        return match (true) {
            ! $this->activo => 'Inactivo',
            $this->estaBloqueado() => 'Bloqueado',
            default => 'Activo',
        };
    }
}
