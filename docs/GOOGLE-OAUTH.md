# Acceso con Google

> Estado: implementado y probado, con 22 pruebas de servidor. **Sin
> credenciales todavía**: falta crear el cliente de OAuth en Google Cloud
> Console. Mientras `GOOGLE_CLIENT_ID` esté vacío, el botón no aparece en el
> portal.

---

## 1. Qué tienes que hacer en Google Cloud Console

Lo que sigue es lo único que hace falta. Todo lo demás ya está construido.

### Paso 1 · Crear o elegir el proyecto

En **console.cloud.google.com**, selector de proyecto arriba a la izquierda →
**Proyecto nuevo**. Nombre sugerido: `Botica San Juan`.

### Paso 2 · Configurar la pantalla de consentimiento

Menú → **APIs y servicios** → **Pantalla de consentimiento de OAuth**.

| Campo | Valor |
|---|---|
| Tipo de usuario | **Externo** |
| Nombre de la aplicación | `Botica San Juan` |
| Correo de asistencia | el tuyo |
| Dominios autorizados | `vercel.app` y `onrender.com` |
| Datos de contacto del desarrollador | el tuyo |

En **Permisos (scopes)**, añade solo estos tres:

```
openid
.../auth/userinfo.email
.../auth/userinfo.profile
```

No pidas ninguno más. Cada permiso extra alarga la pantalla que ve el cliente
y obliga a justificarlo si algún día Google revisa la aplicación.

### Paso 3 · Usuarios de prueba (importante mientras no publiques)

La aplicación queda en estado **«En pruebas»**, y en ese estado **solo pueden
entrar las cuentas que añadas a mano** como usuarios de prueba. Si no añades
la tuya, el botón fallará con `access_denied` y parecerá un fallo del código.

En **Usuarios de prueba** → **Añadir usuarios** → tu correo de Google, y los
de quien vaya a probarlo o sustentar.

### Paso 4 · Crear las credenciales

**APIs y servicios** → **Credenciales** → **Crear credenciales** → **ID de
cliente de OAuth** → tipo **Aplicación web**. Nombre: `Portal Botica San Juan`.

**Orígenes autorizados de JavaScript** (de dónde sale la petición):

```
http://localhost:5173
https://botica-san-juan.vercel.app
```

**URI de redireccionamiento autorizados** (adónde vuelve Google). Estos son
los que de verdad importan:

```
http://127.0.0.1:8083/api/auth/google/callback
https://botica-san-juan-api.onrender.com/api/auth/google/callback
```

Pulsa **Crear** y copia el **ID de cliente** y el **secreto de cliente**.

### Paso 5 · Pegarlos donde van

En `botica-san-juan-backend/.env` —que está en `.gitignore` y no sube al
repositorio—:

```
GOOGLE_CLIENT_ID=...apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=http://127.0.0.1:8083/api/auth/google/callback
```

Y en producción, como variables de entorno del servicio de Render, con
`GOOGLE_REDIRECT_URI` apuntando a la URL de Render.

El secreto **no se pega en el portal**. Es el backend quien canjea el código
por el perfil; un secreto en el front-end deja de serlo en cuanto alguien abre
las herramientas del navegador.

---

## 2. Los tres errores que vas a encontrar, y qué significan

**`redirect_uri_mismatch`.** El más común con diferencia. Google compara la
URI **carácter a carácter**. Falla por:

- una barra de más al final;
- `localhost` donde registraste `127.0.0.1`, o al revés —**no son lo mismo**
  para Google, aunque lo sean para tu máquina—;
- `http` donde registraste `https`;
- un puerto distinto. Si lanzas la API con `php artisan serve` sin `--port`,
  sale en el 8000 y la URI registrada apunta al 8083.

**`access_denied`.** O cancelaste en la pantalla de Google, o tu cuenta no
está en la lista de usuarios de prueba del paso 3.

**`invalid_client`.** El ID o el secreto no coinciden con los del proyecto, o
quedó un espacio al pegarlos en el `.env`.

---

## 3. El problema que este sistema tenía que resolver, y cómo

> **Google acredita un correo. Este sistema identifica a las personas por
> DNI.**

El acceso se hace con DNI y contraseña, y el documento acaba en el comprobante
que se envía a la SUNAT. Google no sabe el DNI de nadie y no hay forma de
deducirlo. Había tres salidas y dos eran malas:

1. **Inventar un DNI de relleno.** Descartada: ese número viajaría a un
   comprobante tributario. Un documento falso en una boleta no es un detalle
   de interfaz.
2. **No dejar que Google cree cuentas, solo que entre a las que ya existen.**
   Descartada: convierte el botón en algo que falla la primera vez, que es
   justo cuando se usa.
3. **Permitir la cuenta sin DNI y pedirlo cuando de verdad hace falta.** Es la
   que se tomó.

En consecuencia `usuarios.dni` pasa a ser anulable, y los dos sitios que lo
daban por presente quedan resueltos:

- **el checkout** pide el documento a quien no lo tiene, exactamente igual que
  ya se lo pedía a un invitado;
- **el perfil** permite completarlo **una** vez. Cambiarlo después no, porque
  ya figura en comprobantes emitidos.

El acceso con DNI y contraseña no se ve afectado: una cuenta sin DNI no puede
entrar por ahí, y es lo correcto, porque entra por Google.

---

## 4. Por qué el token no viaja en la URL

