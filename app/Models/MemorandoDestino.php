<?php

namespace App\Models;

use App\Enums\TipoDestino;
use Database\Factories\MemorandoDestinoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorandoDestino extends Model
{
    /** @use HasFactory<MemorandoDestinoFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'memorando_id',
        'gerencia_id',
        'persona_nombre',
        'tipo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoDestino::class,
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
}
