# Registro de memorandums · GIS

Sistema web interno de la **Gerencia de Información y Sistemas (GIS)** de la Alcaldía Municipal del Distrito Central (AMDC). Sirve para registrar los memorandos **emitidos y recibidos** por la GIS, guardar el PDF escaneado de cada uno y consultarlos después.

El sistema no genera memorandos: registra los que ya existen en papel.

---

## Contenido

1. [Funciones de la versión 1](#funciones-de-la-versión-1)
2. [Stack tecnológico](#stack-tecnológico)
3. [Requisitos del servidor](#requisitos-del-servidor)
4. [Instalación en producción](#instalación-en-producción)
5. [Verificación después de instalar](#verificación-después-de-instalar)
6. [Actualizar a una nueva versión](#actualizar-a-una-nueva-versión)
7. [Respaldos y restauración](#respaldos-y-restauración)
8. [Instalación para desarrollo (XAMPP)](#instalación-para-desarrollo-xampp)
9. [Solución de problemas](#solución-de-problemas)
10. [Estructura del proyecto](#estructura-del-proyecto)

---

## Funciones de la versión 1

- **Registro de memorandos emitidos y recibidos.**
  - El número se captura por partes: `AMDC-SIGLAS-correlativo-año`.
  - La gerencia propia aparece fija y no se puede editar.
  - Admite varios destinatarios (PARA y COPIA).
  - Las fechas se eligen en un calendario.
- **PDF escaneado.**
  - Se valida que sea un PDF real, completo y de tamaño permitido.
  - Se cuentan sus páginas y se calcula su huella SHA-256.
  - Se muestra una vista previa antes de guardar.
- **Duplicados.** Un número no se puede registrar dos veces (`0398` cuenta igual que `398`). Si el mismo PDF ya está cargado en otro memorando, el sistema avisa.
- **Consulta.** Listado con pestañas Todos / Emitidos / Recibidos, búsqueda y filtros. El detalle incluye el visor del PDF.
- **Seguridad.**
  - Los PDF se guardan fuera de la carpeta pública y solo los entrega el sistema, después de verificar los permisos del usuario.
  - Una cuenta se bloquea tras varios intentos fallidos de inicio de sesión.
  - Las contraseñas temporales se deben cambiar en el primer ingreso.
  - No existe registro público de cuentas.
- **Administración.** Gerencias (con sus siglas y titular), Usuarios, y Roles y permisos (18 permisos en 5 módulos).
- **Bitácora de solo lectura.** Registra inicios de sesión, cambios, y cada vez que alguien consulta o descarga un PDF.

## Stack tecnológico

| Capa | Tecnología | Versión |
| --- | --- | --- |
| Lenguaje | PHP | 8.2 o superior (probado con 8.2 y 8.3) |
| Framework | Laravel | 12 |
| Interfaz reactiva | Livewire + Volt (componentes de un solo archivo) | Livewire 4, Volt 1 |
| Componentes de interfaz | Flux UI (edición gratuita) | 2 |
| Estilos | Tailwind CSS | 4 |
| Compilación de recursos | Vite + Node.js | Vite 6, Node 20 o 22 LTS |
| Base de datos | MySQL o MariaDB | MySQL 8.0+ o MariaDB 10.4+ |
| Pruebas | Pest | 3 |
| Formato de código | Laravel Pint | 1 |

El proyecto partió del *starter kit* oficial de Laravel para Livewire. Flux UI gratuito no necesita licencia ni credenciales.

## Requisitos del servidor

**Software**

- Linux (recomendado: Ubuntu Server 22.04/24.04) con **Apache 2.4** o **Nginx + PHP-FPM**. También funciona en Windows con XAMPP (ver [desarrollo](#instalación-para-desarrollo-xampp)).
- **PHP 8.2 o superior** con estas extensiones: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zip`.
- **Composer 2**.
- **Node.js 20 o 22 LTS** con npm, solo para compilar los recursos (CSS y JS). Si prefiere no instalar Node en el servidor, compílelos en otro equipo y copie la carpeta `public/build`.
- **MySQL 8.0+ o MariaDB 10.4+**.
- **Git**.

**Valores de `php.ini`**

El PDF puede pesar hasta 10 MB y la carga temporal de Livewire admite hasta 12 MB. Configure:

```ini
upload_max_filesize = 12M
post_max_size = 16M
memory_limit = 256M
max_execution_time = 60
```

Con Nginx agregue también `client_max_body_size 16M;` (ver la configuración más abajo).

---

## Instalación en producción

Los ejemplos usan la carpeta `/var/www/gestion_memorandums`, el dominio `file.amdc.hn` y el usuario del servidor web `www-data`. Ajústelos a su servidor.

### 1. Obtener el código

El repositorio es `jonatanLRAMDC/gestion_memorandums` y la rama de producción es **`main`**. Las versiones publicadas se marcan con etiquetas (por ejemplo `v1.0.0`).

> **Importante:** si el repositorio es privado, el servidor necesita una forma de acceder a él. Para un servidor se recomienda una **llave de despliegue (deploy key)** de solo lectura:
>
> 1. En el servidor: `ssh-keygen -t ed25519 -C "servidor-memorandums" -f ~/.ssh/memorandums_deploy`
> 2. Copie el contenido de `~/.ssh/memorandums_deploy.pub`.
> 3. En GitHub, el administrador del repositorio la agrega en **Settings → Deploy keys → Add deploy key**, sin marcar "Allow write access".
> 4. En el servidor, cree `~/.ssh/config` con:
>    ```
>    Host github.com
>        IdentityFile ~/.ssh/memorandums_deploy
>    ```
>
> Otra opción: una persona con acceso al repositorio (por ejemplo, un miembro de la organización AMDC HN invitado como colaborador) clona por HTTPS con un *personal access token*.

```bash
cd /var/www
sudo git clone git@github.com:jonatanLRAMDC/gestion_memorandums.git
cd gestion_memorandums
sudo git checkout v1.0.0        # o quédese en main
```

### 2. Instalar las dependencias de PHP

```bash
composer install --no-dev --optimize-autoloader
```

`--no-dev` deja fuera las herramientas de desarrollo y pruebas.

### 3. Compilar los recursos (CSS y JS)

```bash
npm ci
npm run build
```

Así se genera `public/build`. Si compiló en otro equipo, copie esa carpeta al servidor.

### 4. Crear la base de datos

En MySQL/MariaDB:

```sql
CREATE DATABASE gestion_memorandums CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'memorandums'@'localhost' IDENTIFIED BY 'CAMBIE-ESTA-CLAVE';
GRANT ALL PRIVILEGES ON gestion_memorandums.* TO 'memorandums'@'localhost';
FLUSH PRIVILEGES;
```

### 5. Configurar el archivo `.env`

```bash
cp .env.example .env
```

Edite `.env` con estos valores de producción:

```dotenv
APP_NAME="Registro de memorandums"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://file.amdc.hn

APP_LOCALE=es
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=es_ES
APP_TIMEZONE=America/Tegucigalpa

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestion_memorandums
DB_USERNAME=memorandums
DB_PASSWORD=CAMBIE-ESTA-CLAVE

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true      # solo si el sitio usa HTTPS

CACHE_STORE=database
QUEUE_CONNECTION=sync
MAIL_MAILER=log

# Intentos fallidos seguidos antes de bloquear una cuenta
AUTH_MAX_INTENTOS=5

# Tamaño máximo del PDF en KB (10240 = 10 MB)
MEMORANDO_PDF_MAX_KB=10240

# Administrador inicial que crea el seeder
ADMIN_NAME="Administrador del sistema"
ADMIN_EMAIL=admin@amdc.hn
ADMIN_PASSWORD=
```

- **`APP_DEBUG` debe quedar en `false`.** Con `true` se muestran detalles internos cuando ocurre un error.
- **`ADMIN_PASSWORD`:** si lo deja vacío, el seeder genera una contraseña temporal y la muestra una sola vez en la consola. Si escribe una, el sistema igual pedirá cambiarla en el primer ingreso. Después de instalar, borre ese valor del `.env`.

Genere la llave de la aplicación:

```bash
php artisan key:generate
```

### 6. Crear las tablas y los datos iniciales

```bash
php artisan migrate --force
php artisan db:seed --force
```

`db:seed` crea:

- Las **33 gerencias** del catálogo, con siglas únicas. Solo **GIS** es oficial; las demás siglas son provisionales y se corrigen desde la pantalla Gerencias.
- Los **3 roles base**: Administrador, Recepción y Consulta, con sus permisos.
- El **administrador inicial** en la GIS.

En producción **no se cargan titulares**. Los nombres de prueba solo se cargan cuando `APP_ENV` es `local` o `testing`. Registre los titulares reales desde la pantalla Gerencias.

### 7. Permisos de carpetas

El servidor web debe poder escribir en `storage` (registros, sesiones, PDF) y en `bootstrap/cache`:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Los PDF se guardan en `storage/app/private/memorandos`, una carpeta que **no** es pública. No ejecute `php artisan storage:link`: el sistema no lo necesita.

### 8. Optimizar para producción

```bash
php artisan optimize
```

Esto guarda en caché la configuración, las rutas, los eventos y las vistas. **Cada vez que cambie el `.env`**, vuelva a ejecutar `php artisan optimize:clear && php artisan optimize`.

### 9. Configurar el servidor web

El servidor web debe apuntar a la carpeta **`public`** del proyecto, nunca a la raíz.

**Apache** (`/etc/apache2/sites-available/memorandums.conf`):

```apache
<VirtualHost *:80>
    ServerName file.amdc.hn
    DocumentRoot /var/www/gestion_memorandums/public

    <Directory /var/www/gestion_memorandums/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/memorandums-error.log
    CustomLog ${APACHE_LOG_DIR}/memorandums-access.log combined
</VirtualHost>
```

```bash
sudo a2enmod rewrite
sudo a2ensite memorandums
sudo systemctl reload apache2
```

**Nginx** (`/etc/nginx/sites-available/memorandums`):

```nginx
server {
    listen 80;
    server_name file.amdc.hn;
    root /var/www/gestion_memorandums/public;
    index index.php;
    charset utf-8;
    client_max_body_size 16M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;   # ajuste a su versión de PHP
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**HTTPS:** se recomienda publicar el sitio solo por HTTPS, con el certificado institucional o con Let's Encrypt (`certbot`). Con HTTPS activo, deje `SESSION_SECURE_COOKIE=true` y `APP_URL` con `https://`.

### 10. Primer ingreso

1. Abra `https://file.amdc.hn` e inicie sesión con `ADMIN_EMAIL` y la contraseña del paso 5.
2. El sistema pedirá cambiar la contraseña.
3. En **Gerencias**, registre los titulares y corrija las siglas provisionales si hace falta.
4. En **Usuarios**, cree las cuentas del personal de la GIS. Cada una recibe una contraseña temporal que se muestra una sola vez.
5. En **Roles y permisos**, revise qué puede hacer cada rol.

---

## Verificación después de instalar

- [ ] `https://file.amdc.hn/login` muestra la pantalla de inicio de sesión con el escudo.
- [ ] `https://file.amdc.hn/register` responde **404**, porque no existe registro público.
- [ ] El administrador inicia sesión y debe cambiar su contraseña.
- [ ] Se puede registrar un memorando con un PDF real y verlo en el detalle.
- [ ] Al copiar la dirección del PDF y abrirla en una ventana privada (sin sesión), el sistema envía al inicio de sesión.
- [ ] La **Bitácora** muestra los inicios de sesión y las consultas de PDF.
- [ ] `APP_DEBUG=false` y `APP_ENV=production` en el `.env`.

## Actualizar a una nueva versión

```bash
cd /var/www/gestion_memorandums
php artisan down                         # modo mantenimiento
git fetch --all --tags
git checkout v1.x.x                      # o: git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
php artisan up
```

Haga un respaldo (siguiente sección) **antes** de cada actualización.

## Respaldos y restauración

Un respaldo completo tiene **dos partes**. La base de datos sola no basta, porque los PDF están en archivos.

| Qué | Dónde | Cómo |
| --- | --- | --- |
| Base de datos | MySQL/MariaDB | `mysqldump --single-transaction -u memorandums -p gestion_memorandums > respaldo-$(date +%F).sql` |
| PDF de los memorandos | `storage/app/private/memorandos` | `tar czf memorandos-$(date +%F).tar.gz -C storage/app/private memorandos` |
| Configuración | `.env` | Guárdelo aparte y en un lugar seguro (contiene la llave `APP_KEY`) |

Programe ambos respaldos (por ejemplo, a diario con `cron`) y **pruebe la restauración** periódicamente:

```bash
mysql -u memorandums -p gestion_memorandums < respaldo-AAAA-MM-DD.sql
tar xzf memorandos-AAAA-MM-DD.tar.gz -C storage/app/private
sudo chown -R www-data:www-data storage
```

---

## Instalación para desarrollo (XAMPP)

1. Cree la base `gestion_memorandums` (utf8mb4) en phpMyAdmin.
2. Clone el repositorio e instale las dependencias:
   ```bash
   composer install
   npm install
   cp .env.example .env
   php artisan key:generate
   ```
3. En `.env` deje `APP_ENV=local`, `APP_DEBUG=true` y los datos de MySQL de XAMPP (`DB_USERNAME=root`, `DB_PASSWORD=` vacío).
4. Cree las tablas y los datos de prueba. En local también se cargan titulares ficticios en las gerencias.
   ```bash
   php artisan migrate:fresh --seed
   ```
5. Inicie el entorno:
   ```bash
   composer run dev
   ```
   Este comando levanta `http://127.0.0.1:8001`, la cola y Vite.

**Pruebas y formato**

```bash
php artisan test --compact      # pruebas automáticas (Pest, SQLite en memoria)
vendor/bin/pint                 # formato del código PHP
```

---

## Solución de problemas

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| Error 500 justo después de instalar | Sin permisos de escritura en `storage` o `bootstrap/cache` | Revise el [paso 7](#7-permisos-de-carpetas) y `storage/logs/laravel-*.log` |
| "Vite manifest not found" | No se compilaron los recursos | `npm ci && npm run build`, o copie `public/build` |
| "419 Page Expired" al iniciar sesión | `APP_URL` o `SESSION_SECURE_COOKIE` no coinciden con el protocolo real (HTTP o HTTPS) | Corrija el `.env` y ejecute `php artisan optimize:clear && php artisan optimize` |
| El PDF no se carga o da error 413 | Límites de `php.ini` o de Nginx menores que el archivo | Revise `upload_max_filesize`, `post_max_size` y `client_max_body_size` |
| Un cambio en `.env` no tiene efecto | La configuración está en caché | `php artisan optimize:clear && php artisan optimize` |
| Las páginas no cargan bien (rutas 404 salvo la principal) | Falta `mod_rewrite` o `AllowOverride All` en Apache | `sudo a2enmod rewrite` y revise el VirtualHost |
| Las páginas del PDF aparecen como "No determinado" | El escáner guardó esa información comprimida | Es solo informativo; el PDF se guarda y se ve igual |
| Un usuario no puede entrar | Cuenta bloqueada o inactiva | **Usuarios** → Desbloquear o Activar |

## Estructura del proyecto

```
app/
├── Enums/                  Permiso, TipoRegistro, TipoDestino
├── Http/Controllers/       MemorandoPdfController (entrega autorizada del PDF)
├── Http/Middleware/        Cuenta activa y cambio obligatorio de contraseña
├── Livewire/Forms/         MemorandoForm (validación y guardado del memo)
├── Models/                 Memorando, Registro, Archivo, Gerencia, Rol, User, Bitacora…
├── Policies/               MemorandoPolicy (quién ve o descarga cada PDF)
└── Rules/                  PdfValido (tipo real, integridad y páginas)
config/memorandos.php       Gerencia propia, disco y tamaño del PDF, administrador inicial
database/seeders/           Gerencias, administrador y titulares de prueba
resources/views/livewire/   Pantallas: memorandos, administración, ajustes y acceso
tests/                      Pruebas automáticas (Pest)
```

---

Alcaldía Municipal del Distrito Central · Gerencia de Información y Sistemas
