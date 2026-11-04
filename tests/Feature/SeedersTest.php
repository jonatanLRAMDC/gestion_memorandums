<?php

use App\Models\Gerencia;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\GerenciaSeeder;
use Database\Seeders\TitularesPruebaSeeder;

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

    expect($admin->esAdministrador())->toBeTrue()
        ->and($admin->gerencia->siglas)->toBe('GIS')
        ->and($admin->debe_cambiar_contrasena)->toBeTrue();
});

test('test titulares fill every gerencia without overwriting existing ones', function () {
    $this->seed(GerenciaSeeder::class);
    Gerencia::where('siglas', 'GRH')->update(['titular_nombre' => 'Titular real']);

    $this->seed(TitularesPruebaSeeder::class);

    expect(Gerencia::whereNull('titular_nombre')->count())->toBe(0)
        ->and(Gerencia::firstWhere('siglas', 'GRH')->titular_nombre)->toBe('Titular real')
        ->and(Gerencia::firstWhere('siglas', 'GIS')->titular_nombre)->toBe('Ing. Roberto Euceda');
});
