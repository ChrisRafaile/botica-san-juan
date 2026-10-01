#!/bin/sh
# Arranque de la API en produccion.
# =============================================================================
# `set -e` es deliberado: si una migracion falla, el contenedor debe morir y
# que el orquestador lo marque como despliegue fallido. Arrancar igualmente
# dejaria la botica sirviendo peticiones contra un esquema a medio aplicar,
# que es peor que estar caida, porque el error aparece mas tarde y en forma de
# datos incorrectos.
set -e

echo "==> Botica San Juan · arranque de la API"

# ---------------------------------------------------------------------------
# Comprobaciones previas
# ---------------------------------------------------------------------------
# APP_KEY cifra las cookies y los tokens. Sin ella Laravel lanza una excepcion
# en la primera peticion con un mensaje que no dice cual es la causa real, asi
# que se comprueba aqui y se falla con un mensaje que si la dice.
if [ -z "$APP_KEY" ]; then
    echo "!!! Falta APP_KEY. Generala con: php artisan key:generate --show"
    exit 1
fi

if [ -z "$DB_HOST" ] && [ -z "$DATABASE_URL" ]; then
    echo "!!! Falta la configuracion de base de datos (DB_HOST o DATABASE_URL)."
    exit 1
fi

# ---------------------------------------------------------------------------
# Cache de configuracion
# ---------------------------------------------------------------------------
# Se cachea configuracion y rutas porque en produccion no cambian entre
# peticiones y leerlas del disco cada vez es trabajo tirado. Las vistas se
# compilan tambien, aunque esta API devuelve JSON: dompdf usa plantillas Blade
# para los comprobantes.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ---------------------------------------------------------------------------
# Esquema
# ---------------------------------------------------------------------------
# --force porque en produccion Laravel pide confirmacion interactiva y aqui no
# hay nadie para darla.
echo "==> Aplicando migraciones"
php artisan migrate --force

# ---------------------------------------------------------------------------
# Cuentas de demostracion
# ---------------------------------------------------------------------------
# Se siembran en CADA arranque, no solo en el primero, porque el seeder usa
# updateOrCreate: no duplica cuentas y permite rotar la contrasena cambiando
# la variable de entorno y volviendo a desplegar, sin tocar la base a mano.
#
# Si no hay contrasenas declaradas no se siembra nada y se dice por que. Lo
# que no se hace nunca es inventar una clave por defecto: seria un
# administrador con credencial conocida en una URL publica.
if [ -n "$DEMO_ADMIN_PASSWORD" ] || [ -n "$DEMO_CLIENTE_PASSWORD" ]; then
    echo "==> Sembrando cuentas de demostracion"
    php artisan db:seed --class=UsuariosDemostracionSeeder --force
else
    echo "==> Sin DEMO_ADMIN_PASSWORD ni DEMO_CLIENTE_PASSWORD: no se crean cuentas de demostracion"
fi

# ---------------------------------------------------------------------------
# Servidor
# ---------------------------------------------------------------------------
# `php-server` monta la configuracion de Caddy equivalente a un `try_files`
# hacia public/index.php, que es lo que necesita el enrutador de Laravel.
echo "==> Escuchando en :${PORT}"
exec frankenphp php-server \
    --root /app/public \
    --listen "0.0.0.0:${PORT}" \
    --access-log
