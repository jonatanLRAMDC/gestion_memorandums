<?php

use App\Enums\TipoRegistro;
use App\Models\Bitacora;
use App\Models\Gerencia;
use App\Models\Memorando;
use App\Models\Registro;
use App\Models\Rol;
use App\Models\User;

beforeEach(function () {
    $this->gis = Gerencia::factory()->gis()->create();
    $this->usuario = User::factory()->create(['gerencia_id' => $this->gis->id]);
});

/**
 * Memo registrado por la gerencia, sin PDF (la exportación no lo necesita).
 */
function memoParaExportar(Gerencia $gerencia, TipoRegistro $tipo, array $datos = []): Memorando
{
    $memo = Memorando::factory()->create($datos);
    Registro::factory()->create(['memorando_id' => $memo->id, 'gerencia_id' => $gerencia->id, 'tipo' => $tipo]);

    return $memo;
}

/**
 * Contenido XML de la primera hoja del libro descargado.
 */
function hojaDescargada($respuesta): string
{
    $zip = new ZipArchive;
    $zip->open($respuesta->baseResponse->getFile()->getPathname());
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    return $xml;
}

test('the filtered list is exported to an Excel workbook and logged', function () {
    $emitido = memoParaExportar($this->gis, TipoRegistro::Emitido, ['asunto' => 'Asunto & con <símbolos>']);
    $recibido = memoParaExportar($this->gis, TipoRegistro::Recibido);
    $ajeno = memoParaExportar(Gerencia::factory()->create(), TipoRegistro::Emitido);

    $respuesta = $this->actingAs($this->usuario)
        ->get(route('memorandos.exportar.excel', ['tipo' => 'emitido']))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $hoja = hojaDescargada($respuesta);

    expect($hoja)->toContain($emitido->numero)
        ->toContain('Asunto &amp; con &lt;símbolos&gt;')
        ->toContain('Tipo: emitidos')
        ->not->toContain($recibido->numero)
        ->not->toContain($ajeno->numero);

    $entrada = Bitacora::firstWhere('accion', 'listado_exportado');

    expect($entrada->despues['formato'])->toBe('Excel')
        ->and($entrada->despues['registros'])->toBe(1)
        ->and($entrada->despues['filtros']['tipo'])->toBe('emitido');
});

test('the filtered list has a printable view to save as PDF', function () {
    $memo = memoParaExportar($this->gis, TipoRegistro::Recibido);

    $this->actingAs($this->usuario)
        ->get(route('memorandos.exportar.imprimir'))
        ->assertOk()
        ->assertSee($memo->numero)
        ->assertSee('Imprimir o guardar como PDF');

    expect(Bitacora::where('accion', 'listado_exportado')->exists())->toBeTrue();
});

test('users without the export permission can not export', function () {
    $rol = Rol::factory()->create();
    $usuario = User::factory()->conRol($rol)->create(['gerencia_id' => $this->gis->id]);
    $rol->sincronizarPermisos(['memorandos.consultar']);

    $this->actingAs($usuario)->get(route('memorandos.exportar.excel'))->assertForbidden();
    $this->actingAs($usuario)->get(route('memorandos.exportar.imprimir'))->assertForbidden();
});
