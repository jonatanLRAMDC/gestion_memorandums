# Arquitectura actual — Gestión de Memorandos (GIS · AMDC)

Informe del estado del proyecto **antes** de crear migraciones, modelos, seeders y pantallas propias.
Fecha del análisis: 03/11/2026. El análisis fue de solo lectura: el único archivo creado es este.

> Convención: cada dato indica su **fuente** (archivo o comando). "No determinado" indica que el dato
> no se pudo comprobar con los comandos permitidos y explica cómo verificarlo.

---

## 1. Versiones

| Herramienta | Versión | Fuente |
|---|---|---|
| PHP (CLI) | 8.2.12 (ZTS, VC++ 2019 x64, XAMPP) | `php -v` |
| Laravel Framework | 12.70.2 | `php artisan --version`, `php artisan about` |
| Composer | 2.10.2 | `composer --version` |
| Node.js | 24.19.0 | `node -v` |
| npm | 11.17.0 | `npm -v` |
| Livewire (dependencia indirecta) | 4.4.8 | `php artisan about` (sección Livewire) |

Restricción declarada en `composer.json`: `"php": "^8.2"`. Los workflows de GitHub Actions usan PHP 8.4 y Node 22 (`.github/workflows/tests.yml`), distintos del entorno local.

---

## 2. Starter kit

**Starter kit: Livewire** (versión oficial `laravel/livewire-starter-kit`, variante con **Volt** y **Flux**, sin Fortify).

Cómo se identificó:
- `composer.json` → `"name": "laravel/livewire-starter-kit"`, `"description": "The official Laravel starter kit for Livewire."`
- `composer.json` → `require` incluye `livewire/flux` y `livewire/volt`.
- Rutas de autenticación definidas con `Volt::route(...)` en `routes/auth.php`.
- Pantallas como componentes Volt de archivo único en `resources/views/livewire/auth/*` y `resources/views/livewire/settings/*`.
- No hay `inertia`, `react` ni `vue` en `package.json`; no existe `resources/js/Pages`.

Corresponde a la versión **temprana** del kit (archivos de febrero 2025): la autenticación vive en los propios componentes Volt, no en Laravel Fortify. Las dependencias se resolvieron después a versiones más nuevas (Livewire 4.4.8, Flux 2.21.0, Volt 1.11.2), compatibles según `vendor/livewire/volt/composer.json` (`livewire/livewire: ^3.6.1|^4.0`) y `vendor/livewire/flux/composer.json` (`^3.7.4|^4.0`).

### Tecnologías de interfaz

| Tecnología | ¿Instalada? | Versión | Fuente |
|---|---|---|---|
| Inertia.js | No | — | `composer show --direct`, `package.json` |
| Livewire | Sí (indirecta, vía Flux/Volt) | 4.4.8 | `php artisan about` |
| Livewire Volt | Sí | 1.11.2 | `composer show --direct` |
| Flux UI (gratuito) | Sí | 2.21.0 | `composer show --direct`; `vendor/livewire` contiene solo `flux`, `livewire`, `volt` (no `flux-pro`) |
| shadcn/ui | No | — | `package.json` (no hay React/Vue) |
| Tailwind CSS | Sí | 4.0.8 (plugin `@tailwindcss/vite` 4.0.8) | `npm ls --depth=0` |
| Vite | Sí | 6.1.1 (`laravel-vite-plugin` 1.2.0) | `npm ls --depth=0`, `vite.config.js` |
| TypeScript | No | — | `package.json`; no existe `tsconfig.json`; `resources/js/app.js` está vacío (0 bytes) |
| Íconos | Heroicons (incluidos en Flux) + 4 íconos Lucide copiados localmente | — | Uso de `icon="home"`, `icon="cog"`, etc. en layouts; `resources/views/flux/icon/*.blade.php` (comentario "Credit: Lucide") |
| Alpine.js | Sí (incluido en Livewire) | No determinado (empaquetado en Livewire; verificar con `grep -i "alpine" vendor/livewire/livewire/dist/livewire.js \| head`) | `x-data`, `x-model` en `settings/appearance.blade.php` |
| Fuente tipográfica | Instrument Sans desde fonts.bunny.net | — | `resources/views/partials/head.blade.php` |

---

## 3. Dependencias directas

### composer.json — `require`

| Paquete | Restricción | Instalada | Para qué sirve |
|---|---|---|---|
| php | ^8.2 | 8.2.12 | Versión mínima de PHP |
| laravel/framework | ^12.0 | 12.70.2 | Framework base |
| laravel/tinker | ^2.10.1 | 2.11.1 | Consola REPL (`php artisan tinker`) |
| livewire/flux | ^2.0 | 2.21.0 | Biblioteca de componentes UI `<flux:*>` |
| livewire/volt | ^1.6.7 | 1.11.2 | Componentes Livewire de archivo único (PHP + Blade) |

### composer.json — `require-dev`

| Paquete | Restricción | Instalada | Para qué sirve |
|---|---|---|---|
| fakerphp/faker | ^1.23 | 1.24.1 | Datos falsos para factories y pruebas |
| laravel/boost | ^2.2 | 2.10.4 | Servidor MCP y guías para desarrollo asistido por IA |
| laravel/pail | ^1.2.2 | 1.2.7 | Ver logs en tiempo real (`php artisan pail`) |
| laravel/pint | ^1.18 | 1.30.4 | Formateador de código PHP |
| laravel/sail | ^1.41 | 1.69.0 | Entorno Docker (no se usa con XAMPP; `boost.json` → `"sail": false`) |
| mockery/mockery | ^1.6 | 1.6.15 | Dobles de prueba (mocks) |
| nunomaduro/collision | ^8.6 | 8.9.5 | Presentación de errores en consola |
| pestphp/pest | ^3.8 | 3.8.7 | Framework de pruebas |
| pestphp/pest-plugin-laravel | ^3.2 | 3.2.0 | Integración de Pest con Laravel |

