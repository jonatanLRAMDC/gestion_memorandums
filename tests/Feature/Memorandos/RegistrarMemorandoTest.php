<?php

use App\Enums\TipoRegistro;
use App\Models\Archivo;
use App\Models\Bitacora;
use App\Models\Gerencia;
use App\Models\Memorando;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;

beforeEach(function () {
    Storage::fake('memorandos');
    $this->travelTo(now()->setDate(2026, 11, 4));

    $this->gis = Gerencia::factory()->gis()->create(['titular_nombre' => 'Ing. Roberto Euceda', 'titular_cargo' => 'Gerente']);
    $this->rrhh = Gerencia::factory()->create(['nombre' => 'Gerencia de Recursos Humanos', 'siglas' => 'GRH', 'titular_nombre' => 'Lic. Gabriela Salgado', 'titular_cargo' => 'Gerente']);
    $this->usuario = User::factory()->recepcion()->create(['gerencia_id' => $this->gis->id]);
    $this->actingAs($this->usuario);
});

/**
 * Llena un registro de memo emitido válido.
 */
function memoEmitido(array $cambios = []): Testable
{
    $componente = Volt::test('memorandos.registrar', ['tipo' => 'emitido'])
        ->set('form.correlativo', '398')
        ->set('form.destinos.0.gerencia_id', test()->rrhh->id)
        ->set('form.asunto', 'Organización de turnos y rotación de teletrabajo')
        ->set('form.fecha_memo', '2026-09-29')
        ->set('form.fecha_movimiento', '2026-09-30')
        ->set('form.pdf', pdfDePrueba(2));

    foreach ($cambios as $campo => $valor) {
        $componente->set($campo, $valor);
    }

    return $componente;
}

test('an emitted memo is saved with its recipients, registro and private PDF', function () {
    memoEmitido()->call('guardar')->assertHasNoErrors();

    $memo = Memorando::firstWhere('numero', 'AMDC-GIS-398-2026');
    $archivo = $memo->archivoVigente;

    expect($memo->gerencia_emisora_id)->toBe($this->gis->id)
        ->and($memo->remitente_nombre)->toBe('Ing. Roberto Euceda')
        ->and($memo->destinos->first()->gerencia_id)->toBe($this->rrhh->id)
        ->and($memo->destinos->first()->persona_nombre)->toBe('Lic. Gabriela Salgado')
        ->and($memo->registros->first()->tipo)->toBe(TipoRegistro::Emitido)
        ->and($memo->registros->first()->gerencia_id)->toBe($this->gis->id)
        ->and($archivo->paginas)->toBe(2)
        ->and(Bitacora::where('accion', 'memorando_registrado')->where('entidad_id', $memo->id)->exists())->toBeTrue();

    Storage::disk('memorandos')->assertExists($archivo->ruta_interna);
});

test('the own siglas of an emitted memo can not be changed from the browser', function () {
    memoEmitido(['form.siglas' => 'GRH'])->call('guardar')->assertHasNoErrors();

    expect(Memorando::first()->numero)->toBe('AMDC-GIS-398-2026');
});

test('choosing the sender of a received memo fills its siglas and titular', function () {
    Volt::test('memorandos.registrar', ['tipo' => 'recibido'])
        ->set('form.gerencia_remitente_id', $this->rrhh->id)
        ->assertSet('form.siglas', 'GRH')
        ->assertSet('form.remitente_nombre', 'Lic. Gabriela Salgado')
        ->assertSet('form.persona_destinataria', 'Ing. Roberto Euceda')
        ->set('form.correlativo', '0120')
        ->set('form.asunto', 'Planilla complementaria')
        ->set('form.fecha_memo', '2026-10-01')
        ->set('form.fecha_movimiento', '2026-10-02')
        ->set('form.pdf', pdfDePrueba(1))
        ->call('guardar')
        ->assertHasNoErrors();

    $memo = Memorando::firstWhere('numero', 'AMDC-GRH-0120-2026');

    expect($memo->gerencia_emisora_id)->toBe($this->rrhh->id)
        ->and($memo->correlativo_valor)->toBe(120)
        ->and($memo->destinos->first()->gerencia_id)->toBe($this->gis->id)
        ->and($memo->registros->first()->tipo)->toBe(TipoRegistro::Recibido);
});

