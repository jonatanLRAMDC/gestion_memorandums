<?php

use App\Models\Bitacora;
use App\Models\Gerencia;
use App\Models\User;
use Livewire\Volt\Volt;

test('administrators can see the gerencias catalog', function () {
    $gerencia = Gerencia::factory()->create();

    $this->actingAs(User::factory()->administrador()->create())
        ->get(route('admin.gerencias'))
        ->assertOk()
        ->assertSee($gerencia->nombre);
});

test('non administrators can not open the admin screens', function (string $ruta) {
    $this->actingAs(User::factory()->recepcion()->create())
        ->get(route($ruta))
        ->assertForbidden();
})->with(['admin.gerencias', 'admin.usuarios', 'admin.bitacora']);

test('an administrator can create a gerencia with uppercase siglas', function () {
    $this->actingAs(User::factory()->administrador()->create());

    Volt::test('admin.gerencias')
        ->call('crear')
        ->set('nombre', 'Gerencia de Prueba')
        ->set('siglas', 'gpr')
        ->set('titular_nombre', 'Lic. Ana Pérez')
        ->call('guardar')
        ->assertHasNoErrors();

    $gerencia = Gerencia::firstWhere('nombre', 'Gerencia de Prueba');

    expect($gerencia->siglas)->toBe('GPR')
        ->and(Bitacora::where('accion', 'gerencia_creada')->where('entidad_id', $gerencia->id)->exists())->toBeTrue();
});

test('siglas must be unique', function () {
    Gerencia::factory()->create(['siglas' => 'GRH']);
    $this->actingAs(User::factory()->administrador()->create());

    Volt::test('admin.gerencias')
        ->call('crear')
        ->set('nombre', 'Otra gerencia')
        ->set('siglas', 'GRH')
        ->call('guardar')
        ->assertHasErrors(['siglas' => 'unique']);
});

test('deactivating a gerencia keeps the record', function () {
    $gerencia = Gerencia::factory()->create();
    $this->actingAs(User::factory()->administrador()->create());

    Volt::test('admin.gerencias')->call('cambiarEstado', $gerencia->id);

    expect($gerencia->refresh()->activa)->toBeFalse();
});

test('the system own gerencia can not be deactivated', function () {
    $gis = Gerencia::factory()->gis()->create();
    $this->actingAs(User::factory()->administrador()->create(['gerencia_id' => $gis->id]));

    Volt::test('admin.gerencias')->call('cambiarEstado', $gis->id);

    expect($gis->refresh()->activa)->toBeTrue();
});
