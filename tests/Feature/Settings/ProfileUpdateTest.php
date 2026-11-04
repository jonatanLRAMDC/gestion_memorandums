<?php

use App\Models\User;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Volt\Volt;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')
        ->assertOk()
        ->assertSee($user->email)
        ->assertSee($user->gerencia->nombre);
});

test('profile name can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('settings.profile')
        ->set('name', 'Nombre Actualizado')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->name)->toEqual('Nombre Actualizado');
});

test('users can not change their own email from the profile', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('settings.profile')->set('email', 'otro@amdc.hn');
})->throws(PublicPropertyNotFoundException::class);

test('users can not delete their own account', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/settings/profile')->assertDontSee('Delete account');
});
