<?php

namespace App\Models;

use App\Enums\Permiso;
use Illuminate\Database\Eloquent\Model;

class RolPermiso extends Model
{
    /**
     * @var string
     */
    protected $table = 'permiso_rol';

    /**
     * @var string|null
     */
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rol_id',
        'permiso',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permiso' => Permiso::class,
        ];
    }
}
