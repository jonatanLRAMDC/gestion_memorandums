<?php

use App\Enums\TipoDestino;
use App\Enums\TipoRegistro;
use App\Models\Gerencia;
use App\Models\Memorando;
use App\Models\MemorandoDestino;
use App\Models\Registro;
use App\Models\Rol;
use App\Models\User;
use App\Support\ReporteMemorandos;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 9));

    $this->gis = Gerencia::factory()->gis()->create();
    $this->rrhh = Gerencia::factory()->create(['nombre' => 'Gerencia de Recursos Humanos', 'siglas' => 'GRH']);
    $this->finanzas = Gerencia::factory()->create(['nombre' => 'Gerencia de Finanzas', 'siglas' => 'GF']);
    $this->usuario = User::factory()->create(['gerencia_id' => $this->gis->id]);

    // Octubre: un emitido a RRHH con copia a Finanzas y un recibido de RRHH.
    $emitido = Memorando::factory()->create(['gerencia_emisora_id' => $this->gis->id]);
    MemorandoDestino::factory()->create(['memorando_id' => $emitido->id, 'gerencia_id' => $this->rrhh->id, 'tipo' => TipoDestino::Para]);
    MemorandoDestino::factory()->create(['memorando_id' => $emitido->id, 'gerencia_id' => $this->finanzas->id, 'tipo' => TipoDestino::Copia]);
    Registro::factory()->create(['memorando_id' => $emitido->id, 'gerencia_id' => $this->gis->id, 'tipo' => TipoRegistro::Emitido, 'fecha_movimiento' => '2026-10-05']);

    $recibido = Memorando::factory()->create(['gerencia_emisora_id' => $this->rrhh->id]);
    Registro::factory()->create(['memorando_id' => $recibido->id, 'gerencia_id' => $this->gis->id, 'tipo' => TipoRegistro::Recibido, 'fecha_movimiento' => '2026-10-20']);

    // Fuera del período y de otra gerencia: no cuentan.
    Registro::factory()->create(['gerencia_id' => $this->gis->id, 'tipo' => TipoRegistro::Recibido, 'fecha_movimiento' => '2025-12-20']);
    Registro::factory()->create(['gerencia_id' => $this->rrhh->id, 'tipo' => TipoRegistro::Emitido, 'fecha_movimiento' => '2026-10-07']);
});

test('the report counts emitted and received memos by month and by gerencia', function () {
    $datos = ReporteMemorandos::desdeFiltros($this->gis->id, '2026-09-01', '2026-10-31')->datos();

    expect($datos['totales'])->toBe(['emitidos' => 1, 'recibidos' => 1, 'total' => 2])
        ->and($datos['meses'])->toBe([
            ['mes' => 'Septiembre 2026', 'emitidos' => 0, 'recibidos' => 0, 'total' => 0],
            ['mes' => 'Octubre 2026', 'emitidos' => 1, 'recibidos' => 1, 'total' => 2],
        ])
        ->and($datos['gerencias'])->toBe([
            ['gerencia' => 'Gerencia de Recursos Humanos', 'siglas' => 'GRH', 'emitidos' => 1, 'recibidos' => 1, 'total' => 2],
            ['gerencia' => 'Gerencia de Finanzas', 'siglas' => 'GF', 'emitidos' => 1, 'recibidos' => 0, 'total' => 1],
        ]);
});

test('the report page defaults to the current year and can be exported', function () {
    $this->actingAs($this->usuario);

    Volt::test('reportes.index')
        ->assertSet('desde', '2026-01-01')
        ->assertSet('hasta', '2026-11-09')
        ->assertSee('Gerencia de Recursos Humanos')
        ->call('periodo', 'anio_anterior')
        ->assertSet('desde', '2025-01-01')
        ->assertSet('hasta', '2025-12-31');

    $this->get(route('reportes.exportar.excel', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
        ->assertOk()
        ->assertDownload('reporte-memorandos-20261001-20261031.xlsx');

    $this->get(route('reportes.exportar.imprimir'))
        ->assertOk()
        ->assertSee('Por gerencia');
});

test('users without the report permission can not open the reports', function () {
    $rol = Rol::factory()->create();
    $rol->sincronizarPermisos(['memorandos.consultar']);

    $this->actingAs(User::factory()->conRol($rol)->create(['gerencia_id' => $this->gis->id]))
        ->get(route('reportes.index'))
        ->assertForbidden();
});