Fuente: `composer.json` y `composer show --direct`.

### package.json — `dependencies`

| Paquete | Restricción | Instalada | Para qué sirve |
|---|---|---|---|
| @tailwindcss/vite | ^4.0.7 | 4.0.8 | Plugin de Tailwind 4 para Vite |
| autoprefixer | ^10.4.20 | 10.4.20 | Prefijos CSS (no se usa en `vite.config.js`; Tailwind 4 ya los maneja) |
| axios | ^1.7.4 | 1.7.9 | Cliente HTTP JS (no se importa: `app.js` está vacío) |
| concurrently | ^9.0.1 | 9.1.2 | Ejecuta varios procesos en `composer run dev` |
| laravel-vite-plugin | ^1.0 | 1.2.0 | Integración Vite ↔ Laravel (`@vite`) |
| tailwindcss | ^4.0.7 | 4.0.8 | Framework CSS |
| vite | ^6.0 | 6.1.1 | Empaquetador de recursos |

### package.json — `optionalDependencies`

| Paquete | Restricción | Estado | Para qué sirve |
|---|---|---|---|
| @rollup/rollup-linux-x64-gnu | 4.9.5 | No instalada (UNMET OPTIONAL, normal en Windows) | Binario de Rollup para Linux (CI) |
| @tailwindcss/oxide-linux-x64-gnu | ^4.0.1 | No instalada (normal en Windows) | Motor de Tailwind para Linux |
| lightningcss-linux-x64-gnu | ^1.29.1 | No instalada (normal en Windows) | Procesador CSS para Linux |

No hay `devDependencies` en `package.json`. Fuente: `package.json`, `npm ls --depth=0`.

---

## 4. Autenticación

**Qué la implementa:** componentes **Volt propios del starter kit** (no Fortify, no Breeze, no Jetstream). Usan directamente `Auth::attempt`, `RateLimiter`, `Password` broker y `Hash` de Laravel. Guard `web` con driver `session` y proveedor Eloquent `App\Models\User` (`config/auth.php`).

### Funciones

| Función | Estado | Detalle | Fuente |
|---|---|---|---|
| Registro público | **Activo** | Ruta `GET /register` (guest); crea usuario e inicia sesión | `routes/auth.php`, `resources/views/livewire/auth/register.blade.php` |
| Inicio de sesión | **Activo** | `GET /login`; email + contraseña | `resources/views/livewire/auth/login.blade.php` |
| Recordarme | **Activo** | Checkbox `remember` → `Auth::attempt(..., $this->remember)`; columna `remember_token` | `login.blade.php`, migración de users |
| Recuperación de contraseña por correo | **Activa** | `/forgot-password` y `/reset-password/{token}`; token expira en 60 min, reenvío limitado a 60 s. Con `MAIL_MAILER=log` el correo solo se escribe en el log | `routes/auth.php`, `forgot-password.blade.php`, `reset-password.blade.php`, `config/auth.php` (`passwords.users`) |
| Verificación de correo | **Rutas presentes, pero inactiva en la práctica** | Existen `/verify-email` y `/verify-email/{id}/{hash}`, y `dashboard` usa middleware `verified`; pero `User` **no** implementa `MustVerifyEmail` (línea comentada), por lo que el middleware deja pasar a todos | `app/Models/User.php`, `routes/web.php`, `app/Http/Controllers/Auth/VerifyEmailController.php` |
| Confirmación de contraseña | **Pantalla presente, no aplicada** | Existe `/confirm-password` (`password.confirm`), pero ninguna ruta usa el middleware `password.confirm`. Vigencia: 10 800 s (3 h) | `routes/auth.php`, `config/auth.php` (`password_timeout`) |
| Autenticación en dos pasos (2FA) | **No existe** | No hay Fortify, columnas `two_factor_*` ni pantallas | `composer show --direct`, migraciones |
| Perfil (nombre y correo) | **Activo** | `/settings/profile`; si cambia el correo, anula `email_verified_at` | `resources/views/livewire/settings/profile.blade.php` |
| Cambio de contraseña | **Activo** | `/settings/password`; exige contraseña actual y `Password::defaults()` | `resources/views/livewire/settings/password.blade.php` |
| Eliminar la propia cuenta | **Activo** | Modal dentro de Perfil; borra el usuario tras pedir contraseña | `resources/views/livewire/settings/delete-user-form.blade.php` |
| Apariencia (claro/oscuro/sistema) | **Activo** | `/settings/appearance` | `resources/views/livewire/settings/appearance.blade.php` |
| Cerrar sesión | **Activo** | `POST /logout` → invalida sesión y regenera token | `app/Livewire/Actions/Logout.php` |

### Límite de intentos de inicio de sesión

