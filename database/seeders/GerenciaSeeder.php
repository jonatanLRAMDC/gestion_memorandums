<?php

namespace Database\Seeders;

use App\Models\Gerencia;
use Illuminate\Database\Seeder;

class GerenciaSeeder extends Seeder
{
    /**
     * Catálogo inicial de gerencias. Solo GIS es oficial; las demás siglas son
     * provisionales hasta contar con la lista oficial.
     *
     * @var array<string, string>
     */
    public const GERENCIAS = [
        'GA' => 'Gerencia Administrativa',
        'GAC' => 'Gerencia Atención al Ciudadano',
        'GBP' => 'Gerencia Bufete del Pueblo',
        'GAL' => 'Gerencia de Aldeas',
        'GAP' => 'Gerencia de Alianza con el Pueblo',
        'GCAT' => 'Gerencia de Catastro',
        'GCOM' => 'Gerencia de Comayagüela',
        'GCI' => 'Gerencia de Comunicación Institucional',
        'GCONT' => 'Gerencia de Contabilidad',
        'GCC' => 'Gerencia de Control de la Construcción',
        'GE' => 'Gerencia de Educación',
        'GEM' => 'Gerencia de Educación Municipal',
        'GER' => 'Gerencia de Evaluación de Riesgo',
        'GF' => 'Gerencia de Finanzas',
        'GIP' => 'Gerencia de Información Pública',
        'GIS' => 'Gerencia de Información y Sistemas',
        'GMV' => 'Gerencia de Mejoramiento de Vivienda',
        'GMER' => 'Gerencia de Mercado',
        'GMIC' => 'Gerencia de Microempresa',
        'GOC' => 'Gerencia de Obras Civiles',
        'GOP' => 'Gerencia de Orden Público',
        'GPCPS' => 'Gerencia de Participación Ciudadana y Promoción Social',
        'GPRE' => 'Gerencia de Presupuesto',
        'GPRO' => 'Gerencia de Protocolo',
        'GPS' => 'Gerencia de Proyección Social',
        'GRCF' => 'Gerencia de Recaudación y Control Financiero',
        'GRH' => 'Gerencia de Recursos Humanos',
        'GSP' => 'Gerencia de Salud para el Pueblo',
        'GTC' => 'Gerencia de Turismo y Cultura',
        'GVA' => 'Gerencia de Vicealcaldía',
        'GVM' => 'Gerencia de Vigilancia y Monitoreo',
        'GMU' => 'Gerencia Movilidad Urbana',
        'GTCO' => 'Gerencia Transporte y Combustible',
    ];

    /**
     * Crea o actualiza el catálogo. Busca por nombre para poder ejecutarse
     * varias veces sin duplicar ni borrar titulares ya registrados.
     */
    public function run(): void
    {
        foreach (self::GERENCIAS as $siglas => $nombre) {
            Gerencia::query()->updateOrCreate(
                ['nombre' => $nombre],
                [
                    'siglas' => $siglas,
                    'usa_sistema' => $siglas === config('memorandos.siglas_gerencia_propia'),
                ],
            );
        }
    }
}
