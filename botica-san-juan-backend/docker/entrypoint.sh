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
# Interprete
# ---------------------------------------------------------------------------
# Antes que nada: si el PHP de la imagen es uno de los vulnerables a
# CVE-2026-109618 / CVE-2026-109619 (RCE sin autenticar), el contenedor no
# arranca. Va primero porque esos fallos se disparan durante el parseo de la
# peticion, es decir, ANTES de que corra una sola linea de la aplicacion:
# ninguna comprobacion posterior llegaria a tiempo.
php /usr/local/bin/comprobar-php-seguro.php

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

# DB_URL es la que usa config/database.php para la conexion pgsql; DB_HOST es
# la alternativa por partes. Se aceptan ambas, y DATABASE_URL porque algunos
# proveedores la inyectan con ese nombre.
if [ -z "$DB_URL" ] && [ -z "$DB_HOST" ] && [ -z "$DATABASE_URL" ]; then
    echo "!!! Falta la configuracion de base de datos (DB_URL, DB_HOST o DATABASE_URL)."
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
# Enlace de storage
# ---------------------------------------------------------------------------
# Crea public/storage -> storage/app/public, que es por donde se sirven los
# archivos del disco publico (fotos de producto y de perfil).
#
# TIENE QUE HACERSE EN CADA ARRANQUE, no una vez. El enlace esta en
# .gitignore y en .dockerignore, asi que no viaja ni en el repositorio ni en
# la imagen: cada contenedor nuevo nace sin el. Sin el enlace, una peticion a
# /storage/... devuelve 404 y la aplicacion no da ninguna pista, porque desde
# su punto de vista el archivo existe.
#
# --force lo hace idempotente: si el enlace ya esta (reinicio del mismo
# contenedor sin reconstruir), lo rehace en lugar de abortar con "the link
# already exists", que con `set -e` tumbaria el arranque entero.
#
# LO QUE ESTO NO ARREGLA
#
# El disco del contenedor es EFIMERO. El enlace hace que los archivos se
# sirvan; no hace que sobrevivan. Una foto que un administrador suba hoy
# desaparece en el siguiente despliegue, junto con el contenedor que la
# guardo. La solucion de verdad es almacenamiento de objetos (S3 o
# equivalente) con el disco `public` apuntando ahi, y eso es un cambio de
# infraestructura, no de este script. Mientras no exista, las imagenes del
# catalogo son las versionadas en public/images, que si viajan en la imagen.
echo "==> Enlazando storage"
php artisan storage:link --force

# ---------------------------------------------------------------------------
# Fotos del catalogo
# ---------------------------------------------------------------------------
# Las fotos verificadas viajan versionadas en storage/app/public/productos, y
# este comando apunta la columna `imagen` de cada producto a la suya. Va en el
# arranque, y no como paso manual, para que nadie tenga que abrir una conexion
# a la base de produccion desde su equipo: el contenedor ya la tiene.
#
# Es idempotente: en un arranque sin fotos nuevas no escribe nada.
#
# NO tumba el arranque si falla, a diferencia de las migraciones. Una foto que
# falta se ve como la ilustracion de respaldo; una botica caida no vende. Por
# eso el `||`, que ademas es lo unico que impide que `set -e` mate el proceso.
echo "==> Asignando fotos del catalogo"
php artisan catalogo:poblar-imagenes \
    || echo "!!! No se pudieron asignar las fotos del catalogo; se sirve con la ilustracion de respaldo"

# ---------------------------------------------------------------------------
# Planificador
# ---------------------------------------------------------------------------
# El plan gratuito de Render no ofrece cron, y sin planificador las reservas
# vencidas (`pedidos:liberar-expirados`) no devolverian nunca su stock a los
# lotes. `schedule:work` es el planificador de Laravel en primer plano: aqui
# se deja en segundo plano dentro del mismo contenedor.
#
# LO QUE ESTO IMPLICA
#
# Cuando el servicio se duerme por inactividad, el planificador se duerme con
# el. No es un problema: mientras duerme nadie esta comprando, y al despertar
# la siguiente pasada libera todo lo vencido de golpe. Lo que no garantiza es
# puntualidad al minuto.
#
# Si el planificador muere, la API sigue atendiendo: por eso va con `&` y no
# encadenado al arranque. Una reserva que tarda en liberarse es stock que no
# se ve; una botica caida no vende.
echo "==> Arrancando el planificador"
php artisan schedule:work >/dev/stderr 2>&1 &

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