- **Dónde:** `resources/views/livewire/auth/login.blade.php`, método `ensureIsNotRateLimited()`.
- **Cuántos:** **5 intentos** (`RateLimiter::tooManyAttempts($this->throttleKey(), 5)`).
- **Ventana:** 60 segundos (valor por defecto de `RateLimiter::hit($key, $decaySeconds = 60)` en `vendor/laravel/framework/src/Illuminate/Cache/RateLimiter.php`).
- **Clave:** `email en minúsculas | IP` (`throttleKey()`). Se limpia al iniciar sesión con éxito.
- **Almacenamiento:** caché (`CACHE_STORE=database` → tabla `cache`). Es un freno temporal por email+IP, **no** un bloqueo persistente de la cuenta.
- Al exceder el límite se dispara el evento `Illuminate\Auth\Events\Lockout`.
- Otros límites: verificación de correo `throttle:6,1` (`routes/auth.php`); subida temporal de archivos Livewire `throttle:60,1` (`php artisan route:list -v`).

### Archivos involucrados

```
routes/auth.php
routes/web.php
config/auth.php
app/Models/User.php
app/Http/Controllers/Auth/VerifyEmailController.php
app/Livewire/Actions/Logout.php
resources/views/livewire/auth/login.blade.php
resources/views/livewire/auth/register.blade.php
resources/views/livewire/auth/forgot-password.blade.php
resources/views/livewire/auth/reset-password.blade.php
resources/views/livewire/auth/verify-email.blade.php
resources/views/livewire/auth/confirm-password.blade.php
resources/views/livewire/settings/profile.blade.php
resources/views/livewire/settings/password.blade.php
resources/views/livewire/settings/delete-user-form.blade.php
resources/views/components/layouts/auth.blade.php (+ auth/simple, auth/card, auth/split)
database/migrations/0001_01_01_000000_create_users_table.php (users, password_reset_tokens, sessions)
tests/Feature/Auth/*.php, tests/Feature/Settings/*.php
```

---

## 5. Estructura (hasta 3 niveles)

Fuente: `find <carpeta> -maxdepth 3` (sin `vendor` ni `node_modules`).

```
app/
├── Http/
│   └── Controllers/
│       ├── Auth/                      (VerifyEmailController.php)
│       └── Controller.php
├── Livewire/
│   └── Actions/
│       └── Logout.php
├── Models/
│   └── User.php
└── Providers/
    ├── AppServiceProvider.php
    └── VoltServiceProvider.php

bootstrap/
├── app.php
├── providers.php
└── cache/
    ├── packages.php
    └── services.php

config/
├── app.php  auth.php  cache.php  database.php  filesystems.php
└── logging.php  mail.php  queue.php  services.php  session.php

database/
├── database.sqlite
├── factories/
│   └── UserFactory.php
├── migrations/
│   ├── 0001_01_01_000000_create_users_table.php
│   ├── 0001_01_01_000001_create_cache_table.php
│   └── 0001_01_01_000002_create_jobs_table.php
└── seeders/
    └── DatabaseSeeder.php

resources/
├── css/
│   └── app.css
├── js/
│   └── app.js                         (vacío)
└── views/
    ├── components/
    │   ├── layouts/                   (app.blade.php, app/, auth.blade.php, auth/)
    │   ├── settings/                  (layout.blade.php)
    │   ├── action-message.blade.php
    │   ├── app-logo.blade.php
    │   ├── app-logo-icon.blade.php
    │   ├── auth-header.blade.php
    │   ├── auth-session-status.blade.php
    │   ├── placeholder-pattern.blade.php
    │   └── text-link.blade.php
    ├── flux/
    │   ├── icon/                      (book-open-text, chevrons-up-down, folder-git-2, layout-grid)
    │   └── navlist/                   (group.blade.php)
    ├── livewire/
    │   ├── auth/                      (login, register, forgot-password, reset-password, verify-email, confirm-password)
    │   └── settings/                  (profile, password, appearance, delete-user-form)
    ├── partials/
    │   ├── head.blade.php
    │   └── settings-heading.blade.php
    ├── dashboard.blade.php
    └── welcome.blade.php

routes/
├── auth.php
├── console.php
└── web.php

tests/
├── Feature/
│   ├── Auth/                          (Authentication, EmailVerification, PasswordConfirmation, PasswordReset, Registration)
│   ├── Settings/                      (PasswordUpdate, ProfileUpdate)
│   ├── DashboardTest.php
│   └── ExampleTest.php
├── Unit/
│   └── ExampleTest.php
├── Pest.php
└── TestCase.php
```

Observaciones:
- **No existe la carpeta `lang/`** (`ls lang` → no existe).
- **No existe `config/livewire.php`** (configuración de Livewire sin publicar; usa valores por defecto).
- **`bootstrap/providers.php` registra `App\Providers\FolioServiceProvider`, pero ese archivo no existe** y Laravel Folio no está instalado (`ls vendor/laravel`). No falla porque `RegisterProviders::mergeAdditionalProviders()` descarta clases inexistentes (`vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/RegisterProviders.php`, línea 52). Es una línea sobrante.
- `VoltServiceProvider` monta `resources/views/livewire` y `resources/views/pages` (esta última no existe).

---

## 6. Base de datos

### Motor configurado

| Dato | Valor | Fuente |
|---|---|---|
| Conexión | `sqlite` | `.env` (`DB_CONNECTION`), `php artisan about` |
| Archivo | `database/database.sqlite` (valor por defecto; `DB_DATABASE` está comentado en `.env`) | `config/database.php` línea 37 |
| Estado de migraciones | Las 3 migraciones ejecutadas (lote 1) | `php artisan migrate:status` |
| Pruebas | SQLite en memoria (`:memory:`) | `phpunit.xml` |

