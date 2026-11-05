<?php

use App\Enums\Permiso;
use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->actingAs(User::factory()->administrador()->create());
});

test('the base roles exist with their default permissions', function () {
    expect(Rol::porSlug(Rol::ADMINISTRADOR)->listaPermisos())->toHaveCount(count(Permiso::cases()))
        ->and(Rol::porSlug(Rol::RECEPCION)->tiene(Permiso::MemorandosRegistrar))->toBeTrue()
        ->and(Rol::porSlug(Rol::CONSULTA)->tiene(Permiso::MemorandosRegistrar))->toBeFalse();
});

test('an administrator creates a role with permissions and a generated slug', function () {
    Volt::test('admin.roles')
        ->call('crear')
        ->set('nombre', 'Usuario de campo')
        ->assertSet('slug', 'usuario-de-campo')
        ->set('permisos', [Permiso::MemorandosConsultar->value, Permiso::GerenciasConsultar->value])
        ->call('guardar')
        ->assertHasNoErrors();

    $rol = Rol::porSlug('usuario-de-campo');

    expect($rol->listaPermisos())->toEqualCanonicalizing(['memorandos.consultar', 'gerencias.consultar'])
        ->and(Bitacora::where('accion', 'rol_creado')->where('entidad_id', $rol->id)->exists())->toBeTrue();
});

test('permissions that are not in the catalog are rejected', function () {
    Volt::test('admin.roles')
        ->call('crear')
        ->set('nombre', 'Rol inválido')
        ->set('permisos', ['sistema.todo'])
        ->call('guardar')
        ->assertHasErrors('permisos.0');
});

test('editing a role changes what its users can do', function () {
    $rol = Rol::factory()->create();
    $usuario = User::factory()->conRol($rol)->create();

    expect($usuario->can('gerencias.consultar'))->toBeFalse();

    Volt::test('admin.roles')
        ->call('editar', $rol->id)
        ->set('permisos', [Permiso::GerenciasConsultar->value])
        ->call('guardar')
        ->assertHasNoErrors();

    expect($usuario->fresh()->can('gerencias.consultar'))->toBeTrue();
});

test('the administrator role can not be edited and protected roles can not be deactivated', function () {
    Volt::test('admin.roles')->call('editar', Rol::porSlug(Rol::ADMINISTRADOR)->id)->assertForbidden();

    $recepcion = Rol::porSlug(Rol::RECEPCION);
    Volt::test('admin.roles')->call('cambiarEstado', $recepcion->id);

    expect($recepcion->refresh()->activo)->toBeTrue();
});

test('the slug of a protected role stays the same', function () {
    $recepcion = Rol::porSlug(Rol::RECEPCION);

    Volt::test('admin.roles')
        ->call('editar', $recepcion->id)
        ->set('nombre', 'Recepción de documentos')
        ->set('slug', 'otro-slug')
        ->call('guardar')
        ->assertHasNoErrors();

    expect($recepcion->refresh()->slug)->toBe(Rol::RECEPCION)
        ->and($recepcion->nombre)->toBe('Recepción de documentos');
});

test('a role with active users can not be deactivated', function () {
    $rol = Rol::factory()->create();
    User::factory()->conRol($rol)->create();

    Volt::test('admin.roles')->call('cambiarEstado', $rol->id);

    expect($rol->refresh()->activo)->toBeTrue();
});

test('users of an inactive role lose their permissions', function () {
    $rol = Rol::factory()->create();
    $rol->sincronizarPermisos([Permiso::MemorandosConsultar->value]);
    $usuario = User::factory()->conRol($rol)->create();

    $rol->update(['activo' => false]);

    expect($usuario->fresh()->can('memorandos.consultar'))->toBeFalse();
});

test('a user without the permission can not open the roles screen', function () {
    $this->actingAs(User::factory()->recepcion()->create())
        ->get(route('admin.roles'))
        ->assertForbidden();
});
