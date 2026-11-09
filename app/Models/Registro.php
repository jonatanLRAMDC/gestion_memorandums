<?php

namespace App\Models;

use App\Enums\TipoRegistro;
use Database\Factories\RegistroFactory;
use Illuminate\Database\Eloquent\Builder;
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
     * Columnas por las que se puede ordenar el listado y su nombre visible.
     *
     * @var array<string, string>
     */
    public const ORDENES = [
        'registrado' => 'Fecha de registro',
        'numero' => 'Número',
        'fecha_memo' => 'Fecha del memo',
        'movimiento' => 'Fecha de envío o recepción',
    ];

    /**
     * Registros de una gerencia.
     *
     * @param  Builder<Registro>  $query
     */
    public function scopeDeGerencia(Builder $query, ?int $gerenciaId): void
    {
        $query->where('registros.gerencia_id', $gerenciaId);
    }

    /**
     * Aplica los filtros del listado. Los valores vacíos o inválidos se ignoran.
     *
     * @param  Builder<Registro>  $query
     * @param  array{tipo?: string, buscar?: string, gerencia?: string|int, desde?: string, hasta?: string}  $filtros
     */
    public function scopeFiltrar(Builder $query, array $filtros): void
    {
        $tipo = TipoRegistro::tryFrom((string) ($filtros['tipo'] ?? ''));
        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $gerenciaId = (string) ($filtros['gerencia'] ?? '');
        $desde = self::fechaValida($filtros['desde'] ?? null);
        $hasta = self::fechaValida($filtros['hasta'] ?? null);

        $query
            ->when($tipo, fn (Builder $query) => $query->where('registros.tipo', $tipo))
            ->when($buscar !== '', fn (Builder $query) => $query->whereHas('memorando', fn (Builder $memo) => $memo
                ->where('numero', 'like', "%{$buscar}%")
                ->orWhere('asunto', 'like', "%{$buscar}%")
                ->orWhere('remitente_nombre', 'like', "%{$buscar}%")))
            ->when(ctype_digit($gerenciaId), fn (Builder $query) => $query->whereHas('memorando', fn (Builder $memo) => $memo
                ->where('gerencia_emisora_id', (int) $gerenciaId)
                ->orWhereHas('destinos', fn (Builder $destino) => $destino->where('gerencia_id', (int) $gerenciaId))))
            ->when($desde, fn (Builder $query) => $query->whereHas('memorando', fn (Builder $memo) => $memo->whereDate('fecha_memo', '>=', $desde)))
            ->when($hasta, fn (Builder $query) => $query->whereHas('memorando', fn (Builder $memo) => $memo->whereDate('fecha_memo', '<=', $hasta)));
    }

    /**
     * Ordena el listado. Por omisión, del registro más reciente al más antiguo.
     *
     * @param  Builder<Registro>  $query
     */
    public function scopeOrdenar(Builder $query, ?string $orden = 'registrado', ?string $direccion = 'desc'): void
    {
        $direccion = $direccion === 'asc' ? 'asc' : 'desc';
        $delMemo = fn (string $columna) => Memorando::query()->select($columna)->whereColumn('memorandos.id', 'registros.memorando_id');

        match ($orden) {
            'numero' => $query
                ->orderBy($delMemo('anio'), $direccion)
                ->orderBy($delMemo('siglas'), $direccion)
                ->orderBy($delMemo('correlativo_valor'), $direccion),
            'fecha_memo' => $query->orderBy($delMemo('fecha_memo'), $direccion),
            'movimiento' => $query->orderBy('registros.fecha_movimiento', $direccion),
            default => $query->orderBy('registros.created_at', $direccion),
        };

        $query->orderBy('registros.id', $direccion);
    }

    /**
     * Devuelve la fecha aaaa-mm-dd si es válida; si no, null.
     */
    public static function fechaValida(?string $fecha): ?string
    {
        $fecha = trim((string) $fecha);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && strtotime($fecha) ? $fecha : null;
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