> SQLite sirve para desarrollo. Si producción usará MySQL/MariaDB (XAMPP) u otro motor, debe definirse antes de escribir migraciones (no determinado: confirmar con el equipo de infraestructura de la GIS).

### Migraciones y tablas

| Archivo | Tablas que crea |
|---|---|
| `0001_01_01_000000_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` |
| `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks` |
| `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` |

### Columnas de `users`

Fuente: `database/migrations/0001_01_01_000000_create_users_table.php`.

| Columna | Tipo | Restricciones |
|---|---|---|
| id | bigint autoincremental (`id()`) | PK |
| name | string(255) | requerido |
| email | string(255) | único |
| email_verified_at | timestamp | nullable |
| password | string(255) | requerido |
| remember_token | string(100) (`rememberToken()`) | nullable |
| created_at / updated_at | timestamp (`timestamps()`) | nullable |

### Modelos, factories y seeders

| Tipo | Archivo | Contenido |
|---|---|---|
| Modelo | `app/Models/User.php` | `$fillable = [name, email, password]`; `$hidden = [password, remember_token]`; `casts()` → `email_verified_at: datetime`, `password: hashed`; método `initials()` |
| Factory | `database/factories/UserFactory.php` | Contraseña `password`; estado `unverified()` |
| Seeder | `database/seeders/DatabaseSeeder.php` | Crea un usuario "Test User" (`test@example.com`) |

---

## 7. Rutas

Fuente: `php artisan route:list --except-vendor` (5 rutas con closure/controlador propio) y `php artisan route:list -v` (36 rutas en total, incluidas las Volt y las de paquetes). Todas las rutas de aplicación llevan el grupo de middleware `web`.

### `routes/web.php`

| Método | URI | Nombre | Destino | Middleware |
|---|---|---|---|---|
| GET | `/` | home | vista `welcome` | web |
| GET | `/dashboard` | dashboard | vista `dashboard` | web, auth, verified |
| ANY | `/settings` | — | redirección a `settings/profile` | web, auth |
| GET | `/settings/profile` | settings.profile | Volt `settings.profile` | web, auth |
| GET | `/settings/password` | settings.password | Volt `settings.password` | web, auth |
| GET | `/settings/appearance` | settings.appearance | Volt `settings.appearance` | web, auth |

### `routes/auth.php`

| Método | URI | Nombre | Destino | Middleware |
|---|---|---|---|---|
| GET | `/login` | login | Volt `auth.login` | web, guest |
| GET | `/register` | register | Volt `auth.register` | web, guest |
| GET | `/forgot-password` | password.request | Volt `auth.forgot-password` | web, guest |
| GET | `/reset-password/{token}` | password.reset | Volt `auth.reset-password` | web, guest |
| GET | `/verify-email` | verification.notice | Volt `auth.verify-email` | web, auth |
| GET | `/verify-email/{id}/{hash}` | verification.verify | `Auth\VerifyEmailController` | web, auth, signed, throttle:6,1 |
| GET | `/confirm-password` | password.confirm | Volt `auth.confirm-password` | web, auth |
| POST | `/logout` | logout | `App\Livewire\Actions\Logout` | web |

### `routes/console.php`

| Comando | Programación |
|---|---|
| `inspire` | `hourly()` (ejemplo del framework) |

### Rutas de paquetes / framework (relevantes)

| URI | Origen | Nota |
|---|---|---|
| `/up` | `bootstrap/app.php` (`health`) | Chequeo de salud |
| `GET/PUT /storage/{path}` (`storage.local`, `storage.local.upload`) | `FilesystemServiceProvider` porque el disco `local` tiene `'serve' => true` | Sirve archivos privados **solo con URL firmada**; ver brecha 11.f |
| `/livewire-*/update`, `/livewire-*/upload-file`, `/livewire-*/preview-file/{filename}` | Livewire | Actualizaciones y subidas temporales |
| `/flux/*` | Flux | JS/CSS de Flux |
| `POST /_boost/browser-logs` | Laravel Boost (solo desarrollo) | Logs del navegador |

---

## 8. Interfaz

### Layouts

| Layout | Archivo | Uso |
|---|---|---|
| App con **barra lateral** (por defecto) | `resources/views/components/layouts/app/sidebar.blade.php` | `components/layouts/app.blade.php` lo envuelve con `<flux:main>` |
| App con **encabezado** (navbar superior) | `resources/views/components/layouts/app/header.blade.php` | Disponible, no usado (cambiar en `layouts/app.blade.php`) |
| Autenticación **simple** (por defecto) | `resources/views/components/layouts/auth/simple.blade.php` | `components/layouts/auth.blade.php` lo envuelve |
| Autenticación **tarjeta** | `resources/views/components/layouts/auth/card.blade.php` | Disponible, no usado |
| Autenticación **dividida** | `resources/views/components/layouts/auth/split.blade.php` | Disponible, no usado |
| Ajustes | `resources/views/components/settings/layout.blade.php` | Submenú Perfil / Contraseña / Apariencia |

La barra lateral contiene el grupo "Platform" → "Dashboard", enlaces externos al repositorio del kit y a la documentación, y el menú de usuario (Settings, Log out). Textos en inglés y sin `__()`.

### Componentes de UI

