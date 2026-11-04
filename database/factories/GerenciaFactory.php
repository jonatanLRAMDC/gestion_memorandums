<?php

namespace Database\Factories;

use App\Models\Gerencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gerencia>
 */
class GerenciaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Gerencia de '.fake()->unique()->words(3, true),
            'siglas' => 'G'.fake()->unique()->lexify('????'),
            'titular_nombre' => fake()->name(),
            'titular_cargo' => 'Gerente',
            'activa' => true,
            'usa_sistema' => false,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => ['activa' => false]);
    }

    /**
     * La Gerencia de Información y Sistemas, que usa el sistema.
     */
    public function gis(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Gerencia de Información y Sistemas',
            'siglas' => 'GIS',
            'usa_sistema' => true,
        ]);
    }
}
