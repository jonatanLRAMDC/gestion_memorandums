<?php

namespace App\Models;

use App\Enums\Permiso;
use Database\Factories\RolFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    /** @use HasFactory<RolFactory> */
    use HasFactory;

    public const ADMINISTRADOR = 'administrador';

    public const RECEPCION = 'recepcion';

    public const CONSULTA = 'consulta';

    /**
     * @var string
     */
    protected $table = 'roles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'protegido',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'protegido' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * Busca un rol por su slug.
     */
    public static function porSlug(string $slug): ?self
    {
        return static::query()->where('slug', $slug)->first();
    }

    /**
     * @param  Builder<Rol>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<RolPermiso, $this>
     */
    public function permisos(): HasMany
    {
        return $this->hasMany(RolPermiso::class);
    }

    public function esAdministrador(): bool
    {
        return $this->slug === self::ADMINISTRADOR;
    }

    /**
     * Valores de los permisos asignados. El Administrador tiene todos.
     *
     * @return list<string>
     */
    public function listaPermisos(): array
    {
        if ($this->esAdministrador()) {
            return array_map(fn (Permiso $permiso) => $permiso->value, Permiso::cases());
        }

        return $this->permisos->map(fn (RolPermiso $rolPermiso) => $rolPermiso->permiso->value)->values()->all();
    }

    public function tiene(Permiso $permiso): bool
    {
        return $this->esAdministrador() || in_array($permiso->value, $this->listaPermisos(), true);
    }

    /**
     * Reemplaza los permisos del rol.
     *
     * @param  list<string>  $permisos
     */
    public function sincronizarPermisos(array $permisos): void
    {
        $this->permisos()->delete();

        $this->permisos()->createMany(array_map(
            fn (string $permiso) => ['permiso' => $permiso],
            array_values(array_unique($permisos)),
        ));

        $this->unsetRelation('permisos');
    }
}