- **Flux (gratuito)** usados: `sidebar`, `sidebar.toggle`, `navlist`, `navlist.group`, `navlist.item`, `navbar`, `header`, `main`, `spacer`, `dropdown`, `profile`, `menu`, `menu.item`, `menu.radio.group`, `menu.separator`, `navbar.item`, `separator`, `tooltip`, `input`, `checkbox`, `button`, `radio.group`, `radio`, `modal`, `modal.trigger`, `modal.close`, `heading`, `subheading`, `icon.*`. (Fuente: `grep -rhoE "<flux:[a-z.\-]+" resources/views`.)
- **Componentes Blade propios** (`resources/views/components/`): `action-message`, `app-logo`, `app-logo-icon`, `auth-header`, `auth-session-status`, `placeholder-pattern`, `text-link`.
- **Sobrescrituras de Flux** (`resources/views/flux/`): 4 íconos Lucide y `navlist/group.blade.php`.
- No hay componentes de tabla, paginación, carga de archivos ni selector de fechas personalizados aún. Flux Pro (tablas, date-picker, etc.) **no** está instalado.

### Páginas / vistas existentes

`welcome` (página de bienvenida de Laravel con enlaces a Log in / Register), `dashboard` (marcadores de posición), 6 pantallas de autenticación y 4 de ajustes (ver sección 4).

### Modo oscuro

- Variante `dark` basada en clase: `@custom-variant dark (&:where(.dark, .dark *));` en `resources/css/app.css`.
- Los layouts traen `<html ... class="dark">` fijo, pero `@fluxAppearance` (en `partials/head.blade.php`) aplica la preferencia del usuario (claro / oscuro / sistema) guardada por Flux en el navegador; se cambia en `/settings/appearance`.

### Estilos y colores del tema

- Archivo principal: **`resources/css/app.css`** (entradas de Vite: `resources/css/app.css` y `resources/js/app.js`, según `vite.config.js`).
- Importa `tailwindcss` y `vendor/livewire/flux/dist/flux.css`.
- Colores del tema en el bloque **`@theme`** de `resources/css/app.css`: escala `--color-zinc-*` (redefinida a grises neutros) y `--color-accent`, `--color-accent-content`, `--color-accent-foreground`. Sus valores para modo oscuro están en `@layer theme { .dark { ... } }` del mismo archivo.
- Fuente: `--font-sans: 'Instrument Sans', ...`.

---

## 9. Configuración

### Valores no sensibles de `.env`

| Variable | Valor | Observación |
|---|---|---|
| APP_ENV | `local` | |
| APP_DEBUG | `true` | |
| APP_LOCALE | `en` | |
| APP_FALLBACK_LOCALE | `en` | |
| APP_FAKER_LOCALE | `en_US` | |
| APP_TIMEZONE | **No existe en `.env`** | `config/app.php` tiene `'timezone' => 'UTC'` **fijo**, no lee ninguna variable |
| DB_CONNECTION | `sqlite` | |
| FILESYSTEM_DISK | `local` | |
| SESSION_DRIVER | `database` | `SESSION_LIFETIME=120` |
| QUEUE_CONNECTION | `database` | |
| CACHE_STORE | `database` | |
| MAIL_MAILER | `log` | Los correos se escriben en `storage/logs` |
| BROADCAST_CONNECTION | `log` | |
| BCRYPT_ROUNDS | `12` | |

Nombres de las demás variables presentes (sin valores): `APP_NAME, APP_KEY, APP_URL, APP_MAINTENANCE_DRIVER, PHP_CLI_SERVER_WORKERS, LOG_CHANNEL, LOG_STACK, LOG_DEPRECATIONS_CHANNEL, LOG_LEVEL, SESSION_ENCRYPT, SESSION_PATH, SESSION_DOMAIN, CACHE_PREFIX, MEMCACHED_HOST, REDIS_CLIENT, REDIS_HOST, REDIS_PASSWORD, REDIS_PORT, MAIL_SCHEME, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET, AWS_USE_PATH_STYLE_ENDPOINT, VITE_APP_NAME`. Comentadas: `DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_MAINTENANCE_STORE`.

### Idioma

| Dato | Valor | Fuente |
|---|---|---|
| `app.locale` | `env('APP_LOCALE', 'en')` → `en` | `config/app.php` línea 81 |
| `app.fallback_locale` | `env('APP_FALLBACK_LOCALE', 'en')` → `en` | `config/app.php` línea 83 |
| `app.faker_locale` | `en_US` | `config/app.php` línea 85 |
| Carpeta `lang/` | **No existe** (solo traducciones `en` internas del framework) | `ls lang`; `vendor/laravel/framework/src/Illuminate/Translation/lang` |

### Zona horaria

`UTC`, fija en `config/app.php` línea 68. Confirmado por `php artisan about` (Timezone: UTC).

### Almacenamiento (`config/filesystems.php`)

| Disco | Driver | Raíz | Notas |
|---|---|---|---|
| `local` (por defecto) | local | `storage/app/private` | `'serve' => true` → publica `/storage/{path}` con URL firmada |
| `public` | local | `storage/app/public` | URL `APP_URL/storage`, visibilidad pública. Enlace simbólico **no creado** (`php artisan about` → NOT LINKED) |
| `s3` | s3 | — | Sin credenciales configuradas |

### Sesión, colas, caché y correo

| Servicio | Driver | Detalle | Fuente |
|---|---|---|---|
| Sesión | `database` (tabla `sessions`) | 120 min, sin cifrar, `http_only=true`, `same_site=lax`, `secure` no definido | `config/session.php`, `.env` |
| Colas | `database` (tabla `jobs`) | `retry_after` 90 s; fallidos en `failed_jobs` | `config/queue.php` |
| Caché | `database` (tabla `cache`) | | `config/cache.php` |
| Correo | `log` | Remitente por defecto `hello@example.com` / `Example` si no se definen variables | `config/mail.php` |

