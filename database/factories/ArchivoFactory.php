<?php

namespace Database\Factories;

use App\Models\Archivo;
use App\Models\Memorando;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Archivo>
 */
class ArchivoFactory extends Factory
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
            'registro_id' => null,
            'ruta_interna' => now()->format('Y/m').'/'.Str::uuid().'.pdf',
            'nombre_original' => 'memorando.pdf',
            'sha256' => hash('sha256', Str::random(40)),
            'tamano_bytes' => fake()->numberBetween(50_000, 2_000_000),
            'paginas' => fake()->numberBetween(1, 5),
            'version' => 1,
            'vigente' => true,
            'subido_por' => User::factory(),
        ];
    }
}
