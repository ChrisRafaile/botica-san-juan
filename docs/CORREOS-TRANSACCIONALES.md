# Correos transaccionales

> Estado: implementado y probado de punta a punta en local con el transporte
> `log`. **Nunca se ha enviado un correo real todavía**: falta un dominio
> verificado y el visto bueno para mandar el primero.

## 1. Qué hay y qué no

| | Estado |
|---|---|
| Clave de envío de Resend (`sending_access`) | Creada, en `.env` local |
| Dominio verificado | **No.** La cuenta no tiene ninguno |
| Remitente | `onboarding@resend.dev` (el único posible sin dominio) |
| Verificación de cuenta al registrarse | Funciona |
| Recuperación de contraseña | Funciona |
| Envío real contra Resend | **Sin ejecutar** |

Sin dominio verificado, Resend solo acepta `onboarding@resend.dev` como
remitente y **solo entrega al correo de la persona dueña de la cuenta**. Eso
basta para probar, no para atender clientes: un cliente que se registre no
recibirá nada. Lo que falta es un dominio y sus registros DNS, que se añaden en
el panel del proveedor del dominio y no puede hacerlo el sistema.

Mientras tanto, `MAIL_MAILER=log` deja el correo entero escrito en
`storage/logs/laravel.log`. Se desarrolla y se prueba igual, sin gastar cuota
ni escribirle a nadie desde una máquina de desarrollo.

## 2. La regla que gobierna la recuperación

> **El formulario responde lo mismo exista o no la cuenta.**

No es prudencia de manual. Un formulario público que distinguiera los dos casos
sería un verificador de correos: se prueban mil direcciones y se sabe cuáles
son clientes. En una botica eso dice algo más que «esta dirección existe»; dice
que esa persona compra medicamentos aquí.

De ahí tres consecuencias concretas:

- el mensaje dice *«si existe una cuenta…»*, que es cierto en los dos casos, en
  lugar de prometer un correo que quizá no salió;
- el limitador cuenta por IP **y** por correo: solo por IP, quien tenga
  direcciones rotativas castiga el buzón de un tercero; solo por correo, una
  sola IP barre miles de direcciones;
- «token inválido» y «correo desconocido» devuelven exactamente el mismo
  cuerpo y el mismo código. Separarlos reabriría la misma puerta.

Hay dos pruebas que comparan las respuestas **byte a byte**:
`RecuperacionContrasenaTest::test_la_respuesta_es_identica_exista_o_no_la_cuenta`
y `::test_el_fallo_no_distingue_token_malo_de_correo_inexistente`.

El coste es real y se asume: quien se equivoca al teclear su correo no recibe
nada y no se le avisa.

## 3. Por qué el enlace de verificación va a la API y no al portal

El primer diseño era el contrario: el correo llevaba a una pantalla del portal
con el enlace firmado como parámetro, y la pantalla lo invocaba con `fetch`.

No funcionó, y el motivo no era un descuido. La API y el portal viven en
orígenes distintos, así que esa llamada era una petición entre orígenes; en el
ensayo el navegador la bloqueó antes incluso de llegar a CORS. Arreglarlo
habría significado abrir CORS para un enlace que llega por correo, que es justo
el tipo de cosa que conviene no hacer.

Ahora el enlace apunta a la API, firmado. La persona **navega** —no hay llamada
de fondo—, la API valida la firma, marca el correo y redirige al portal con el
resultado en `?resultado=confirmado | ya-confirmado | invalido`. La pantalla
solo lee ese parámetro.

La firma se comprueba dentro del controlador y no con el middleware `signed`,
porque ese middleware devuelve la página de error de Laravel: para alguien que
acaba de pulsar un enlace en su bandeja de entrada, eso es una pantalla en
inglés y sin salida.

## 4. Lo que protege cada flujo

**Verificación**

- El enlace **caduca** (60 min). Un enlace eterno en una bandeja de entrada es
  una credencial permanente.
- El hash del correo viaja en la URL y se compara con `hash_equals`. Cubre un
  caso que la firma no ve: que la persona haya **cambiado de correo** después
  de pedir la verificación. Ese enlace acredita una dirección que ya no es la
  de la cuenta.
