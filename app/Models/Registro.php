<?php

namespace App\Models;

use App\Enums\TipoRegistro;
use Database\Factories\RegistroFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registro extends Model
{
    /** @use HasFactory<RegistroFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'memorando_id',
        'gerencia_id',
        'tipo',
        'fecha_movimiento',
        'observaciones',
        'usuario_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoRegistro::class,
            'fecha_movimiento' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Memorando, $this>
     */
    public function memorando(): BelongsTo
    {
        return $this->belongsTo(Memorando::class);
    }

    /**
     * @return BelongsTo<Gerencia, $this>
     */
    public function gerencia(): BelongsTo
    {
        return $this->belongsTo(Gerencia::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * @return HasMany<Archivo, $this>
     */
    public function acuses(): HasMany
    {
        return $this->hasMany(Archivo::class);
    }
}