---

## 10. Calidad

| Herramienta | Estado | Fuente |
|---|---|---|
| Framework de pruebas | **Pest 3.8.7** (sobre PHPUnit) con `pest-plugin-laravel` | `composer show --direct`, `tests/Pest.php` |
| Configuración de pruebas | `tests/Pest.php` aplica `TestCase` + `RefreshDatabase` a `Feature`; BD SQLite en memoria | `tests/Pest.php`, `phpunit.xml` |
| Pint | Instalado (1.30.4), **sin `pint.json`** (usa preset `laravel` por defecto) | `composer show --direct`, `ls pint.json` |
| ESLint | **No instalado** | `package.json`, no hay `eslint.config.*` |
| Prettier | **No instalado** | `package.json`, no hay `.prettierrc*` |
| EditorConfig | Sí (`.editorconfig`: UTF-8, LF, 4 espacios; YAML 2) | `.editorconfig` |
| CI | `.github/workflows/tests.yml` (Pest) y `.github/workflows/lint.yml` (Pint); ambos con PHP 8.4 y credenciales Flux (no necesarias para Flux gratuito) | `.github/workflows/` |

### Pruebas existentes (27 pruebas en 10 archivos)

| Archivo | Pruebas |
|---|---|
| `tests/Feature/Auth/AuthenticationTest.php` | 4 (pantalla login, login válido, contraseña inválida, logout) |
| `tests/Feature/Auth/EmailVerificationTest.php` | 3 |
| `tests/Feature/Auth/PasswordConfirmationTest.php` | 3 |
| `tests/Feature/Auth/PasswordResetTest.php` | 4 |
| `tests/Feature/Auth/RegistrationTest.php` | 2 |
| `tests/Feature/Settings/PasswordUpdateTest.php` | 2 |
| `tests/Feature/Settings/ProfileUpdateTest.php` | 5 (incluye borrado de cuenta) |
| `tests/Feature/DashboardTest.php` | 2 |
| `tests/Feature/ExampleTest.php` | 1 |
| `tests/Unit/ExampleTest.php` | 1 |

Resultado actual de la suite: **No determinado** (ejecutar pruebas no estaba entre los comandos permitidos). Verificar con `php artisan test --compact`.

### Scripts

| Origen | Script | Comando |
|---|---|---|
| composer | `dev` | `concurrently`: `php artisan serve`, `queue:listen --tries=1`, `pail --timeout=0`, `npm run dev` |
| composer | `post-update-cmd` | publica assets de Laravel y ejecuta `boost:update` |
| composer | `post-create-project-cmd` | `key:generate`, crea `database.sqlite`, `migrate` |
| composer | `test` / `lint` | **No existen** |
| npm | `dev` | `vite` |
| npm | `build` | `vite build` |
| npm | `test` / `lint` | **No existen** |

---

## 11. Brechas frente a los requerimientos

### a. Quitar el registro público y la recuperación de contraseña por correo

- **Estado:** ambas funciones **están activas**.
- **Falta:** eliminar rutas, vistas y enlaces; ajustar pruebas.
- **Archivos:**
  - `routes/auth.php`: quitar `register`, `forgot-password`, `reset-password/{token}`.
  - Eliminar `resources/views/livewire/auth/register.blade.php`, `forgot-password.blade.php`, `reset-password.blade.php`.
  - `resources/views/livewire/auth/login.blade.php` línea 114: el enlace "Sign up" usa `route('register')` **sin** `Route::has` → la pantalla de login fallaría (`RouteNotFoundException`) si solo se borra la ruta. El enlace "Forgot your password?" sí está protegido con `Route::has('password.request')`.
  - `resources/views/welcome.blade.php`: enlace a register (protegido con `Route::has`); conviene redirigir `/` al login o al dashboard.
  - Pruebas: `tests/Feature/Auth/RegistrationTest.php` y `PasswordResetTest.php` deben reemplazarse por pruebas que confirmen que esas rutas ya no existen (borrar pruebas requiere aprobación).
  - Opcional: tabla `password_reset_tokens` (puede quedarse o eliminarse con una migración nueva).
  - Relacionado: decidir si se quitan también la **verificación de correo** y el **borrado de la propia cuenta** (`delete-user-form.blade.php`, incluido desde `settings/profile.blade.php` línea 115), poco adecuados en un sistema interno con usuarios creados por el Administrador y bitácora.

### b. Campos extra en `users`

- **Estado:** no existe ninguno de los campos; no existen tablas `gerencias` ni enum de roles.
- **Falta:** `gerencia_id` (FK a `gerencias`), `rol` (Recepción, Consulta, Administrador), `activo` (bool, default true), `intentos_fallidos` (entero, default 0), `bloqueado_en` (timestamp nullable), `debe_cambiar_contrasena` (bool).
- **Archivos:**
  - Nueva migración `create_gerencias_table` (debe ir antes) y nueva migración `add_..._to_users_table` (no editar la migración base si ya se usa en otros entornos).
  - Nuevo modelo `app/Models/Gerencia.php` + factory + seeder.
  - Nuevo enum `app/Enums/Rol.php` (claves TitleCase: `Recepcion`, `Consulta`, `Administrador`).
  - `app/Models/User.php`: `$fillable`, `casts()` (`rol` → enum, `activo` y `debe_cambiar_contrasena` → boolean, `bloqueado_en` → datetime), relación `gerencia()`.
  - `database/factories/UserFactory.php`: valores por defecto y estados (`administrador()`, `bloqueado()`, `inactivo()`…).
  - `database/seeders/DatabaseSeeder.php`: Administrador inicial y gerencias.
  - Para `debe_cambiar_contrasena`: middleware nuevo (p. ej. `app/Http/Middleware/...`) registrado en `bootstrap/app.php` que redirija a `settings.password`, y `settings/password.blade.php` que ponga el campo en `false` al cambiarla.