- Cambiar el correo desde el perfil **retira la verificación anterior** y manda
  una nueva. Sin eso bastaría registrarse con un correo propio, confirmarlo y
  apuntar luego la cuenta a una dirección que nadie ha comprobado, conservando
  el sello de verificada. Cambiar solo las mayúsculas no cuenta: es el mismo
  buzón.
- El reenvío **exige sesión**. Si aceptara un correo arbitrario sería un motor
  de envío gratuito contra terceros, y delataría qué cuentas existen.
- Un fallo del servicio de correo **no tumba el registro**: la cuenta ya está
  creada, y un 500 dejaría al cliente creyendo que su registro falló con una
  contraseña que no volverá a ver. Se registra el fallo —con el id, nunca con
  el correo— y la respuesta trae `verificacion_enviada: false`.

**Recuperación**

- El token se guarda **como hash** en `password_reset_tokens`, sirve una sola
  vez y caduca a los 60 minutos. En claro solo existe dentro del correo: no se
  escribe en el log, no vuelve en ninguna respuesta y la pantalla no lo guarda
  en el navegador.
- Al restablecer se **revocan todos los tokens de Sanctum**, sin excepción. A
  diferencia del cambio desde el perfil —donde se conserva la sesión en uso—,
  aquí el punto de partida es que la contraseña anterior pudo estar en manos de
  otra persona.
- El endpoint que fija la contraseña **no comparte limitador** con el que
  envía: aquí no sale ningún correo, y una contraseña rechazada por la
  validación consumiría cupo. Con el límite de envío, tres tecleos torpes
  dejarían a alguien fuera de su propio restablecimiento.
- El saludo del correo **no lleva nombre**. Si el mensaje llega a la persona
  equivocada, el saludo no tiene por qué confirmarle de quién es la cuenta.

## 5. Una cosa que se desactivó al instalar el paquete

`resend/resend-laravel` registra por su cuenta un `POST /resend/webhook`, y su
controlador **solo comprueba la firma si existe `RESEND_WEBHOOK_SECRET`**. Sin
secreto, eso es un punto de entrada público que acepta eventos inventados.

Se desactiva con `RESEND_ROUTES=false`, en `.env` y en `.env.example`. Para
activarlo hay que definir antes el secreto.

## 6. Cómo se ensayó

Con `MAIL_MAILER=log`, contra la base local y el portal en `localhost:5173`:

1. Registro por API → el correo de verificación queda en `laravel.log`.
2. Se abre el enlace firmado → la API redirige al portal: **«Correo
   confirmado»** (C05), y `email_verified_at` deja de ser nulo.
3. Formulario «¿Olvidaste tu contraseña?» (C01) → respuesta que no revela si la
   cuenta existe (C02).
4. Se abre el enlace del correo → formulario con el medidor de contraseña
   (C03).
5. Se guarda → **«Contraseña actualizada»** (C04).

Comprobado después en la base: la contraseña nueva vale, la vieja no, y los
tokens de sesión de esa cuenta pasaron de 1 a **0**.

Las capturas y el texto de los dos correos están en
`docs/evidencias/correos/`.

## 7. Lo que falta

1. **Verificar un dominio en Resend** y cambiar `MAIL_FROM_ADDRESS` a una
   dirección propia (`no-responder@…`). Hasta entonces no se puede escribir a
   clientes.
2. **Enviar el primer correo real** y comprobar entrega, SPF/DKIM y que no cae
   en no deseados.
3. Decidir si la verificación pasa a ser **obligatoria** para comprar. Hoy no
   lo es: se avisa en el perfil y nada más. Las cuentas que ya existían
   quedaron marcadas con la fecha de su propio registro, no con «ahora», porque
   nadie les pidió confirmar nada en su momento.
4. En producción, `FRONTEND_URL` y `APP_URL` tienen que apuntar a los dominios
   reales. Los enlaces de los correos se construyen con ellos.

## 8. Reserva

Los enlaces y tokens que aparecen en las evidencias son de un ensayo local
contra cuentas de prueba (`*@prueba.test`). Sus firmas ya caducaron y las
cuentas no corresponden a ninguna persona real.
