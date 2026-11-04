# Recetas médicas

> Estado: implementado y probado de punta a punta en local, con 26 pruebas de
> servidor y 9 de componente. El archivo vive en el disco privado; no existe
> ninguna URL pública que lo sirva.

## 1. La diferencia con la foto de perfil

La tubería de subida se parece a la de CAM-012, pero una decisión cambia el
diseño entero: **una receta no es una foto de perfil**. Lleva el nombre del
paciente, el del médico y, por el medicamento que prescribe, su diagnóstico.

| | Foto de perfil | Receta médica |
|---|---|---|
| Disco | `storage/app/public` | `storage/app/private` |
| Cómo se sirve | URL pública en `/storage/...` | Endpoint que comprueba quién pide |
| Quién la ve | Cualquiera con la URL | Su dueño y el administrador |
| Tamaño máximo | 2 MB | 8 MB |

En el disco público bastaría adivinar la URL —o que alguien la reenvíe— para
leer la receta de otra persona. Por eso la ruta del archivo **no sale nunca en
las respuestas de la API**: el modelo la declara en `$hidden`, y hay una prueba
que recorre la respuesta buscando la cadena `recetas/` para asegurarse.

El efecto práctico en el navegador: `<img src>` no envía la cabecera de
autorización, así que el archivo se descarga con la sesión y se pinta desde un
object URL en memoria. Se descartó la alternativa habitual —una URL firmada
temporal— porque una URL, aunque caduque, se puede reenviar mientras viva.

## 2. La regla que protege al cliente

> **Un pedido con medicamentos de venta bajo receta no se completa sin una
> receta validada.**

La comprobación está en el servidor, en `PedidoController::update`, y no solo
en el botón de la pantalla. La pantalla puede deshabilitarlo —y lo hace—, pero
esa misma ruta la puede llamar cualquiera con sesión de administrador desde
fuera de la aplicación. Dispensar sin receta lo que la ley exige con receta no
es un error de interfaz.

Se bloquea el paso a `completado`, que es cuando el producto sale del
mostrador. `confirmado` sí se permite: preparar el pedido mientras el químico
farmacéutico revisa la receta es el flujo normal de la botica.

Comprobado en vivo contra la API desplegada en local:

```
PUT /api/pedidos/217  {"estado":"completado"}   con receta rechazada  → 422
PUT /api/pedidos/217  {"estado":"completado"}   con receta validada   → 200
```

## 3. Lo que el sistema no decide

No hay validación automática. No se lee el archivo, no hay OCR, no se
comprueba que el medicamento recetado coincida con el del pedido. La receta
nace `pendiente` y solo la mueve una persona.

De ahí tres detalles de interfaz que parecen pequeños y no lo son:

- **Subir no es acreditar.** Al adjuntarla, el portal dice «la revisará antes
  de preparar tu pedido». No hay tilde verde ni «recibido correctamente»: el
  único verde de la pantalla aparece cuando alguien con título ha decidido, y
  entonces lleva su nombre al lado.
- **Rechazar exige escribir por qué.** El botón no hace nada hasta que hay
  motivo. Un «rechazada» a secas deja al cliente adivinando qué repetir, y lo
  que llega después es una segunda receta igual de inservible.
- **Cuatro estados, no tres.** «Pendiente de adjuntar» y «pendiente de
  revisar» parecen lo mismo: en el primero la pelota está en el tejado del
  cliente y en el segundo en el del mostrador. Juntarlos haría que el
  boticario buscara recetas que nadie ha subido.

## 4. Qué se guarda

`recetas` tiene una fila por pedido —`pedido_id` es único— con el archivo, su
tipo real, el estado, el motivo del rechazo, **quién** revisó y **cuándo**, y
un contador de intentos.

El contador tiene un uso concreto: un 3 le dice al boticario que el cliente ya
lo intentó dos veces, que es justamente cuando conviene llamarle en vez de
rechazar otra.

Adjuntar una receta nueva sustituye a la anterior, borra su archivo del disco
y reinicia el estado. Una receta **ya validada** no se sustituye desde el
portal (409): el boticario la miró y decidió, y dejar que el cliente la cambie
después convertiría esa decisión en provisional sin que nadie se entere.

## 5. Qué se acepta y cómo se comprueba

JPG, PNG, WEBP y PDF, hasta 8 MB. Las dos formas en que llega una receta de
verdad: la foto del móvil y el PDF del consultorio. Rechazar el PDF obligaría
a imprimirlo para volver a fotografiarlo.

No se acepta HEIC: lo produce el iPhone pero el navegador del mostrador no lo
pinta, y una receta que el boticario no puede ver no sirve de nada.

La comprobación mira el **contenido**, no la extensión, y el nombre del
archivo lo genera Laravel. El que trae el cliente se guarda aparte, como dato,
y nunca se usa para construir una ruta: «../../.env.jpg» es un nombre de
archivo perfectamente válido.

