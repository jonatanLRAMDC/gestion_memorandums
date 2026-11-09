<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permisos nuevos de la fase 3 que reciben los roles base ya instalados.
     * El Administrador los tiene todos sin necesidad de asignarlos.
     *
     * @var array<string, list<string>>
     */
    protected array $permisos = [
        'recepcion' => ['memorandos.reemplazar_pdf', 'memorandos.exportar', 'reportes.consultar'],
        'consulta' => ['memorandos.exportar', 'reportes.consultar'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->permisos as $slug => $permisos) {
            $rolId = DB::table('roles')->where('slug', $slug)->value('id');

            if (! $rolId) {
                continue;
            }

            DB::table('permiso_rol')->insertOrIgnore(array_map(
                fn (string $permiso) => ['rol_id' => $rolId, 'permiso' => $permiso],
                $permisos,
            ));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permiso_rol')
            ->whereIn('permiso', ['memorandos.reemplazar_pdf', 'memorandos.exportar', 'memorandos.historial', 'reportes.consultar'])
            ->delete();
    }
};
