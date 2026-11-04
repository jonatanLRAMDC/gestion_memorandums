<?php

namespace App\Providers;

use App\Http\Middleware\EnsureCuentaActiva;
use App\Models\Bitacora;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registrarPermisos();
        $this->registrarBitacoraDeSesion();

        Livewire::addPersistentMiddleware([EnsureCuentaActiva::class]);
    }

    /**
     * Permisos de los roles Recepción, Consulta y Administrador.
     */
    protected function registrarPermisos(): void
    {
        Gate::define('administrar', fn (User $user): bool => $user->esAdministrador());
        Gate::define('registrar-memos', fn (User $user): bool => $user->puedeRegistrarMemos());
        Gate::define('consultar-memos', fn (User $user): bool => $user->puedeIngresar());
    }

    /**
     * Registra en la bitácora los inicios y cierres de sesión.
     */
    protected function registrarBitacoraDeSesion(): void
    {
        Event::listen(function (Login $event): void {
            Bitacora::registrar('inicio_sesion', $event->user, usuarioId: $event->user->getAuthIdentifier());
        });

        Event::listen(function (Logout $event): void {
            if ($event->user) {
                Bitacora::registrar('cierre_sesion', $event->user, usuarioId: $event->user->getAuthIdentifier());
            }
        });
    }
}
