<?php

use App\Models\Bitacora;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        $user = User::query()->where('email', $this->email)->first();

        if ($user && ! $user->puedeIngresar()) {
            Bitacora::registrar('ingreso_rechazado', $user, despues: ['motivo' => $user->estadoCuenta()], usuarioId: $user->id);

            throw ValidationException::withMessages(['email' => __('auth.blocked')]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            $this->registrarIntentoFallido($user);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $user->reiniciarIntentos();

        $destino = $user->debe_cambiar_contrasena
            ? route('settings.password', absolute: false)
            : route('dashboard', absolute: false);

        $this->redirectIntended(default: $destino, navigate: true);
    }

    /**
     * Cuenta el intento fallido y bloquea la cuenta al llegar al límite.
     *
     * @throws ValidationException
     */
    protected function registrarIntentoFallido(?User $user): never
    {
        if (! $user) {
            Bitacora::registrar('ingreso_fallido', despues: ['email' => Str::lower($this->email)]);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $quedoBloqueada = $user->registrarIntentoFallido();

        Bitacora::registrar(
            $quedoBloqueada ? 'cuenta_bloqueada' : 'ingreso_fallido',
            $user,
            despues: ['intentos_fallidos' => $user->intentos_fallidos],
            usuarioId: $user->id,
        );

        throw ValidationException::withMessages([
            'email' => $quedoBloqueada ? __('auth.locked_now') : __('auth.failed'),
        ]);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Iniciar sesión" description="Ingrese su correo institucional y su contraseña" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-5">
        <flux:input wire:model="email" label="Correo electrónico" type="email" name="email" required autofocus autocomplete="email" placeholder="usuario@amdc.hn" />

        <flux:input
            wire:model="password"
            label="Contraseña"
            type="password"
            name="password"
            required
            autocomplete="current-password"
            placeholder="Contraseña"
            viewable
        />

        <flux:checkbox wire:model="remember" label="Recordarme" />

        <flux:button variant="primary" type="submit" class="w-full">Iniciar sesión</flux:button>
    </form>

    <p class="text-center text-sm text-zinc-500 dark:text-zinc-400">
        ¿Olvidó su contraseña? Contacte al administrador del sistema.
    </p>
</div>
