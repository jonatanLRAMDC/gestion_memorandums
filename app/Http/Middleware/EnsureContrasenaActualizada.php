<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureContrasenaActualizada
{
    /**
     * Obliga a cambiar la contraseña temporal antes de usar el resto del sistema.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->debe_cambiar_contrasena) {
            return redirect()->route('settings.password')->with('status', __('auth.must_change_password'));
        }

        return $next($request);
    }
}
