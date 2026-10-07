# Imagen de produccion de la API de la Botica San Juan
# =============================================================================
# POR QUE FRANKENPHP Y NO php-fpm + nginx
#
# FrankenPHP es un unico binario (Caddy con PHP embebido) que atiende HTTP y
# ejecuta PHP en el mismo proceso. En un contenedor eso importa: php-fpm mas
# nginx son dos procesos que hay que supervisar, y el supervisor se convierte
# en una pieza mas que puede fallar. Aqui el contenedor arranca un proceso, y
# si ese proceso muere el orquestador lo reinicia, que es justo lo que se
# espera de un despliegue en contenedor.
#
# POR QUE NO `php artisan serve`
#
# Es un servidor de desarrollo de un solo hilo: atiende una peticion cada vez.
# Para una botica con un punto de venta abierto eso es inaceptable, aunque
# arrancaria sin protestar.
# =============================================================================

# PHP 8.4, no 8.3.
#
# La imagen estuvo en 8.3 y eso rompio todos los despliegues desde el 5 de
# octubre, de forma silenciosa: `composer install` abortaba con
#
#     symfony/clock v8.1.0 requires php >=8.4.1 -> your php version (8.3.35)
#     does not satisfy that requirement
#
# El contenedor moria durante la construccion, Render mantenia vivo el anterior
# y el servicio seguia respondiendo 200. Desde fuera no se veia nada: lo unico
# que ocurria es que el codigo nuevo no llegaba nunca.
#
# El origen fue una actualizacion de dependencias resuelta contra el PHP 8.4
# local, que fijo Symfony 8.1 en el lock. Para que esa discrepancia no se repita
# sin avisar, `composer.json` declara ahora `config.platform.php`: Composer
# resuelve siempre contra la version de PHP del despliegue, no contra la del
# equipo de quien ejecuta el comando.
FROM dunglas/frankenphp:1-php8.4-alpine

# pdo_pgsql es la unica extension imprescindible (PostgreSQL); intl la pide
# Laravel para el formato de numeros y fechas en es-PE, y opcache evita
# recompilar el codigo en cada peticion.
RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        intl \
        opcache \
        zip \
        gd

# La imagen de FrankenPHP NO trae Composer. Se copia el binario de la imagen
# oficial en lugar de descargar el instalador, que obligaria a verificar el
# hash a mano en cada construccion.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Las dependencias se instalan ANTES de copiar el codigo para que el cache de
# capas de Docker sobreviva a un cambio en el codigo: solo se reinstala cuando
# cambian composer.json o composer.lock.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY . .

# --no-dev deja fuera las herramientas de desarrollo; --optimize-autoloader
# construye un mapa de clases, que en produccion evita recorrer el disco en
# cada carga.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi

# Laravel escribe cache, vistas compiladas y logs aqui.
#
# Hay que CREAR los directorios, no solo darles permiso: su contenido esta en
# .gitignore y ademas en .dockerignore, asi que no llegan con el codigo. Si
# faltan, `view:cache` muere con "View path not found" en el arranque, que es
# un mensaje que no apunta a la causa real.
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# La imagen de FrankenPHP marca su binario con CAP_NET_BIND_SERVICE para poder
# escuchar en los puertos 80 y 443 sin ser root. Render ejecuta los
# contenedores con privilegios restringidos y se niega a lanzar un binario con
# capacidades: el arranque muere con "frankenphp: Operation not permitted",
# que no menciona ni capacidades ni permisos de puerto.
#
# Aqui esa capacidad sobra, porque el servicio escucha en el puerto alto que
# inyecta la plataforma. Se retira y el binario queda como uno normal.
RUN setcap -r /usr/local/bin/frankenphp 2>/dev/null || true

# Render inyecta PORT en tiempo de ejecucion; 8080 solo sirve cuando se prueba
# el contenedor a mano con `docker run`.
ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint"]
