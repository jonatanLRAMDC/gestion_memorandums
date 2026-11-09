<?php

use App\Enums\Permiso;
use App\Enums\TipoDestino;
use App\Enums\TipoRegistro;
use App\Models\Archivo;
use App\Models\Bitacora;
use App\Models\Gerencia;
use App\Models\Memorando;
use App\Models\MemorandoDestino;
use App\Models\Registro;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    Storage::fake('memorandos');
    $this->travelTo(now()->setDate(2026, 11, 9));

    $this->gis = Gerencia::factory()->gis()->create();
    $this->rrhh = Gerencia::factory()->create(['nombre' => 'Gerencia de Recursos Humanos', 'siglas' => 'GRH']);
    $this->finanzas = Gerencia::factory()->create(['nombre' => 'Gerencia de Finanzas', 'siglas' => 'GF', 'titular_nombre' => 'Lic. Mario Flores']);
    $this->recepcion = User::factory()->recepcion()->create(['gerencia_id' => $this->gis->id]);
});

/**
 * Memo emitido por GIS hacia RRHH, con su registro y su PDF vigente en el disco privado.
 */
function memoParaEditar(array $datos = []): Memorando
{
    $memo = Memorando::factory()->create([
        'numero' => 'AMDC-GIS-398-2026',
        'siglas' => 'GIS',
        'correlativo' => '398',
        'correlativo_valor' => 398,
        'anio' => 2026,
        'gerencia_emisora_id' => test()->gis->id,
        'asunto' => 'Organización de turnos',
        'fecha_memo' => '2026-09-29',
        ...$datos,
    ]);
    MemorandoDestino::factory()->create(['memorando_id' => $memo->id, 'gerencia_id' => test()->rrhh->id, 'persona_nombre' => 'Lic. Gabriela Salgado', 'tipo' => TipoDestino::Para]);
    Registro::factory()->create(['memorando_id' => $memo->id, 'gerencia_id' => test()->gis->id, 'tipo' => TipoRegistro::Emitido, 'fecha_movimiento' => '2026-09-30']);
    $archivo = Archivo::factory()->create(['memorando_id' => $memo->id, 'sha256' => hash('sha256', 'version-1')]);
    Storage::disk('memorandos')->put($archivo->ruta_interna, pdfDePrueba(1)->getContent());

    return $memo;
}

test('the edit page opens with the current data for users with the edit permission', function () {
    $memo = memoParaEditar();

    $this->actingAs($this->recepcion)
        ->get(route('memorandos.editar', $memo))
        ->assertOk()
        ->assertSee('Editar memorando')
        ->assertSee('Organización de turnos');

    $this->actingAs(User::factory()->create(['gerencia_id' => $this->gis->id]))
        ->get(route('memorandos.editar', $memo))
        ->assertForbidden();
});

test('a memo of another gerencia can not be edited', function () {
    $ajeno = Memorando::factory()->create();
    Registro::factory()->create(['memorando_id' => $ajeno->id, 'gerencia_id' => $this->rrhh->id]);

    $this->actingAs($this->recepcion)
        ->get(route('memorandos.editar', $ajeno))
        ->assertForbidden();
});

test('editing saves the changes and logs only the changed fields with their previous value', function () {
    $memo = memoParaEditar();

    $this->actingAs($this->recepcion);

    Volt::test('memorandos.editar', ['memorando' => $memo])
        ->set('form.correlativo', '399')
        ->set('form.asunto', 'Organización de turnos y teletrabajo')
        ->call('agregarDestino')
        ->set('form.destinos.1.gerencia_id', $this->finanzas->id)
        ->set('form.fecha_movimiento', '2026-10-01')
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect(route('memorandos.ver', $memo));

    $memo->refresh();
    $entrada = Bitacora::firstWhere('accion', 'memorando_editado');

    expect($memo->numero)->toBe('AMDC-GIS-399-2026')
        ->and($memo->correlativo_valor)->toBe(399)
        ->and($memo->destinos()->where('tipo', TipoDestino::Copia)->value('gerencia_id'))->toBe($this->finanzas->id)
        ->and($memo->registros()->first()->fecha_movimiento->toDateString())->toBe('2026-10-01')
        ->and($entrada->usuario_id)->toBe($this->recepcion->id)
        ->and(array_keys($entrada->despues))->toEqualCanonicalizing(['numero', 'asunto', 'copia', 'fecha_movimiento'])
        ->and($entrada->antes['numero'])->toBe('AMDC-GIS-398-2026')
        ->and($entrada->antes['copia'])->toBeNull()
        ->and($entrada->despues['copia'])->toBe('Gerencia de Finanzas (Lic. Mario Flores)');
});

test('saving without changes does not write to the log', function () {
    $memo = memoParaEditar();

    $this->actingAs($this->recepcion);

    Volt::test('memorandos.editar', ['memorando' => $memo])
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertNoRedirect();

    expect(Bitacora::where('accion', 'memorando_editado')->exists())->toBeFalse();
});

test('a memo can not take the number of another registered memo', function () {
    memoParaEditar(['numero' => 'AMDC-GIS-400-2026', 'correlativo' => '400', 'correlativo_valor' => 400]);
    $memo = memoParaEditar();

    $this->actingAs($this->recepcion);

    Volt::test('memorandos.editar', ['memorando' => $memo])
        ->set('form.correlativo', '0400')
        ->call('guardar')
        ->assertHasErrors('form.correlativo');

    expect($memo->refresh()->numero)->toBe('AMDC-GIS-398-2026');
});

