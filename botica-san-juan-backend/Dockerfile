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

FROM dunglas/frankenphp:1-php8.3-alpine

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

# Render inyecta PORT en tiempo de ejecucion; 8080 solo sirve cuando se prueba
# el contenedor a mano con `docker run`.
ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint"]