El recorrido que se encuentra en la mitad de los tutoriales es: la API recibe
el retorno de Google, crea el token y redirige al portal con el token en la
dirección. Eso deja el token:

- en el historial del navegador,
- en la cabecera `Referer` de la siguiente petición a cualquier tercero,
- en los registros de acceso de cualquier intermediario,
- y a la vista en la barra de direcciones, en el mostrador.

Aquí la API redirige con un **código de un solo uso** que vive 60 segundos en
caché. El portal lo canjea por `POST` y recibe el token en el cuerpo de la
respuesta, que no se registra en ningún sitio. Un código sacado del historial
no sirve: o ya se gastó, o caducó. Hay dos pruebas que lo fijan.

En caché se guarda el **hash** del código, no el código: si alguien llegara a
leer la caché, lo que encuentra no canjea nada.

---

## 5. Las otras decisiones de seguridad

**El parámetro `state`.** Sin él, cualquiera puede inducir a una persona a
completar un flujo de OAuth que no empezó y acabar con la sesión de **otra**
cuenta de Google en su navegador. Socialite lo guarda en la sesión, pero esta
API es sin estado: el `state` se genera en el backend, se guarda cinco minutos
junto con la IP que lo pidió, y se comprueban las dos cosas al volver. Sirve
una sola vez.

**El correo tiene que venir verificado.** Google puede devolver un correo no
verificado en algunos casos de Workspace. Vincular por un correo sin verificar
permitiría apropiarse de la cuenta de quien se registró con esa dirección. Si
el campo no viene, se asume **no** verificado.

**Vincular por correo es aceptable aquí, y conviene saber por qué.** Es la vía
clásica de apropiación de cuentas, así que no se hace a ciegas: Google ya
acreditó el control de ese buzón, y controlar el buzón es exactamente lo que
este sistema pide para dar por verificada una dirección. Quien recibe el
correo de recuperación puede, de todas formas, hacerse con la cuenta.

**Entrar con Google no asciende a nadie** ni toca la contraseña. Un
administrador que vincule su cuenta sigue entrando con su contraseña.

**Manda el identificador de Google, no el correo.** Una persona puede cambiar
el correo de su cuenta de Google; el `sub` no cambia. Vincular por correo
dejaría la cuenta colgando de un dato mutable.

**`prompt=select_account`.** Evita el peor momento de este flujo: que Google
reutilice en silencio la sesión abierta y la persona acabe entrando con una
cuenta que no quería. En un equipo compartido de mostrador, eso es entrar como
otra persona.

---

## 6. Un fallo que apareció al construir esto

La cuenta creada por Google nacía **sin el correo verificado**, aunque el
código pasaba `email_verified_at => now()` al crearla.

El motivo: `email_verified_at` **no está en `$fillable`**, y eso es
deliberado —dejarlo asignable en masa significaría que cualquier endpoint que
pase el cuerpo de la petición a `create()` permitiría autoverificarse un
correo—. La asignación en masa lo descartaba **en silencio**, sin error. El
resultado habría sido que el portal pidiera confirmar un correo que Google
acababa de acreditar.

Corregido con un `forceFill` explícito, y fijado por la prueba
`test_una_cuenta_nueva_nace_sin_dni_y_con_el_correo_verificado`.

---

## 7. Cómo se verificó

22 pruebas en `AccesoGoogleTest`, con Google sustituido por un doble. Las que
importan no son las de «se puede entrar», sino:

| Prueba | Qué impide |
|---|---|
| `el_token_no_viaja_en_la_url_de_retorno` | Que el token quede en el historial |
| `un_retorno_sin_state_no_crea_ninguna_cuenta` | Sesiones inducidas desde fuera |
| `el_state_sirve_una_sola_vez` | Reenviar el retorno |
| `el_codigo_sirve_una_sola_vez` | Abrir una segunda sesión con un código viejo |
| `un_correo_sin_verificar_no_vincula_nada` | Apropiación de cuenta |
| `un_correo_que_ya_cuelga_de_otra_cuenta_de_google_no_se_roba` | Reuso de dirección |
| `entrar_con_google_no_asciende_a_nadie` | Escalada de privilegios |
| `manda_el_identificador_de_google_y_no_el_correo` | Cuentas colgando de un dato mutable |
| `el_checkout_pide_el_documento_a_quien_no_tiene_dni` | Boletas sin adquirente |
| `un_dni_ya_registrado_no_se_cambia_desde_el_perfil` | Reescribir comprobantes emitidos |

**Lo que no se ha probado:** el flujo real contra Google. No hay credenciales
todavía, así que el doble sustituye a Google en todas las pruebas. Eso cubre
la lógica del sistema y **no** cubre que la URI de redirección esté bien
escrita en la consola, que es precisamente donde falla la mitad de las
integraciones. Esa comprobación es el primer clic después del paso 5.

---

## 8. Lo que falta

1. **Crear el cliente de OAuth** y pegar las credenciales (sección 1).
2. **Probar el flujo real** y capturar la evidencia.
3. **Publicar la aplicación** en Google cuando deje de ser un entregable de
   curso: en estado «En pruebas» solo entran las cuentas de la lista, y el
   consentimiento caduca cada siete días.
4. **Desvincular Google** desde el perfil. Hoy se puede vincular y no soltar;
   no es urgente porque la cuenta conserva su acceso por DNI y contraseña,
   pero es una puerta que falta.
