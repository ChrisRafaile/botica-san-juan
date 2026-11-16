# Checklist de Activación · Google OAuth y Resend (Producción)

> **Propósito:** Guía de activación operativa paso a paso para habilitar el acceso con Google OAuth 2.0 y el envío de correos transaccionales con Resend en el entorno de producción (Render + Vercel / Dominios personalizados).
> **Regla de seguridad:** Nunca introducir ni versionar valores reales de credenciales. Los valores en esta guía son marcadores de formato.

---

## 1. Inventario de Variables de Entorno

Todas estas variables deben configurarse en el panel de **Render** (`Dashboard -> botica-san-juan-api -> Environment`).

| Variable | Dónde se obtiene | Formato esperado / Ejemplo representativo | Notas / Impacto |
|---|---|---|---|
| `GOOGLE_CLIENT_ID` | Google Cloud Console (`APIs y servicios -> Credenciales`) | `<id-cliente>.apps.googleusercontent.com` | Si está vacía, el botón de Google se oculta automáticamente. |
| `GOOGLE_CLIENT_SECRET` | Google Cloud Console (`APIs y servicios -> Credenciales`) | Cadena alfanumérica secreta (ej. `GOCSPX-...`) | **Nunca** compartir ni exponer al frontend. |
| `GOOGLE_REDIRECT_URI` | Derivada de la URL del backend en Render | `https://<api-dominio>.onrender.com/api/auth/google/callback` | Debe coincidir **carácter a carácter** con la consola de Google. |
| `FRONTEND_URL` | URL pública del portal web (Vercel o dominio propio) | `https://<portal-dominio>.vercel.app` (sin barra final) | **Crítico**: URL adonde redirige el callback tras autenticar. |
| `RESEND_API_KEY` | Panel de Resend (`API Keys -> Create API Key`) | `re_<alfanumérico>` | Permiso requerido: `Sending access`. |
| `MAIL_MAILER` | Configuración del backend | `resend` | Cambia el transporte de `log` a la API de Resend. |
| `MAIL_FROM_ADDRESS` | Dominio verificado en Resend | `no-responder@<tu-dominio-verificado.pe>` | En pruebas sin dominio propio: `onboarding@resend.dev`. |
| `MAIL_FROM_NAME` | Configuración de marca | `Botica San Juan` | Nombre que ve el cliente en su bandeja de entrada. |
| `APP_LOCALE` | Configuración regional | `es` | Garantiza mensajes de validación y plantillas en español. |

---

## 2. Paso a Paso para la Activación

### Fase 1 · Configuración en Google Cloud Console

