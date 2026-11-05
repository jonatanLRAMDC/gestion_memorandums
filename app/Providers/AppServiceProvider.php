<?php

namespace App\Providers;

use App\Enums\Permiso;
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
     * Cada permiso del catálogo es una habilidad que se evalúa con el rol del usuario.
     */
    protected function registrarPermisos(): void
    {
        foreach (Permiso::cases() as $permiso) {
            Gate::define($permiso->value, fn (User $user): bool => $user->tienePermiso($permiso));
        }
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