## 6. Dos fallos que aparecieron al construir esto

**El primero era mío, del bloque anterior.** Al poner la aplicación en español
se añadió `lang/es.json`, que cubre las frases de las plantillas de correo pero
no los mensajes de validación, que Laravel busca en un archivo de arreglo. El
resultado se vio al adjuntar la primera receta:

```json
{"message":"validation.required","errors":{"archivo":["validation.required"]}}
```

Y eso se pintaba tal cual en pantalla, **en todos los formularios de la
aplicación**: registro, perfil, contacto, productos. Las pruebas no lo
detectaron, y la razón merece anotarse: `assertJsonValidationErrors('archivo')`
comprueba qué campo falla, no qué dice el mensaje. Una suite entera en verde
con la aplicación mostrando claves en crudo.

Corregido con `lang/es/` completo —validación, autenticación, contraseñas y
paginación—, con `APP_FALLBACK_LOCALE` de vuelta a `en` como red de seguridad,
y con `MensajesDeValidacionTest`, que mira el texto y falla si empieza por
`validation.`.

**El segundo era anterior y estaba a la vista.** La lista de pedidos del panel
decía «0 producto(s)» y «Sin productos» en **todas** las filas. La pantalla
leía `pedido.pedidoDetalles` y la API devuelve `pedido_detalles`: Eloquent pasa
el nombre de la relación a snake_case al serializar, así que la clave en
camelCase era siempre `undefined`. El detalle del pedido mostraba además el
aviso de «pedido sin líneas» con las líneas cargadas en la respuesta.

Se descubrió porque el pedido de ensayo tenía una línea de amoxicilina y la
pantalla decía que no tenía ninguna.

## 7. Cómo se verificó

26 pruebas de servidor (`RecetaTest`) sobre PostgreSQL 16. Las que importan no
son las de «el archivo se guarda», sino:

- que la receta de una persona no la pueda leer otra, ni con el identificador
  correcto, ni sin sesión;
- que el archivo no acabe en el disco público;
- que un pedido de mostrador sin cuenta asociada no pertenezca a cualquiera
  (`null === null` daría acceso a toda cuenta con sesión);
- que no se pueda completar el pedido sin receta validada, por ninguna de las
  tres vías: sin receta, con receta sin revisar, con receta rechazada;
- que validar y rechazar dejen constancia de quién decidió.

9 pruebas de componente (`zona-de-receta.spec.ts`) sobre el gesto de arrastrar
y soltar, que es justo lo que una captura **no** demuestra: la imagen enseña la
zona dibujada, no que al soltar un PDF pase algo ni que al soltar un ejecutable
no pase nada. Cubren además que el resaltado no parpadee al pasar por los
hijos, que elegir dos veces el mismo archivo cuente dos veces, y que el object
URL de la vista previa se libere.

### Una limitación de las capturas

El navegador integrado no puede inyectar un archivo real en un campo de
archivo, así que **las capturas se tomaron sobre estados creados llamando a la
API**, no arrastrando el archivo con el ratón. Las pantallas son reales y los
datos también; el gesto en sí lo cubren las pruebas de componente. Conviene
decirlo en lugar de dejar suponer que hubo un arrastre.

## 8. Evidencias

En `docs/evidencias/recetas/`, el ciclo completo sobre el pedido #217:

| | Qué muestra |
|---|---|
| R01 | El pedido con el distintivo «Falta receta» |
| R02 | La zona de arrastre del cliente |
| R03 | Receta enviada, sin visto bueno: «la revisará antes de preparar tu pedido» |
| R04 | La lista del panel con el distintivo «Por revisar» y la línea de producto |
| R05 | El visor con la receta descargada con la sesión |
| R06 | El rechazo pidiendo motivo antes de dejar confirmar |
| R07 | Rechazada, con quién la rechazó y por qué |
| R08 | La clienta viendo el motivo y pudiendo adjuntar otra |
| R09 | Receta validada y pedido ya entregado |

## 9. Lo que falta

1. **Avisar al cliente por correo cuando se rechaza la receta.** Hoy tiene que
   entrar al portal a mirarlo. El transporte ya está configurado; es una
   notificación más.
2. **Caducidad de la receta.** Una receta tiene vigencia legal y el sistema no
   la modela: guarda la imagen, no la fecha de emisión ni su validez.
3. **Retención.** No hay política de borrado. Un documento clínico no debería
   quedarse indefinidamente, y decidir cuánto tiempo se conserva es una
   decisión del negocio, no del código.
4. **El mostrador.** Las ventas presenciales (`origen = pos`) no pasan por
   aquí: en el mostrador la receta se ve en papel. Si algún día se quiere
   registrar, el modelo lo admite pero la pantalla no existe.

## 10. Reserva

Las capturas se tomaron sobre cuentas de prueba (`*@prueba.test`) y una
imagen de receta generada para la ocasión, que lo dice en su propio texto. No
corresponden a ninguna persona ni a ninguna prescripción real.