1. Acceder a [console.cloud.google.com](https://console.cloud.google.com) y seleccionar o crear el proyecto `Botica San Juan`.
2. **Pantalla de consentimiento de OAuth** (`APIs y servicios -> Pantalla de consentimiento de OAuth`):
   - Tipo de usuario: **Externo**.
   - Nombre de la aplicación: `Botica San Juan`.
   - Correo de soporte del usuario y contacto del desarrollador: correo del administrador.
   - Dominios autorizados: agregar `onrender.com` y `vercel.app` (o el dominio personalizado).
   - Permisos (Scopes): agregar estrictamente:
     - `openid`
     - `https://www.googleapis.com/auth/userinfo.email`
     - `https://www.googleapis.com/auth/userinfo.profile`
3. **Usuarios de prueba** (mientras la app esté en estado "En pruebas"):
   - Agregar los correos de Gmail de evaluadores y docentes que sustentarán el proyecto.
4. **Crear credenciales de OAuth** (`APIs y servicios -> Credenciales -> Crear credenciales -> ID de cliente de OAuth`):
   - Tipo: **Aplicación web**.
   - Nombre: `Portal Botica San Juan`.
   - **Orígenes autorizados de JavaScript**:
     - `http://localhost:5173` (para pruebas locales)
     - `https://<portal-dominio>.vercel.app` (URL de producción del frontend)
   - **URI de redireccionamiento autorizados**:
     - `http://127.0.0.1:8083/api/auth/google/callback` (local)
     - `https://<api-dominio>.onrender.com/api/auth/google/callback` (producción Render)
   - Copiar el **ID de cliente** y el **Secreto de cliente**.

---

### Fase 2 · Configuración en Resend

1. Acceder a [resend.com](https://resend.com) e iniciar sesión.
2. **Verificación de Dominio** (`Domains -> Add Domain`):
   - Ingresar el dominio de la botica (ej. `boticasanjuan.pe`).
   - Agregar los registros DNS generados (DKIM, SPF / TXT, MX) en el panel de control DNS del registrador (Cloudflare, GoDaddy, etc.).
   - Esperar a que el estado cambie a **Verified**.
   - *Nota de contingencia:* Si no se dispone de dominio propio para la sustentación, utilizar temporalmente `MAIL_FROM_ADDRESS=onboarding@resend.dev` (atención: en este modo, Resend solo enviará correos al buzón del propietario de la cuenta).
3. **Generar API Key** (`API Keys -> Create API Key`):
   - Nombre: `botica-san-juan-prod`.
   - Permisos: `Sending access` (restringido a envío de correos).
   - Copiar la clave generada (`re_...`).

---

### Fase 3 · Carga de Variables en Render y Redeploy

1. Ir a [dashboard.render.com](https://dashboard.render.com).
2. Seleccionar el servicio web `botica-san-juan-api`.
3. Pestaña **Environment** -> **Add Environment Variable** e ingresar:
   - `GOOGLE_CLIENT_ID`
   - `GOOGLE_CLIENT_SECRET`
   - `GOOGLE_REDIRECT_URI` = `https://<api-dominio>.onrender.com/api/auth/google/callback`
   - `FRONTEND_URL` = `https://<portal-dominio>.vercel.app`
   - `RESEND_API_KEY`
   - `MAIL_MAILER` = `resend`
   - `MAIL_FROM_ADDRESS` = `no-responder@<tu-dominio-verificado.pe>` (o `onboarding@resend.dev`)
   - `MAIL_FROM_NAME` = `Botica San Juan`
   - `APP_LOCALE` = `es`
4. Guardar cambios (**Save changes**).
5. Desplegar: Menú **Manual Deploy** -> **Clear build cache & deploy**.
   - *El entrypoint (`docker/entrypoint.sh`) ejecutará `php artisan config:cache` para consolidar las nuevas variables en el arranque del contenedor.*

---

## 3. Plan de Pruebas de Verificación Post-Despliegue

Ejecutar las siguientes pruebas en orden tras confirmar que el despliegue finalizó con éxito:

### Prueba 1 · Disponibilidad de Google OAuth vía API
- **Comando:**
  ```bash
  curl -i https://<api-dominio>.onrender.com/api/auth/google/disponible
  ```
- **Respuesta esperada:**
  - Código HTTP: `200 OK`
  - Cabecera: `Content-Type: application/json`
  - Cuerpo:
    ```json
    {"disponible": true}
    ```

### Prueba 2 · URL de redirección a Google
- **Comando:**
  ```bash
  curl -i https://<api-dominio>.onrender.com/api/auth/google/redirect
  ```
- **Respuesta esperada:**
  - Código HTTP: `200 OK`
  - Cuerpo JSON con `url` que comienza con `https://accounts.google.com/o/oauth2/v2/auth?` e incluye los parámetros `state`, `client_id`, `prompt=select_account`, `scope=openid+profile+email` y `redirect_uri=https://<api-dominio>.onrender.com/api/auth/google/callback`.

### Prueba 3 · Visibilidad e interacción en el Portal Cliente
- **Acción:**
  1. Abrir en el navegador `https://<portal-dominio>.vercel.app/login`.
  2. Verificar que el botón **«Continuar con Google»** aparece sobre el separador "o".
  3. Pulsar el botón.
- **Resultado esperado:**
  - El navegador redirige fluidamente a la pantalla oficial de Google para seleccionar cuenta.
  - Al seleccionar un usuario de prueba autorizado, Google redirige al callback de la API, la API crea o vincula la cuenta, emite un código de 48 caracteres en caché y redirige a `https://<portal-dominio>.vercel.app/auth/google?codigo=...`.
  - El portal canjea el código en segundo plano (`POST /api/auth/google/intercambio`) y la persona queda con sesión activa.

### Prueba 4 · Envío de correo transaccional real (Recuperación de contraseña)
- **Comando:**
  ```bash
  curl -i -X POST https://<api-dominio>.onrender.com/api/password/olvide \
    -H "Content-Type: application/json" -H "Accept: application/json" \
    -d '{"email": "<correo-de-prueba@dominio.com>"}'
  ```
- **Respuesta esperada en API:**
  - Código HTTP: `200 OK`
  - Cuerpo JSON (el mismo exista o no la cuenta: el endpoint no enumera usuarios):
    ```json
    {"message": "Si existe una cuenta con ese correo, te enviamos un enlace para restablecer la contraseña. Revisa también la carpeta de no deseados."}
    ```
  - Lleva limitador (2 por minuto y 10 por hora por IP): no lo repitas en bucle.
- **Comprobación en Resend:**
  - Ir a `resend.com/emails`.
  - El correo debe figurar en la lista con estado **Delivered** (250 OK) hacia el destinatario, remitente configurado y plantilla corporativa con botón y enlace temporal firmado.

---

## 4. Procedimiento de Reversión Inmediata (Rollback)

Si se detectan fallos de configuración, tokens inválidos o bloqueos de entrega:

### Para revertir Google OAuth (sin apagar el portal ni la API):
1. En el panel de Render, eliminar o vaciar la variable `GOOGLE_CLIENT_ID`.
2. Guardar y pulsar **Manual Deploy** -> **Deploy latest commit**.
3. *Efecto inmediato:* `GET /api/auth/google/disponible` devolverá `{"disponible": false}`.
4. El botón de Google desaparecerá del portal del cliente en la siguiente carga, manteniendo el acceso convencional mediante DNI y contraseña al 100% operativo sin mostrar errores al usuario.

### Para revertir Resend:
1. En el panel de Render, cambiar la variable `MAIL_MAILER` de `resend` a `log`.
2. Guardar y redeployar.
3. *Efecto inmediato:* El backend dejará de comunicarse con la API de Resend y escribirá los correos en `storage/logs/laravel.log`. Los endpoints de registro y recuperación seguirán respondiendo exitosamente (200 OK) sin lanzar excepciones ni errores 500 al cliente.
