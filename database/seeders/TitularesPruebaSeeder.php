<?php

namespace Database\Seeders;

use App\Models\Gerencia;
use Illuminate\Database\Seeder;

class TitularesPruebaSeeder extends Seeder
{
    /**
     * Titulares ficticios para desarrollo y pruebas, por siglas de gerencia.
     * No son los titulares reales; se reemplazan desde la pantalla de Gerencias.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const TITULARES = [
        'GA' => ['Lic. Marcela Andino', 'Gerente Administrativa'],
        'GAC' => ['Lic. Óscar Zelaya', 'Gerente'],
        'GBP' => ['Abg. Rosa Membreño', 'Gerente'],
        'GAL' => ['Ing. Mario Funes', 'Gerente'],
        'GAP' => ['Lic. Karla Ordóñez', 'Gerente'],
        'GCAT' => ['Ing. Héctor Banegas', 'Gerente'],
        'GCOM' => ['Lic. Sandra Rivera', 'Gerente'],
        'GCI' => ['Lic. Daniel Matamoros', 'Gerente'],
        'GCONT' => ['Lic. Patricia Valladares', 'Gerente'],
        'GCC' => ['Arq. Fernando Cálix', 'Gerente'],
        'GE' => ['Lic. Gloria Pineda', 'Gerente'],
        'GEM' => ['M.Sc. Ricardo Aguilar', 'Gerente'],
        'GER' => ['Ing. Lorena Castellanos', 'Gerente'],
        'GF' => ['Lic. Jorge Midence', 'Gerente'],
        'GIP' => ['Lic. Ana Lucía Paz', 'Oficial de Información Pública'],
        'GIS' => ['Ing. Roberto Euceda', 'Gerente'],
        'GMV' => ['Arq. Silvia Mejía', 'Gerente'],
        'GMER' => ['Lic. Carlos Turcios', 'Gerente'],
        'GMIC' => ['Lic. Dania Romero', 'Gerente'],
        'GOC' => ['Ing. Andrés Bonilla', 'Gerente'],
        'GOP' => ['Cap. Julio Maradiaga', 'Gerente'],
        'GPCPS' => ['Lic. Nora Espinal', 'Gerente'],
        'GPRE' => ['Lic. Iván Discua', 'Gerente'],
        'GPRO' => ['Lic. Claudia Bográn', 'Jefa de Protocolo'],
        'GPS' => ['Lic. Esteban Lagos', 'Gerente'],
        'GRCF' => ['Lic. Mirna Ferrera', 'Gerente'],
        'GRH' => ['Lic. Gabriela Salgado', 'Gerente'],
        'GSP' => ['Dra. Elena Moncada', 'Gerente'],
        'GTC' => ['Lic. Raúl Sevilla', 'Gerente'],
        'GVA' => ['Abg. Teresa Cruz', 'Gerente'],
        'GVM' => ['Ing. Hugo Rosales', 'Gerente'],
        'GMU' => ['Ing. Diana Escoto', 'Gerente'],
        'GTCO' => ['Lic. Wilmer Sorto', 'Gerente'],
    ];

    /**
     * Asigna titulares de prueba solo a las gerencias que todavía no tienen titular.
     */
    public function run(): void
    {
        foreach (self::TITULARES as $siglas => [$nombre, $cargo]) {
            Gerencia::query()
                ->where('siglas', $siglas)
                ->whereNull('titular_nombre')
                ->update(['titular_nombre' => $nombre, 'titular_cargo' => $cargo]);
        }
    }
}