test('the year follows the date of the memo', function () {
    Volt::test('memorandos.registrar', ['tipo' => 'emitido'])
        ->set('form.fecha_memo', '2025-12-30')
        ->assertSet('form.anio', 2025);
});

test('a memo number can only be registered once, even with leading zeros', function () {
    memoEmitido()->call('guardar');

    memoEmitido(['form.correlativo' => '0398'])
        ->call('guardar')
        ->assertHasErrors('form.correlativo');

    expect(Memorando::count())->toBe(1)
        ->and(Storage::disk('memorandos')->allFiles())->toHaveCount(1);
});

test('files that are not real PDFs are rejected', function () {
    memoEmitido(['form.pdf' => UploadedFile::fake()->image('memo.pdf')])
        ->call('guardar')
        ->assertHasErrors('form.pdf');

    expect(Memorando::count())->toBe(0);
});

test('a truncated PDF is rejected', function () {
    $incompleto = UploadedFile::fake()->createWithContent('memo.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n");

    memoEmitido(['form.pdf' => $incompleto])
        ->call('guardar')
        ->assertHasErrors('form.pdf');
});

test('invalid dates are rejected', function () {
    memoEmitido(['form.fecha_memo' => '29/09/2026'])
        ->call('guardar')
        ->assertHasErrors('form.fecha_memo');

    memoEmitido(['form.fecha_memo' => '2026-02-31'])
        ->call('guardar')
        ->assertHasErrors('form.fecha_memo');
});

test('selected dates are shown in words below each field', function () {
    memoEmitido(['form.fecha_memo' => '2026-11-05'])
        ->assertSee('05 de noviembre de 2026')
        ->assertSee('30 de septiembre de 2026');
});

test('dates must be coherent', function () {
    memoEmitido(['form.fecha_movimiento' => '2026-09-01'])
        ->call('guardar')
        ->assertHasErrors('form.fecha_movimiento');

    memoEmitido(['form.fecha_memo' => '2026-12-01'])
        ->call('guardar')
        ->assertHasErrors('form.fecha_memo');
});

test('an emitted memo needs at least one recipient other than the own gerencia', function () {
    memoEmitido(['form.destinos.0.gerencia_id' => $this->gis->id])
        ->call('guardar')
        ->assertHasErrors('form.destinos.0.gerencia_id');
});

test('the same PDF in another memo asks for confirmation', function () {
    $pdf = pdfDePrueba(2, marca: 'mismo-archivo');
    memoEmitido(['form.pdf' => $pdf])->call('guardar');

    memoEmitido(['form.correlativo' => '399', 'form.pdf' => pdfDePrueba(2, marca: 'mismo-archivo')])
        ->call('guardar')
        ->assertHasErrors('form.pdf')
        ->assertSet('form.pdfDuplicadoEn', 'AMDC-GIS-398-2026')
        ->set('form.confirmarPdfDuplicado', true)
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Memorando::count())->toBe(2)
        ->and(Archivo::distinct()->count('sha256'))->toBe(1);
});

test('users without the register permission can not open the form', function () {
    $this->actingAs(User::factory()->create(['gerencia_id' => $this->gis->id]))
        ->get(route('memorandos.registrar', 'emitido'))
        ->assertForbidden();
});

test('the registration page renders with the own gerencia locked', function () {
    $this->get(route('memorandos.registrar', 'emitido'))
        ->assertOk()
        ->assertSee('Gerencia de Información y Sistemas')
        ->assertSee('AMDC-GIS-000-2026');
});
