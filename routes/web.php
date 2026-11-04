<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware(['auth', 'cuenta.activa'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/password', 'settings.password')->name('settings.password');

    Route::middleware('contrasena.actualizada')->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');

        Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
        Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

        Route::middleware('can:administrar')->prefix('admin')->name('admin.')->group(function () {
            Volt::route('gerencias', 'admin.gerencias')->name('gerencias');
            Volt::route('usuarios', 'admin.usuarios')->name('usuarios');
            Volt::route('bitacora', 'admin.bitacora')->name('bitacora');
        });
    });
});

require __DIR__.'/auth.php';
