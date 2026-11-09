<?php

use App\Http\Controllers\MemorandoExportController;
use App\Http\Controllers\MemorandoPdfController;
use App\Http\Controllers\ReporteExportController;
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

        Route::prefix('memorandos')->name('memorandos.')->group(function () {
            Volt::route('/', 'memorandos.index')->middleware('can:memorandos.consultar')->name('index');
            Route::middleware('can:memorandos.exportar')->controller(MemorandoExportController::class)->group(function () {
                Route::get('exportar/excel', 'excel')->name('exportar.excel');
                Route::get('exportar/imprimir', 'imprimir')->name('exportar.imprimir');
            });
            Volt::route('registrar/{tipo}', 'memorandos.registrar')
                ->whereIn('tipo', ['emitido', 'recibido'])
                ->middleware('can:memorandos.registrar')
                ->name('registrar');
            Volt::route('{memorando}', 'memorandos.ver')->whereNumber('memorando')->middleware('can:memorandos.consultar')->name('ver');
            Volt::route('{memorando}/editar', 'memorandos.editar')->whereNumber('memorando')->middleware('can:memorandos.editar')->name('editar');
            Volt::route('{memorando}/reemplazar-pdf', 'memorandos.reemplazar-pdf')->whereNumber('memorando')->middleware('can:memorandos.reemplazar_pdf')->name('reemplazar-pdf');
            Route::get('{memorando}/pdf', MemorandoPdfController::class)->whereNumber('memorando')->name('pdf');
        });

        Route::prefix('reportes')->name('reportes.')->middleware('can:reportes.consultar')->group(function () {
            Volt::route('/', 'reportes.index')->name('index');
            Route::get('exportar/excel', [ReporteExportController::class, 'excel'])->name('exportar.excel');
            Route::get('exportar/imprimir', [ReporteExportController::class, 'imprimir'])->name('exportar.imprimir');
        });

        Route::prefix('admin')->name('admin.')->group(function () {
            Volt::route('gerencias', 'admin.gerencias')->middleware('can:gerencias.consultar')->name('gerencias');
            Volt::route('usuarios', 'admin.usuarios')->middleware('can:usuarios.consultar')->name('usuarios');
            Volt::route('roles', 'admin.roles')->middleware('can:roles.consultar')->name('roles');
            Volt::route('bitacora', 'admin.bitacora')->middleware('can:bitacora.consultar')->name('bitacora');
        });
    });
});

require __DIR__.'/auth.php';