test('the siglas of an emitted memo can not be changed when editing', function () {
    $memo = memoParaEditar();

    $this->actingAs($this->recepcion);

    Volt::test('memorandos.editar', ['memorando' => $memo])
        ->set('form.siglas', 'GRH')
        ->set('form.asunto', 'Asunto corregido')
        ->call('guardar')
        ->assertHasNoErrors();

    expect($memo->refresh()->numero)->toBe('AMDC-GIS-398-2026');
});

test('a recipient that is now inactive can stay on the edited memo', function () {
    $memo = memoParaEditar();
    $this->rrhh->update(['activa' => false]);

    $this->actingAs($this->recepcion);

    Volt::test('memorandos.editar', ['memorando' => $memo])
        ->set('form.asunto', 'Asunto corregido')
        ->call('guardar')
        ->assertHasNoErrors();
});

test('replacing the PDF keeps the previous version as not current', function () {
    $memo = memoParaEditar();
    $anterior = $memo->archivoVigente;

    $this->actingAs($this->recepcion);

    Volt::test('memorandos.reemplazar-pdf', ['memorando' => $memo])
        ->set('form.pdf', pdfDePrueba(3, 'memo-corregido.pdf'))
        ->set('form.motivo', 'El escaneo anterior estaba incompleto')
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect(route('memorandos.ver', $memo));

    $nuevo = $memo->refresh()->archivoVigente;

    expect($nuevo->version)->toBe(2)
        ->and($nuevo->paginas)->toBe(3)
        ->and($nuevo->motivo)->toBe('El escaneo anterior estaba incompleto')
        ->and($anterior->refresh()->vigente)->toBeFalse()
        ->and($anterior->reemplazado_en)->not->toBeNull()
        ->and(Bitacora::where('accion', 'pdf_reemplazado')->where('entidad_id', $memo->id)->first()->despues['version'])->toBe(2);

    Storage::disk('memorandos')->assertExists([$anterior->ruta_interna, $nuevo->ruta_interna]);
});

test('replacing the PDF needs a reason and a different file', function () {
    $memo = memoParaEditar();
    $pdf = pdfDePrueba(1, 'mismo.pdf');
    $memo->archivoVigente->update(['sha256' => hash_file('sha256', $pdf->getRealPath())]);

    $this->actingAs($this->recepcion);

    Volt::test('memorandos.reemplazar-pdf', ['memorando' => $memo])
        ->call('guardar')
        ->assertHasErrors(['form.pdf' => 'required', 'form.motivo' => 'required'])
        ->set('form.pdf', $pdf)
        ->set('form.motivo', 'Escaneo ilegible')
        ->call('guardar')
        ->assertHasErrors('form.pdf');

    expect($memo->archivos()->count())->toBe(1);
});

test('users without the replace permission can not open the replace page', function () {
    $memo = memoParaEditar();

    $this->actingAs(User::factory()->create(['gerencia_id' => $this->gis->id]))
        ->get(route('memorandos.reemplazar-pdf', $memo))
        ->assertForbidden();
});

test('previous versions and the change history are only available with the history permission', function () {
    $memo = memoParaEditar();
    $anterior = $memo->archivoVigente;
    $anterior->update(['vigente' => false]);
    $vigente = Archivo::factory()->create(['memorando_id' => $memo->id, 'version' => 2]);
    Storage::disk('memorandos')->put($vigente->ruta_interna, pdfDePrueba(1)->getContent());

    $this->actingAs($this->recepcion)
        ->get(route('memorandos.pdf', [$memo, 'version' => $anterior->id]))
        ->assertForbidden();

    $this->actingAs($this->recepcion)
        ->get(route('memorandos.ver', $memo))
        ->assertOk()
        ->assertDontSee('Historial de cambios');

    $administrador = User::factory()->administrador()->create(['gerencia_id' => $this->gis->id]);

    $this->actingAs($administrador)
        ->get(route('memorandos.pdf', [$memo, 'version' => $anterior->id]))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename=AMDC-GIS-398-2026-v1.pdf');

    $this->actingAs($administrador)
        ->get(route('memorandos.ver', $memo))
        ->assertOk()
        ->assertSee('Historial de cambios')
        ->assertSee('No vigente');
});

test('the phase 3 permissions are added to the base roles already installed', function () {
    DB::table('permiso_rol')->whereIn('permiso', ['memorandos.reemplazar_pdf', 'memorandos.exportar', 'reportes.consultar'])->delete();

    $migracion = require database_path('migrations/2026_11_09_155602_add_fase_3_permissions_to_base_roles.php');
    $migracion->up();
    $migracion->up();

    $recepcion = Rol::porSlug(Rol::RECEPCION)->load('permisos');
    $consulta = Rol::porSlug(Rol::CONSULTA)->load('permisos');

    expect($recepcion->tiene(Permiso::MemorandosReemplazarPdf))->toBeTrue()
        ->and($recepcion->tiene(Permiso::MemorandosExportar))->toBeTrue()
        ->and($recepcion->tiene(Permiso::MemorandosHistorial))->toBeFalse()
        ->and($consulta->tiene(Permiso::MemorandosExportar))->toBeTrue()
        ->and($consulta->tiene(Permiso::MemorandosReemplazarPdf))->toBeFalse()
        ->and(Rol::porSlug(Rol::ADMINISTRADOR)->tiene(Permiso::MemorandosHistorial))->toBeTrue();
});
