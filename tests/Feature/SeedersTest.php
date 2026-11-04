<?php

use App\Enums\Rol;
use App\Models\Gerencia;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\GerenciaSeeder;

test('the gerencia catalog has 33 entries with unique siglas and GIS as the own gerencia', function () {
    $this->seed(GerenciaSeeder::class);
    $this->seed(GerenciaSeeder::class);

    expect(Gerencia::count())->toBe(33)
        ->and(Gerencia::distinct()->count('siglas'))->toBe(33)
        ->and(Gerencia::propia()->nombre)->toBe('Gerencia de Información y Sistemas')
        ->and(Gerencia::where('usa_sistema', true)->pluck('siglas')->all())->toBe(['GIS']);
});

test('the database seeder creates the initial administrator in GIS', function () {
    config(['memorandos.admin.email' => 'admin@amdc.hn', 'memorandos.admin.password' => null]);

    $this->seed(DatabaseSeeder::class);

    $admin = User::firstWhere('email', 'admin@amdc.hn');

    expect($admin->rol)->toBe(Rol::Administrador)
        ->and($admin->gerencia->siglas)->toBe('GIS')
        ->and($admin->debe_cambiar_contrasena)->toBeTrue();
});