### c. Bloqueo tras N intentos fallidos y bloqueo manual

- **Estado:** solo existe el freno temporal de 5 intentos por email+IP durante 60 s (sección 4). **No hay bloqueo persistente** ni bloqueo manual.
- **Falta:**
  - Parámetro configurable: p. ej. `AUTH_MAX_INTENTOS=5` en `.env` / `.env.example`, leído en `config/auth.php` (nunca `env()` fuera de config).
  - En el login: rechazar si `activo = false` o `bloqueado_en` no es nulo; al fallar, incrementar `intentos_fallidos` del usuario con ese correo y fijar `bloqueado_en` al llegar a N; al acertar, reiniciar a 0. Mensaje genérico que no revele si el correo existe.
  - Cerrar sesiones activas de usuarios bloqueados o inactivos (middleware en cada petición autenticada).
  - Pantalla del Administrador para bloquear/desbloquear/activar (reinicia contador).
  - Registrar en bitácora los intentos fallidos y bloqueos (ver g).
- **Archivos:** `resources/views/livewire/auth/login.blade.php`, `config/auth.php`, `.env.example`, `bootstrap/app.php` (middleware), nueva pantalla Volt de usuarios (p. ej. `resources/views/livewire/usuarios/*`), `routes/web.php`, pruebas en `tests/Feature/Auth/`.

### d. Roles y permisos

- **Estado:** no existen gates, policies ni paquete de permisos (`app/Policies` no existe; `AppServiceProvider::boot()` vacío; no hay `spatie/laravel-permission`).
- **Falta:** con tres roles fijos basta lo nativo de Laravel: enum `Rol` + **Gates** en `app/Providers/AppServiceProvider.php` y **Policies** (`app/Policies/MemorandumPolicy.php`, `UserPolicy.php`) aplicadas con `can:` en rutas y `$this->authorize()` / `Gate::authorize()` en componentes. Un paquete solo se justificaría si se piden permisos editables desde la interfaz (requiere aprobación).
- **Archivos:** `app/Enums/Rol.php`, `app/Policies/*`, `app/Providers/AppServiceProvider.php`, `routes/web.php`, menú de `resources/views/components/layouts/app/sidebar.blade.php` (ocultar opciones con `@can`).

### e. Español, zona horaria America/Tegucigalpa y fechas dd/mm/aaaa

- **Estado:** idioma `en`, sin carpeta `lang/`; zona `UTC` fija; sin formato de fecha propio. Muchos textos de las vistas están en inglés **sin** `__()` (p. ej. "Log in to your account", "Platform", "Dashboard", "Settings", "Profile").
- **Falta:**
  - `.env` / `.env.example`: `APP_LOCALE=es`, `APP_FALLBACK_LOCALE=es` (o `en`), `APP_FAKER_LOCALE=es_ES`, `APP_TIMEZONE=America/Tegucigalpa`.
  - `config/app.php` línea 68: `'timezone' => env('APP_TIMEZONE', 'America/Tegucigalpa')`.
  - `php artisan lang:publish` y crear `lang/es/` (`auth.php`, `validation.php`, `passwords.php`, `pagination.php`) y `lang/es.json`.
  - Traducir o envolver en `__()` los textos de `resources/views/**`.
  - Fechas: formato `d/m/Y` centralizado (p. ej. accessor o helper reutilizable, o cast `'date:d/m/Y'` para serialización) y entradas de fecha que acepten/validen `dd/mm/aaaa` (`date_format:d/m/Y`). Guardar en BD en formato nativo.
- **Archivos:** `.env`, `.env.example`, `config/app.php`, `lang/`, `resources/views/**`, modelos con fechas.

### f. PDF en almacenamiento privado servidos por un controlador con autorización

- **Estado:** el disco `local` ya es privado (`storage/app/private`), pero tiene **`'serve' => true`**, lo que publica `GET/PUT /storage/{path}`. Ese acceso exige URL firmada (`ServeFile::hasValidSignature`), pero **no verifica usuario ni rol**: cualquiera con la URL firmada vigente puede descargar. No existe controlador de descarga.
- **Falta:**
  - Disco dedicado (p. ej. `memorandos`, raíz `storage/app/private/memorandos`, sin `serve`) o poner `'serve' => false` en `local`.
  - Controlador `app/Http/Controllers/MemorandumPdfController.php` (invocable) que autorice con la Policy y responda con `Storage::disk(...)->response()` (en línea, `Content-Type: application/pdf`, nombre seguro), en ruta con `auth`.
  - Nombres de archivo generados por el sistema (UUID o huella), nunca el nombre original como ruta.
  - Livewire guarda las subidas temporales en `livewire-tmp/` del disco por defecto; publicar `config/livewire.php` si se quiere otro disco o límite.
- **Archivos:** `config/filesystems.php`, `app/Http/Controllers/`, `app/Policies/MemorandumPolicy.php`, `routes/web.php`, opcional `config/livewire.php`.

