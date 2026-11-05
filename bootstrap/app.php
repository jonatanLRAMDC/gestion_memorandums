<?php

use App\Http\Middleware\EnsureContrasenaActualizada;
use App\Http\Middleware\EnsureCuentaActiva;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'cuenta.activa' => EnsureCuentaActiva::class,
            'contrasena.actualizada' => EnsureContrasenaActualizada::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
