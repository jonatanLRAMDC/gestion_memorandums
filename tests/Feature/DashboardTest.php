<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});

test('the sidebar shows the current user and the logout button outside of a dropdown', function () {
    $user = User::factory()->create(['name' => 'María Mejía']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('data-usuario-actual', false)
        ->assertSee('María Mejía')
        ->assertSee($user->email)
        ->assertSee('Cerrar sesión')
        ->assertSee('collapsible="true"', false);
});
