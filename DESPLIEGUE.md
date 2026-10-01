# Despliegue a producción · versión 1

Arquitectura del entorno desplegado, procedimiento y decisiones. Este archivo
es también la evidencia del punto 20 de la guía del APF2.

---

## 1. Arquitectura

| Capa | Servicio | Por qué |
|---|---|---|
| Base de datos | Neon · PostgreSQL 16 gestionado | Mismo motor que en desarrollo. Reanuda solo tras inactividad, así que un enlace revisado semanas después sigue respondiendo. Copias de seguridad y restauración a un punto en el tiempo incluidas. |
| API | Render · contenedor Docker | Laravel necesita un proceso persistente: colas, comandos programados y conexiones de base reutilizadas. Un entorno sin servidor obligaría a rediseñar esas tres cosas. |
| Front-End | Vercel · sitio estático | El build de Vite produce archivos estáticos. Una red de distribución los sirve desde el nodo más cercano, que es lo que corresponde. |

Separar API y Front-End en dos proveedores no es un capricho: tienen
necesidades opuestas. El Front-End quiere estar replicado y cerca del usuario;
la API quiere estar cerca de la base de datos y no replicarse.

---

## 2. Estado actual

| Componente | Estado | Comprobación |
|---|---|---|
| Base de datos | **Operativa** | Neon · 3 361 productos, 3 363 lotes, 17 086 unidades, 41 migraciones |
| API | **Desplegada** | `GET /api/salud` → `{"ok":true,"base":true}` en botica-san-juan-api.onrender.com |
| Front-End | **Desplegado** | botica-san-juan.vercel.app responde 200 sin exigir autenticación |
| Cuentas de demostración | **Creadas** | El arranque las siembra: `Cuenta lista: 10000001` y `10000002` |
| Control de acceso | **Verificado** | `/api/productos` → 200; `/api/tablero` y `/api/pedidos` → 401 sin token |

### Cuatro fallos que solo aparecieron al desplegar

Ninguno daba síntoma en desarrollo. Se dejan anotados porque el mensaje de
error no apuntaba a la causa en ninguno de los cuatro casos.

| # | Síntoma | Causa real |
|---|---|---|
| 1 | `composer: not found` al construir | La imagen `dunglas/frankenphp` no trae Composer. Se copia el binario de la imagen oficial. |
| 2 | «Falta la configuración de base de datos» con la base bien puesta | El arranque exigía `DB_HOST`, pero `config/database.php` lee `DB_URL`, que es la que declara el blueprint. |
| 3 | `View path not found` al cachear vistas | `storage/framework/views` no existe: su contenido está en `.gitignore` **y** en `.dockerignore`. Hay que crearlo con `mkdir -p`. |
| 4 | `frankenphp: Operation not permitted` | El binario trae `CAP_NET_BIND_SERVICE` y Render no ejecuta binarios con capacidades. Aquí sobra: escucha en un puerto alto. Se retira con `setcap -r`. |

El primero se encontró reproduciendo la construcción en local con
`docker build`, que dio el error exacto en segundos en lugar de esperar al
panel. Los otros tres, leyendo el registro de despliegue.

---

## 3. Qué datos se llevaron a producción y cuáles no

| Tabla | ¿Se copia? | Motivo |
|---|---|---|
| `categorias`, `subcategorias`, `productos`, `lotes` | Sí | No identifican a nadie. Son lo que hace representativa la demostración. |
| `usuarios` | **No** | Nombre, DNI, correo y teléfono reales de cinco personas. |
| `pedidos`, `pedido_detalles`, `pedido_pagos` | **No** | Contienen nombre y documento de clientes. |
| `comprobantes_electronicos` | **No** | Llevan el RUC del contribuyente. |

El entorno desplegado es una URL pública que se entrega como evidencia
académica. Publicar ahí datos personales de terceros para ilustrar un trabajo
de curso no es aceptable, aunque técnicamente fuera trivial.

En su lugar, `UsuariosDemostracionSeeder` crea dos cuentas ficticias cuyas
contraseñas llegan por variables de entorno y no viven en el repositorio.

---

## 4. Variables de entorno de la API

| Variable | Valor | Nota |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | Con `true`, una excepción devuelve al navegador la traza completa y el contenido del entorno. |
| `APP_KEY` | `base64:…` | `php artisan key:generate --show` |
| `APP_URL` | URL pública de la API | |
| `DB_CONNECTION` | `pgsql` | |
| `DB_HOST` | Endpoint **directo** de Neon | Ver el apartado 6. |
| `DB_PORT` | `5432` | |
| `DB_DATABASE` | `botica_san_juan` | |
| `DB_USERNAME` / `DB_PASSWORD` | Credenciales de Neon | |
| `DB_SSLMODE` | `require` | Neon rechaza conexiones sin cifrar. |
| `CORS_ALLOWED_ORIGINS` | URL del Front-End | Sin `*`: el navegador rechaza el comodín junto con credenciales. |
| `SESSION_DRIVER` | `array` | La API autentica con tokens; no hay sesiones de navegador que guardar. |
| `LOG_CHANNEL` | `stderr` | En un contenedor los registros van a la salida estándar, no a un archivo que se pierde al reiniciar. |

---

## 5. Procedimiento

### 5.1. API en Railway

1. Nuevo proyecto → **Deploy from GitHub repo** → `ChrisRafaile/botica-san-juan`.
2. En el servicio, *Settings → Root Directory*: `botica-san-juan-backend`.
   Sin esto Railway busca el `Dockerfile` en la raíz del repositorio, donde no
   está: el repositorio contiene dos aplicaciones.
3. *Variables*: las de la tabla anterior.
4. *Settings → Networking → Generate Domain*.
5. El primer despliegue aplica las migraciones por sí solo desde el
   `entrypoint`.

Comprobación:

```
GET https://<api>/api/salud   →  {"ok":true,"base":true,"hora":"…"}
```

### 5.2. Front-End en Vercel

1. Instalar la aplicación de GitHub de Vercel en el repositorio.
2. Importar el repositorio con *Root Directory* `botica-san-juan-frontend`.
3. Variable `VITE_API_URL` = `https://<api>/api`.
4. Desplegar.

`VITE_API_URL` se incrusta en el momento de compilar, no se lee en ejecución:
cambiarla exige volver a compilar. Es la diferencia entre una variable de
build y una de tiempo de ejecución, y confundirlas produce un front que apunta
a la API equivocada sin ningún error visible.

### 5.3. Cerrar el círculo

Con la URL del Front-End ya conocida, actualizar `CORS_ALLOWED_ORIGINS` en la
API. Hasta ese momento el navegador bloquea cada respuesta, aunque la API
responda correctamente a `curl`.

---

## 6. Decisiones que costaron un fallo

**El endpoint de Neon tiene que ser el directo, no el `-pooler`.**
El agrupador de conexiones es PgBouncer en modo transacción, que no conserva
las sentencias preparadas entre consultas. PDO las usa siempre, así que la
primera migración fallaba con `SQLSTATE[25P02]: current transaction is
aborted` —un mensaje que señala a la sentencia siguiente, no a la que falló.

**El arranque falla a propósito si falta `APP_KEY`.**
Sin esa comprobación, Laravel arranca y responde 500 en la primera petición
con un error que no menciona la clave.

**Las migraciones se aplican antes de escuchar.**
Si una falla, el contenedor muere y el despliegue se marca como fallido.
Atender peticiones contra un esquema a medio aplicar es peor que estar caído:
el error aparece más tarde y en forma de datos incorrectos.
