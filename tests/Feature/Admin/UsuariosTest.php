<?php

use App\Models\Bitacora;
use App\Models\Gerencia;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->gis = Gerencia::factory()->gis()->create();
    $this->admin = User::factory()->administrador()->create(['gerencia_id' => $this->gis->id]);
    $this->actingAs($this->admin);
});

test('an administrator creates a user with a temporary password', function () {
    $componente = Volt::test('admin.usuarios')
        ->call('crear')
        ->assertSet('gerencia_id', $this->gis->id)
        ->set('name', 'María López')
        ->set('email', 'Maria.Lopez@AMDC.hn')
        ->set('rol_id', Rol::porSlug(Rol::RECEPCION)->id)
        ->call('guardar')
        ->assertHasNoErrors();

    $usuario = User::firstWhere('email', 'maria.lopez@amdc.hn');
    $contrasena = $componente->get('contrasenaTemporal');

    expect($usuario->rol->slug)->toBe(Rol::RECEPCION)
        ->and($usuario->debe_cambiar_contrasena)->toBeTrue()
        ->and(Hash::check($contrasena, $usuario->password))->toBeTrue()
        ->and(Bitacora::where('accion', 'usuario_creado')->where('entidad_id', $usuario->id)->exists())->toBeTrue();
});

test('the email must be unique', function () {
    User::factory()->create(['email' => 'repetido@amdc.hn']);

    Volt::test('admin.usuarios')
        ->call('crear')
        ->set('name', 'Otra persona')
        ->set('email', 'repetido@amdc.hn')
        ->call('guardar')
        ->assertHasErrors(['email' => 'unique']);
});

test('an administrator can unlock a blocked account', function () {
    $usuario = User::factory()->bloqueado()->create();

    Volt::test('admin.usuarios')->call('desbloquear', $usuario->id);

    $usuario->refresh();
    expect($usuario->estaBloqueado())->toBeFalse()
        ->and($usuario->intentos_fallidos)->toBe(0);
});

test('resetting a password unlocks the account and forces a change', function () {
    $usuario = User::factory()->bloqueado()->create();

    $contrasena = Volt::test('admin.usuarios')
        ->call('restablecerContrasena', $usuario->id)
        ->get('contrasenaTemporal');

    $usuario->refresh();
    expect(Hash::check($contrasena, $usuario->password))->toBeTrue()
        ->and($usuario->debe_cambiar_contrasena)->toBeTrue()
        ->and($usuario->estaBloqueado())->toBeFalse();
});

test('administrators can not deactivate their own account or drop their role', function () {
    Volt::test('admin.usuarios')->call('cambiarEstado', $this->admin->id);

    expect($this->admin->refresh()->activo)->toBeTrue();

    Volt::test('admin.usuarios')
        ->call('editar', $this->admin->id)
        ->set('rol_id', Rol::porSlug(Rol::CONSULTA)->id)
        ->call('guardar')
        ->assertHasErrors('rol_id');

    expect($this->admin->refresh()->esAdministrador())->toBeTrue();
});

test('an administrator can deactivate another user', function () {
    $usuario = User::factory()->create();

    Volt::test('admin.usuarios')->call('cambiarEstado', $usuario->id);

    expect($usuario->refresh()->activo)->toBeFalse();
});

test('deactivating a user asks for confirmation in a modal first', function () {
    $usuario = User::factory()->create();

    Volt::test('admin.usuarios')
        ->call('solicitarCambioEstado', $usuario->id)
        ->assertSet('confirmacion.accion', 'cambiarEstado')
        ->assertSet('confirmacion.boton', 'Desactivar');

    expect($usuario->refresh()->activo)->toBeTrue();

    Volt::test('admin.usuarios')
        ->call('solicitarCambioEstado', $usuario->id)
        ->call('confirmarAccion');

    expect($usuario->refresh()->activo)->toBeFalse();
});

test('only whitelisted actions can run from the confirmation modal', function () {
    $usuario = User::factory()->create();

    Volt::test('admin.usuarios')
        ->set('confirmacion.accion', 'guardar')
        ->set('confirmacion.id', $usuario->id)
        ->call('confirmarAccion')
        ->assertForbidden();
});
