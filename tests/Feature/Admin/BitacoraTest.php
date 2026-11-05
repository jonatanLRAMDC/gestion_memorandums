<?php

use App\Models\Bitacora;
use App\Models\User;

test('administrators can read the audit log', function () {
    $usuario = User::factory()->create(['name' => 'Usuario Auditado']);
    Bitacora::registrar('inicio_sesion', $usuario, usuarioId: $usuario->id);

    $this->actingAs(User::factory()->administrador()->create())
        ->get(route('admin.bitacora'))
        ->assertOk()
        ->assertSee('Usuario Auditado')
        ->assertSee('Inicio de sesión');
});

test('audit log entries can not be changed or deleted', function () {
    $entrada = Bitacora::registrar('inicio_sesion');

    expect(fn () => $entrada->update(['accion' => 'otra']))->toThrow(LogicException::class)
        ->and(fn () => $entrada->delete())->toThrow(LogicException::class);
});
