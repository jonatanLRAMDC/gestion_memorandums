<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Gerencia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gerencia_id' => Gerencia::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'rol' => Rol::Consulta,
            'activo' => true,
            'intentos_fallidos' => 0,
            'bloqueado_en' => null,
            'debe_cambiar_contrasena' => false,
        ];
    }

    public function administrador(): static
    {
        return $this->state(fn (array $attributes) => ['rol' => Rol::Administrador]);
    }

    public function recepcion(): static
    {
        return $this->state(fn (array $attributes) => ['rol' => Rol::Recepcion]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }

    public function bloqueado(): static
    {
        return $this->state(fn (array $attributes) => [
            'intentos_fallidos' => config('auth.max_intentos'),
            'bloqueado_en' => now(),
        ]);
    }

    public function conContrasenaTemporal(): static
    {
        return $this->state(fn (array $attributes) => ['debe_cambiar_contrasena' => true]);
    }
}
