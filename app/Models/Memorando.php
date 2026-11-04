<?php

namespace App\Models;

use Database\Factories\MemorandoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Memorando extends Model
{
    /** @use HasFactory<MemorandoFactory> */
    use HasFactory;

    /**
     * Prefijo institucional del número de memorando.
     */
    public const PREFIJO = 'AMDC';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'numero',
        'siglas',
        'correlativo',
        'correlativo_valor',
        'anio',
        'gerencia_emisora_id',
        'remitente_nombre',
        'remitente_cargo',
        'asunto',
        'fecha_memo',
        'creado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_memo' => 'date',
            'anio' => 'integer',
            'correlativo_valor' => 'integer',
        ];
    }

    /**
     * Arma el número completo a partir de sus partes, p. ej. AMDC-GIS-398-2026.
     */
    public static function armarNumero(string $siglas, string $correlativo, int $anio): string
    {
        return implode('-', [self::PREFIJO, mb_strtoupper(trim($siglas)), trim($correlativo), $anio]);
    }

    /**
     * @return BelongsTo<Gerencia, $this>
     */
    public function gerenciaEmisora(): BelongsTo
    {
        return $this->belongsTo(Gerencia::class, 'gerencia_emisora_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * @return HasMany<MemorandoDestino, $this>
     */
    public function destinos(): HasMany
    {
        return $this->hasMany(MemorandoDestino::class);
    }

    /**
     * @return HasMany<Registro, $this>
     */
    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class);
    }

    /**
     * @return HasMany<Archivo, $this>
     */
    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class);
    }

    /**
     * @return HasOne<Archivo, $this>
     */
    public function archivoVigente(): HasOne
    {
        return $this->hasOne(Archivo::class)->whereNull('registro_id')->where('vigente', true);
    }
}
