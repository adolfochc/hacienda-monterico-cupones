# Despliegue en cPanel

Este proyecto requiere PHP 8.2, MySQL y que el dominio apunte al directorio `public` de Laravel.

## 1. Rutas del servidor

Los ejemplos usan estas rutas. Reemplaza `DOMINIO.com` por la carpeta real asignada al proyecto:

```bash
PROJECT_PATH=/home/wynbioms/DOMINIO.com
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php
COMPOSER_PHAR=/home/wynbioms/centromedicopaita.com/composer.phar
```

El document root del dominio debe ser:

```text
/home/wynbioms/DOMINIO.com/public
```

No debe apuntar a la raíz completa del repositorio, porque expondría `.env`, código y archivos internos.

## 2. Configuración inicial

Crear `.env` a partir de `.env.example` y configurar como mínimo:

```dotenv
APP_NAME="Hacienda MonteRico"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMINIO.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=NOMBRE_BASE_DATOS
DB_USERNAME=USUARIO_BASE_DATOS
DB_PASSWORD=CONTRASENA_BASE_DATOS

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true

ADMIN_NAME="Administrador Hacienda MonteRico"
ADMIN_EMAIL=admin@DOMINIO.com
ADMIN_PASSWORD=CAMBIAR_POR_UNA_CLAVE_SEGURA
```

Generar `APP_KEY` solamente en el primer despliegue:

```bash
cd /home/wynbioms/DOMINIO.com
/opt/cpanel/ea-php82/root/usr/bin/php artisan key:generate
```

No vuelvas a ejecutar `key:generate` en actualizaciones: cambiar la clave invalidaría sesiones y datos cifrados.

## 3. Recursos frontend

La aplicación necesita el contenido compilado de `public/build`. La opción recomendada es compilar localmente antes de subir o actualizar el repositorio:

```bash
npm ci
npm run build
```

Comprueba que `public/build/manifest.json` exista en el servidor. Si cPanel cuenta con Node.js, también puedes ejecutar esos comandos allí.

## 4. Comando de despliegue habitual

Ejecutar desde la raíz del proyecto. Composer debe instalarse antes de ejecutar Artisan:

```bash
cd /home/wynbioms/DOMINIO.com

/opt/cpanel/ea-php82/root/usr/bin/php /home/wynbioms/centromedicopaita.com/composer.phar install --no-dev --optimize-autoloader --no-interaction

/opt/cpanel/ea-php82/root/usr/bin/php artisan migrate --force && \
/opt/cpanel/ea-php82/root/usr/bin/php artisan optimize:clear && \
/opt/cpanel/ea-php82/root/usr/bin/php artisan config:cache && \
/opt/cpanel/ea-php82/root/usr/bin/php artisan route:cache && \
/opt/cpanel/ea-php82/root/usr/bin/php artisan view:cache
```

Versión en una sola línea:

```bash
cd /home/wynbioms/DOMINIO.com && /opt/cpanel/ea-php82/root/usr/bin/php /home/wynbioms/centromedicopaita.com/composer.phar install --no-dev --optimize-autoloader --no-interaction && /opt/cpanel/ea-php82/root/usr/bin/php artisan migrate --force && /opt/cpanel/ea-php82/root/usr/bin/php artisan optimize:clear && /opt/cpanel/ea-php82/root/usr/bin/php artisan config:cache && /opt/cpanel/ea-php82/root/usr/bin/php artisan route:cache && /opt/cpanel/ea-php82/root/usr/bin/php artisan view:cache
```

## 5. Primer despliegue solamente

Crear el enlace de archivos públicos:

```bash
cd /home/wynbioms/DOMINIO.com
/opt/cpanel/ea-php82/root/usr/bin/php artisan storage:link
```

Crear o actualizar el administrador predeterminado:

```bash
/opt/cpanel/ea-php82/root/usr/bin/php artisan db:seed --class=AdminUserSeeder --force
```

El seeder toma `ADMIN_NAME`, `ADMIN_EMAIL` y `ADMIN_PASSWORD` del `.env`. Después del primer acceso, cambia la contraseña y evita conservar una clave predecible.

Si también deseas crear los cupones iniciales configurados por el proyecto:

```bash
/opt/cpanel/ea-php82/root/usr/bin/php artisan db:seed --force
```

## 6. Permisos

Laravel necesita escritura en:

```text
storage/
bootstrap/cache/
```

En el Administrador de archivos de cPanel, verifica que pertenezcan al usuario de la cuenta y tengan permisos de escritura. Evita permisos `777`.

## 7. Comprobaciones posteriores

```bash
cd /home/wynbioms/DOMINIO.com
/opt/cpanel/ea-php82/root/usr/bin/php artisan about
/opt/cpanel/ea-php82/root/usr/bin/php artisan migrate:status
/opt/cpanel/ea-php82/root/usr/bin/php artisan route:list --except-vendor
```

Después revisa:

- Que `https://DOMINIO.com/login` cargue sin errores.
- Que un administrador pueda iniciar sesión.
- Que el alta de socios y la invitación funcionen.
- Que el socio cambie su contraseña en el primer acceso.
- Que la cámara funcione mediante HTTPS para escanear el QR.
- Que `storage/logs/laravel.log` no registre errores nuevos.

## 8. Si el despliegue falla

Limpia las cachés y consulta el log:

```bash
cd /home/wynbioms/DOMINIO.com
/opt/cpanel/ea-php82/root/usr/bin/php artisan optimize:clear
tail -n 100 storage/logs/laravel.log
```

No uses `migrate:fresh` en producción: elimina todas las tablas y sus datos.
