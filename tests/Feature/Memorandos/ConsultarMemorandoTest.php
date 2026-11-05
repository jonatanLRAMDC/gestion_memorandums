<?php

use App\Enums\Permiso;
use App\Enums\TipoRegistro;
use App\Models\Archivo;
use App\Models\Bitacora;
use App\Models\Gerencia;
use App\Models\Memorando;
use App\Models\Registro;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    Storage::fake('memorandos');

    $this->gis = Gerencia::factory()->gis()->create();
    $this->usuario = User::factory()->create(['gerencia_id' => $this->gis->id]);
});

/**
 * Crea un memo registrado por la gerencia indicada, con su PDF en el disco privado.
 */
function memoRegistrado(Gerencia $gerencia, TipoRegistro $tipo, array $datos = []): Memorando
{
    $memo = Memorando::factory()->create($datos);
    Registro::factory()->create(['memorando_id' => $memo->id, 'gerencia_id' => $gerencia->id, 'tipo' => $tipo]);
    $archivo = Archivo::factory()->create(['memorando_id' => $memo->id]);
    Storage::disk('memorandos')->put($archivo->ruta_interna, pdfDePrueba(1)->getContent());

    return $memo;
}

test('the list shows only the memos of the user gerencia and filters by type', function () {
    $emitido = memoRegistrado($this->gis, TipoRegistro::Emitido, ['asunto' => 'Memo emitido de prueba']);
    $recibido = memoRegistrado($this->gis, TipoRegistro::Recibido, ['asunto' => 'Memo recibido de prueba']);
    $ajeno = memoRegistrado(Gerencia::factory()->create(), TipoRegistro::Emitido, ['asunto' => 'Memo de otra gerencia']);

    $this->actingAs($this->usuario);

    Volt::test('memorandos.index')
        ->assertSee($emitido->numero)
        ->assertSee($recibido->numero)
        ->assertDontSee($ajeno->numero)
        ->set('tipo', 'recibido')
        ->assertSee($recibido->numero)
        ->assertDontSee($emitido->numero);
});

test('the list can be searched by number', function () {
    $buscado = memoRegistrado($this->gis, TipoRegistro::Emitido);
    $otro = memoRegistrado($this->gis, TipoRegistro::Emitido);

    $this->actingAs($this->usuario);

    Volt::test('memorandos.index')
        ->set('buscar', $buscado->numero)
        ->assertSee($buscado->numero)
        ->assertDontSee($otro->numero);
});

test('the detail page shows the memo and its PDF viewer', function () {
    $memo = memoRegistrado($this->gis, TipoRegistro::Recibido);

    $this->actingAs($this->usuario)
        ->get(route('memorandos.ver', $memo))
        ->assertOk()
        ->assertSee($memo->numero)
        ->assertSee(route('memorandos.pdf', $memo), false);
});

test('the PDF opens inline for authorized users and is logged', function () {
    $memo = memoRegistrado($this->gis, TipoRegistro::Emitido);

    $this->actingAs($this->usuario)
        ->get(route('memorandos.pdf', $memo))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename='.$memo->numero.'.pdf');

    expect(Bitacora::where('accion', 'documento_consultado')->where('entidad_id', $memo->id)->exists())->toBeTrue();
});

test('the PDF can be downloaded with the download permission', function () {
    $memo = memoRegistrado($this->gis, TipoRegistro::Emitido);

    $this->actingAs($this->usuario)
        ->get(route('memorandos.pdf', [$memo, 'descargar' => 1]))
        ->assertOk()
        ->assertDownload($memo->numero.'.pdf');
});

test('users without the download permission can only view the PDF', function () {
    $rol = Rol::factory()->create();
    $rol->sincronizarPermisos([Permiso::MemorandosConsultar->value]);
    $usuario = User::factory()->conRol($rol)->create(['gerencia_id' => $this->gis->id]);
    $memo = memoRegistrado($this->gis, TipoRegistro::Emitido);

    $this->actingAs($usuario)->get(route('memorandos.pdf', $memo))->assertOk();
    $this->actingAs($usuario)->get(route('memorandos.pdf', [$memo, 'descargar' => 1]))->assertForbidden();
});

test('a memo of another gerencia can not be opened, even with a direct link', function () {
    $memo = memoRegistrado(Gerencia::factory()->create(), TipoRegistro::Emitido);

    $this->actingAs($this->usuario)->get(route('memorandos.ver', $memo))->assertForbidden();
    $this->actingAs($this->usuario)->get(route('memorandos.pdf', $memo))->assertForbidden();
});

test('guests can not reach the PDF', function () {
    $memo = memoRegistrado($this->gis, TipoRegistro::Emitido);

    $this->get(route('memorandos.pdf', $memo))->assertRedirect(route('login'));
});

test('the dashboard counts the memos of the user gerencia', function () {
    memoRegistrado($this->gis, TipoRegistro::Emitido);

    $this->actingAs($this->usuario)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Memorandos recientes');
});

test('the list can be filtered by memo date', function () {
    $septiembre = memoRegistrado($this->gis, TipoRegistro::Emitido, ['fecha_memo' => '2026-09-15']);
    $octubre = memoRegistrado($this->gis, TipoRegistro::Emitido, ['fecha_memo' => '2026-10-15']);

    $this->actingAs($this->usuario);

    Volt::test('memorandos.index')
        ->set('desde', '2026-10-01')
        ->assertSee($octubre->numero)
        ->assertDontSee($septiembre->numero);
});
