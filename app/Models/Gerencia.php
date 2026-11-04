<?php

namespace App\Models;

use Database\Factories\GerenciaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gerencia extends Model
{
    /** @use HasFactory<GerenciaFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'siglas',
        'titular_nombre',
        'titular_cargo',
        'activa',
        'usa_sistema',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'usa_sistema' => 'boolean',
        ];
    }

    /**
     * Gerencia que usa el sistema en la versión 1 (GIS).
     */
    public static function propia(): ?self
    {
        return static::query()
            ->where('siglas', config('memorandos.siglas_gerencia_propia'))
            ->first();
    }

    /**
     * @param  Builder<Gerencia>  $query
     */
    public function scopeActivas(Builder $query): void
    {
        $query->where('activa', true);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Memorando, $this>
     */
    public function memorandosEmitidos(): HasMany
    {
        return $this->hasMany(Memorando::class, 'gerencia_emisora_id');
    }

    /**
     * @return HasMany<Registro, $this>
     */
    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class);
    }
}
