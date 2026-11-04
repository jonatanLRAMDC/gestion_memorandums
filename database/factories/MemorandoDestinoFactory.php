<?php

namespace Database\Factories;

use App\Enums\TipoDestino;
use App\Models\Gerencia;
use App\Models\Memorando;
use App\Models\MemorandoDestino;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemorandoDestino>
 */
class MemorandoDestinoFactory extends Factory
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
            'persona_nombre' => fake()->name(),
            'tipo' => TipoDestino::Para,
        ];
    }
}
