<?php

use App\Models\User;

test('the home page sends guests to the login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('the home page sends authenticated users to the dashboard', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
});
