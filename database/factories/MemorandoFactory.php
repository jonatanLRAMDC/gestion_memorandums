<?php

namespace Database\Factories;

use App\Models\Gerencia;
use App\Models\Memorando;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Memorando>
 */
class MemorandoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $correlativo = (string) fake()->unique()->numberBetween(1, 9999);
        $siglas = 'G'.strtoupper(fake()->lexify('???'));
        $fecha = fake()->dateTimeBetween('-6 months', 'now');
        $anio = (int) $fecha->format('Y');

        return [
            'numero' => Memorando::armarNumero($siglas, $correlativo, $anio),
            'siglas' => $siglas,
            'correlativo' => $correlativo,
            'correlativo_valor' => (int) $correlativo,
            'anio' => $anio,
            'gerencia_emisora_id' => Gerencia::factory(),
            'remitente_nombre' => fake()->name(),
            'remitente_cargo' => 'Gerente',
            'asunto' => fake()->sentence(6),
            'fecha_memo' => $fecha,
            'creado_por' => User::factory(),
        ];
    }
}
