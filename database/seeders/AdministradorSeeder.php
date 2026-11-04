<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Gerencia;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdministradorSeeder extends Seeder
{
    /**
     * Crea el administrador inicial de la GIS si todavía no existe.
     * Si ADMIN_PASSWORD está vacío, genera una contraseña y la muestra una vez.
     */
    public function run(): void
    {
        $datos = config('memorandos.admin');

        if (User::query()->where('email', $datos['email'])->exists()) {
            $this->command?->info("El administrador {$datos['email']} ya existe; no se modificó.");

            return;
        }

        $contrasena = filled($datos['password']) ? $datos['password'] : Str::password(14, symbols: false);

        User::query()->create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $contrasena,
            'gerencia_id' => Gerencia::propia()?->id,
            'rol' => Rol::Administrador,
            'activo' => true,
            'debe_cambiar_contrasena' => true,
        ]);

        if (blank($datos['password'])) {
            $this->command?->warn("Administrador creado: {$datos['email']} / contraseña temporal: {$contrasena}");
        } else {
            $this->command?->info("Administrador creado: {$datos['email']} (contraseña tomada de ADMIN_PASSWORD).");
        }

        $this->command?->line('Se pedirá cambiar la contraseña en el primer inicio de sesión.');
    }
}
