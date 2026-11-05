<?php

use App\Models\Bitacora;
use App\Models\User;
use Livewire\Volt\Volt as LivewireVolt;

test('login screen can be rendered without a sign up link', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee('Iniciar sesión')
        ->assertDontSee('Sign up');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(Bitacora::where('accion', 'inicio_sesion')->where('usuario_id', $user->id)->exists())->toBeTrue();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
    expect($user->refresh()->intentos_fallidos)->toBe(1);
});

test('the account is locked after the configured number of failed attempts', function () {
    config(['auth.max_intentos' => 3]);
    $user = User::factory()->create();

    foreach (range(1, 3) as $intento) {
        LivewireVolt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login');
    }

    $user->refresh();
    expect($user->estaBloqueado())->toBeTrue()
        ->and(Bitacora::where('accion', 'cuenta_bloqueada')->where('entidad_id', $user->id)->exists())->toBeTrue();

    LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email' => __('auth.blocked')]);

    $this->assertGuest();
});

test('a successful login resets the failed attempts counter', function () {
    $user = User::factory()->create(['intentos_fallidos' => 2]);

    LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors();

    expect($user->refresh()->intentos_fallidos)->toBe(0);
});

test('inactive users can not authenticate', function () {
    $user = User::factory()->inactivo()->create();

    LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email' => __('auth.blocked')]);

    $this->assertGuest();
});

test('users with a temporary password are sent to change it after logging in', function () {
    $user = User::factory()->conContrasenaTemporal()->create();

    LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('settings.password', absolute: false));
});

test('users with a temporary password can not use the rest of the system', function () {
    $user = User::factory()->conContrasenaTemporal()->create();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('settings.password'));
    $this->actingAs($user)->get('/settings/password')->assertOk();
});

test('a user deactivated during an active session is logged out', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->update(['activo' => false]);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});
