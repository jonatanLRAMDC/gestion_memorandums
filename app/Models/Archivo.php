<?php

namespace App\Models;

use Database\Factories\ArchivoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Archivo extends Model
{
    /** @use HasFactory<ArchivoFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'memorando_id',
        'registro_id',
        'ruta_interna',
        'nombre_original',
        'sha256',
        'tamano_bytes',
        'paginas',
        'version',
        'vigente',
        'subido_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'paginas' => 'integer',
            'version' => 'integer',
            'vigente' => 'boolean',
        ];
    }

    /**
     * Tamaño legible: KB por debajo de 1 MB, MB desde ahí.
     */
    public static function tamanoLegible(int $bytes): string
    {
        return $bytes < 1024 * 1024
            ? number_format(max(1, $bytes / 1024), 0).' KB'
            : number_format($bytes / 1024 / 1024, 2).' MB';
    }

    /**
     * @return BelongsTo<Memorando, $this>
     */
    public function memorando(): BelongsTo
    {
        return $this->belongsTo(Memorando::class);
    }

    /**
     * @return BelongsTo<Registro, $this>
     */
    public function registro(): BelongsTo
    {
        return $this->belongsTo(Registro::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
