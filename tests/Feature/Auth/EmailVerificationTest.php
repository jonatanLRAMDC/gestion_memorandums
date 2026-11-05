<?php

use App\Models\User;

test('email verification is not required', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($user)->get('/verify-email')->assertNotFound();
    $this->actingAs($user)->get('/dashboard')->assertOk();
});