### g. Bitácora de auditoría

- **Estado:** no existe (ni tabla ni paquete). Solo logs de archivo (`LOG_CHANNEL=stack`).
- **Falta:** tabla `bitacora` (usuario, acción, entidad, id de entidad, valores anteriores/nuevos en JSON, IP, user agent, fecha), modelo y un servicio/observer que registre: inicio/cierre de sesión, intentos fallidos y bloqueos (eventos `Login`, `Logout`, `Failed`, `Lockout`), alta/edición/baja de memorandos, consultas y descargas de PDF, cambios de usuarios y roles. Pantalla de consulta solo para Administrador. La bitácora no debe poder editarse ni borrarse desde la aplicación. Alternativa con paquete: `spatie/laravel-activitylog` (requiere aprobación).
- **Archivos:** nueva migración, `app/Models/Bitacora.php`, `app/Observers/*` o `app/Listeners/*`, `app/Providers/AppServiceProvider.php`, vista Volt de consulta, `routes/web.php`.

### h. Exportación a Excel y PDF

- **Estado:** no hay paquetes para generar Excel ni PDF (`composer show --direct`).
- **Falta:** elegir y aprobar dependencias, p. ej. `openspout/openspout` o `maatwebsite/excel` (Excel) y `barryvdh/laravel-dompdf` (PDF). Exportar respetando los mismos filtros y permisos de la búsqueda, y registrar la exportación en la bitácora.
- **Archivos:** `composer.json` (tras aprobación), clases de exportación (p. ej. `app/Exports/`), vistas Blade para el PDF del listado, `routes/web.php`.

### i. Validación de PDF en el servidor

- **Estado:** no existe ninguna validación de archivos (no hay Form Requests ni componentes de carga).
- **Falta:**
  - **Tipo real:** regla `mimetypes:application/pdf` (detecta por contenido) más comprobación de la firma `%PDF-` en los primeros bytes.
  - **Tamaño:** regla `max:<KB>` con valor en config (p. ej. `MEMORANDO_PDF_MAX_KB`); revisar además `upload_max_filesize` y `post_max_size` de `C:\xampp\php\php.ini` (valores **no determinados**: verificar con `php -i | findstr /i "upload_max_filesize post_max_size"`) y el límite de subida temporal de Livewire (por defecto 12 MB, sin `config/livewire.php` publicado).
  - **Conteo de páginas:** no hay librería instalada; opciones: `smalot/pdfparser` o `setasign/fpdi` (requieren aprobación) o `pdfinfo` de Poppler (requiere instalarlo en el servidor). Rechazar PDF de 0 páginas, cifrados o corruptos.
  - **Huella SHA-256:** nativo de PHP (`hash_file('sha256', ...)`); guardarla en una columna única para detectar duplicados.
  - Encapsular todo en una regla o servicio reutilizable (p. ej. `app/Rules/PdfValido.php` o `app/Services/...`) con pruebas.
- **Archivos:** componente Volt de registro de memorandos, `app/Rules/` o `app/Services/`, migración de memorandos (columnas `sha256`, `paginas`, `tamano_bytes`, `ruta_pdf`), `config/` propio para límites, pruebas en `tests/Feature/`.

---

## 12. Recomendaciones (en orden de prioridad)

1. **Definir el motor de base de datos de producción** (SQLite actual vs. MySQL/MariaDB de XAMPP u otro) antes de escribir migraciones, para usar tipos y claves foráneas compatibles.
2. **Configurar idioma y zona horaria primero** (`APP_LOCALE=es`, `APP_TIMEZONE=America/Tegucigalpa` leído desde `config/app.php`, carpeta `lang/es`), para que las fechas guardadas desde el inicio sean correctas.
3. **Cerrar el acceso público:** quitar registro y recuperación de contraseña (corrigiendo el enlace sin `Route::has` en `login.blade.php` línea 114), quitar el borrado de la propia cuenta y redirigir `/` al login.
4. **Ampliar `users` y crear `gerencias`** con migraciones nuevas, enum `Rol`, casts y factories con estados; crear el Administrador inicial por seeder con contraseña de `.env` y `debe_cambiar_contrasena = true`.
5. **Implementar bloqueo persistente** con límite en `config/auth.php` (vía `.env`), mensaje genérico y middleware que expulse a usuarios bloqueados/inactivos, con pruebas Pest de cada caso.
6. **Autorización nativa** (Gates + Policies) para los tres roles; evitar un paquete de permisos mientras los roles sean fijos.
7. **Almacenamiento privado sin `serve`:** disco dedicado para PDF y descarga solo mediante controlador autorizado; validar PDF (tipo real, tamaño, páginas, SHA-256) en un servicio reutilizable.
8. **Bitácora desde el primer módulo**, para que todo alta, consulta, descarga, exportación y evento de sesión quede registrado.
9. **Aprobar dependencias** necesarias (conteo de páginas PDF, Excel, PDF de reportes) antes de construir búsqueda y exportación.
10. **Limpieza y calidad:** quitar `FolioServiceProvider` de `bootstrap/providers.php`, el montaje de `resources/views/pages` si no se usará, los enlaces del starter kit en la barra lateral, `axios`/`autoprefixer` si no se usan; agregar `pint.json` y scripts `composer test`/`lint`; alinear la versión de PHP de CI (8.4) con la de producción (8.2.12 local); evaluar más adelante si conviene migrar de Volt a los componentes de archivo único nativos de Livewire 4.
