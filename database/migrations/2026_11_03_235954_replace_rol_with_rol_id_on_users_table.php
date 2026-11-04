<?php

use App\Enums\Permiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles base del sistema. Están protegidos: no se borran ni se desactivan.
     *
     * @var array<string, array{nombre: string, descripcion: string}>
     */
    private const ROLES_BASE = [
        'administrador' => ['nombre' => 'Administrador', 'descripcion' => 'Acceso total al sistema y a su administración.'],
        'recepcion' => ['nombre' => 'Recepción', 'descripcion' => 'Registra y consulta memorandos emitidos y recibidos.'],
        'consulta' => ['nombre' => 'Consulta', 'descripcion' => 'Busca y consulta memorandos y sus PDF.'],
    ];

    /**
     * Crea los roles base y cambia la columna de texto `rol` de users por `rol_id`.
     */
    public function up(): void
    {
        $ahora = now();

        foreach (self::ROLES_BASE as $slug => $datos) {
            $rolId = DB::table('roles')->insertGetId([
                'nombre' => $datos['nombre'],
                'slug' => $slug,
                'descripcion' => $datos['descripcion'],
                'protegido' => true,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            DB::table('permiso_rol')->insert(array_map(
                fn (Permiso $permiso) => ['rol_id' => $rolId, 'permiso' => $permiso->value],
                Permiso::porDefecto($slug),
            ));
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('rol_id')->nullable()->after('gerencia_id')->constrained('roles')->restrictOnDelete();
        });

        foreach (DB::table('roles')->pluck('id', 'slug') as $slug => $rolId) {
            DB::table('users')->where('rol', $slug)->update(['rol_id' => $rolId]);
        }

        DB::table('users')->whereNull('rol_id')->update([
            'rol_id' => DB::table('roles')->where('slug', 'consulta')->value('id'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 20)->default('consulta')->after('password');
        });

        foreach (DB::table('roles')->whereIn('slug', array_keys(self::ROLES_BASE))->pluck('id', 'slug') as $slug => $rolId) {
            DB::table('users')->where('rol_id', $rolId)->update(['rol' => $slug]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rol_id');
        });

        DB::table('roles')->delete();
    }
};
