<?php

namespace Database\Factories;

use App\Enums\TipoRegistro;
use App\Models\Gerencia;
use App\Models\Memorando;
use App\Models\Registro;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registro>
 */
class RegistroFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'memorando_id' => Memorando::factory(),
            'gerencia_id' => Gerencia::factory(),
            'tipo' => fake()->randomElement(TipoRegistro::cases()),
            'fecha_movimiento' => fake()->dateTimeBetween('-3 months', 'now'),
            'observaciones' => null,
            'usuario_id' => User::factory(),
        ];
    }
}
