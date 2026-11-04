# Observabilidad y criterios de anonimización

Sistema Web de Gestión Botica San Juan · Semanas 17-18

---

## 1. El problema, antes de la configuración

Un panel de errores y un grabador de sesiones sirven para lo mismo: enterarse
de lo que falla sin esperar a que alguien lo cuente. El precio por omisión es
que ambos recogen mucho más de lo necesario.

En una tienda de zapatos eso es un problema de privacidad corriente. En una
botica es otra cosa. **Lo que una persona busca en el catálogo dice algo sobre
su salud.** «Amoxicilina» sugiere una infección; «test de embarazo» sugiere lo
que sugiere; el contenido del carrito es un historial de consumo, y una receta
médica lleva el diagnóstico escrito en la imagen.

De ahí la regla que gobierna toda la configuración que sigue:

> **No se recoge nada que permita saber qué medicamento mira o compra una
> persona concreta.**

Esto no es una preferencia estética. Es la razón por la que cuatro cosas que
las dos herramientas traen **activadas de fábrica** aquí están apagadas.

---

## 2. Qué está apagado de lo que viene encendido

| Comportamiento de fábrica | Qué recogería aquí | Decisión |
|---|---|---|
| `autocapture` de PostHog | Captura cada clic **con el texto del elemento pulsado**. En un catálogo, ese texto es el nombre del medicamento. | **Apagado.** Los eventos se mandan a mano y se sabe qué llevan. |
| `capture_pageview` automático | Manda la dirección completa, con el término de búsqueda en la cadena de consulta. | **Apagado.** La vista de página la manda el router con la URL ya depurada. |
| Grabación de sesión con texto visible | El nombre del producto en la ficha, las líneas del carrito, el motivo de rechazo de una receta. | **Enmascarado total** (`maskTextSelector: '*'`), y rutas enteras excluidas. |
| Cuerpo de petición y respuesta en las migas de pan de Sentry | Contraseñas, documentos, líneas de pedido. | **Se borra sin mirarlo.** |
| `send_default_pii` en Sentry (servidor) | IP, cookies y datos del usuario autenticado en cada error. | **`false`, y nunca otra cosa.** |
| Parámetros de las consultas SQL en las migas de pan | La consulta de búsqueda lleva el término tecleado; la de usuarios, el DNI. | **`sql_bindings => false`.** |

El detalle de por qué, caso por caso, está en los comentarios de los propios
archivos de configuración; aquí solo está la decisión.

---

## 3. Sin clave no se carga nada

Las tres piezas quedan **inactivas cuando falta su variable de entorno**:

- `SENTRY_LARAVEL_DSN` vacío → el SDK del servidor no envía nada.
- `VITE_SENTRY_DSN` vacío → el código de Sentry **no se descarga** en el navegador.
- `VITE_POSTHOG_KEY` vacío → el código de PostHog **no se descarga**.

Ese es el estado por omisión, el de desarrollo y el de cualquier entorno que no
las haya configurado a propósito. No es una comodidad: significa que el código
de terceros no se ejecuta en el navegador de nadie salvo decisión expresa, por
entorno. Las variables están en los dos `.env.example`, vacías, con esta misma
advertencia al lado.

**Y está medido, no prometido.** Se compiló el portal dos veces, con la única
diferencia de las variables de entorno:

| | Sin claves | Con claves |
|---|---|---|
| Fragmento de entrada | 191 413 B | 194 392 B (**+1,6 %**) |
| Fragmentos de Sentry y PostHog | **ninguno** | 439 kB + 332 kB, de carga diferida |
| `maskTextSelector`, `ph-no-capture`, `[dni]`, `eu.i.posthog` en los 132 fragmentos | **0 coincidencias** | presentes |

Sin claves no es que las herramientas no se activen: **su código no existe en
lo que se sirve al navegador.** Vite sustituye `import.meta.env.VITE_*` por su
valor al compilar, así que `if (!dsn) return` queda en `if (!undefined) return`
y Rollup se lleva como código muerto todo lo que hay detrás, el depurador
incluido. Con claves, los 771 kB de las dos herramientas quedan en
importaciones dinámicas que se piden después de montar y no bloquean el primer
render; lo único que crece en el arranque son los ~3 kB del depurador.

La medición, con el procedimiento para repetirla, está en
`docs/evidencias/observabilidad/O03-peso-del-bundle-segun-claves.txt`.

---

## 4. Criterios de anonimización

### 4.1 Patrones que se enmascaran en cualquier texto

Se aplican tanto en el servidor (`App\Support\Observabilidad\Depurador`) como
en el navegador (`src/observabilidad/depurador.ts`), con los mismos patrones,
porque el mismo dato puede salir por las dos puertas.

| Dato | Reemplazo | Por qué entra en la lista |
|---|---|---|
| Correo electrónico | `[correo]` | Identifica a la persona por sí solo. |
| 8 dígitos aislados | `[dni]` | Es el formato del DNI peruano. |
| 11 dígitos con prefijo 10/20 | `[ruc]` | RUC del emisor o del cliente. |
| `nn\|xxxxxxxx…` | `[token]` | Formato de los tokens de Sanctum. |
| `Bearer …` | `Bearer [token]` | Cabecera de autorización dentro de un mensaje. |
| `re_` / `sk_` / `pk_` / `ghp_` + clave | `[clave]` | Claves de API de proveedores. Un error de integración las arrastra al mensaje con facilidad. |
| 9 dígitos que empiezan por 9 | `[telefono]` | Móvil peruano. |

**El depurador es deliberadamente conservador en lo demás.** Un número de
pedido de tres cifras, una cantidad o un importe **no** se tocan: un depurador
demasiado agresivo deja los errores ilegibles, y entonces nadie los mira. Hay
una prueba que fija exactamente esto (`no destroza un mensaje sin datos
personales`).

### 4.2 Lo que se descarta entero, sin filtrar campo a campo

Filtrar un cuerpo de petición campo a campo es apostar a que nadie añadirá un
campo nuevo sin acordarse de la lista. Así que estas estructuras **se borran
completas**:

- el **cuerpo** de la petición y de la respuesta (`data`, `body`, `response`);
- las **cookies** y el **entorno** (`env`, donde viven las contraseñas de base de datos);
- la **cadena de consulta** cuando llega sin analizar, como texto;
- los **registros de la consola** en las migas de pan de Sentry, enteros: en
  desarrollo llevan volcados de objetos completos y nadie revisa qué se imprime
  pensando en que un día eso viajará a un tercero;
- el **título de la página** (`$title`). En la ficha de un producto el título
  *es* el nombre del medicamento, y no hay forma de distinguirlo de uno inocuo.

De las cabeceras se conserva solo una **lista blanca** (`Accept`,
`Content-Type`, `User-Agent`, y poco más). `Authorization` y `Cookie` no están
en ella.

### 4.3 URLs

Se conserva la **ruta**, porque sin ella no se sabe dónde falló algo, y se
quita todo lo demás. Las dos puertas lo hacen al revés a propósito:

- **En el servidor**, la cadena de consulta pasa por una **lista blanca**
  (`page`, `per_page`, `estado`, `categoria_id`, y poco más): lo que no está
  en ella desaparece, incluido un parámetro que nadie previó. De la URL se
  corta la cola entera.
- **En el navegador** la lista es negra —los parámetros de búsqueda (`q`,
  `buscar`, `search`, `termino`) y los que llevan credenciales o identidad
  (`token`, `codigo`, `enlace`, `correo`, `email`, `dni`)— porque el portal
  usa parámetros legítimos variados y una lista blanca dejaría los errores sin
  contexto. Para cerrar el hueco, **lo que quede de la consulta pasa
  igualmente por los patrones de 4.1**.

En los dos casos, además:
- el fragmento (`#…`);
- los identificadores numéricos se sustituyen: `/pedidos/217` → `/pedidos/:id`.
  Esto además **agrupa** los errores en lugar de dispersarlos en uno por pedido.

Una URL que ni así se puede analizar se reduce a `[url]`: es preferible perder
el dato a dejar pasar una cadena entera sin mirar.

### 4.4 Quién es la persona

En las dos herramientas, el usuario se reduce a **su identificador numérico**.
Sin correo, sin nombre, sin DNI y sin IP.

Con el `id` basta para responder la única pregunta que un panel de errores
necesita hacer sobre personas: *¿esto le pasa a una cuenta o a muchas?* Con el
correo, el panel de analítica se convierte en un listado de quién compra qué.
En PostHog, además, `$process_person_profile: false` evita que se construya un
perfil acumulado por persona.

### 4.5 Rutas que no se graban nunca, ni enmascaradas

| Ruta | Motivo |
|---|---|
| `/perfil` | Nombre, DNI, correo y foto de la persona. |
| `/cart` | Las líneas del carrito dicen qué medicamentos compra. |
| `/restablecer-contrasena` | La contraseña nueva está escrita en la pantalla. |
| `/verificar-correo` | Lleva el enlace firmado en la dirección. |
| `/auth/google` | Es el retorno del proveedor, con el código de intercambio. |

La comparación es por **prefijo**, no por igualdad: si mañana aparece
`/perfil/recetas`, queda cubierta sin tocar la lista. Al entrar en una de estas
rutas la grabación **se detiene**; al salir, se reanuda.

El caso de las **recetas médicas** merece decirse aparte: una receta es un
documento clínico con el nombre del paciente, el del médico y el diagnóstico
implícito en el medicamento. Grabar la pantalla donde se ve, aunque el texto
vaya tapado, significa grabar **la imagen del documento**. Por eso, además de
las rutas de arriba, el `blockSelector` excluye del vídeo los elementos
marcados con `[data-privado]`, `.ph-no-capture`, los `input[type="file"]` y
cualquier imagen cuyo texto alternativo mencione una receta.

### 4.6 Lo que no se controla desde aquí

Hay que decirlo porque lo contrario sería vender humo: **la dirección IP la ve
el servicio de todos modos**, a nivel de red, por el simple hecho de que el
navegador se conecta a él. Eso no se arregla con una opción del SDK; se
desactiva en la configuración del proyecto, en el panel del proveedor. Queda
anotado como paso pendiente de administración, no de código.

---

## 5. Dónde está cada cosa

| Archivo | Qué hace |
|---|---|
| `botica-san-juan-backend/config/sentry.php` | Configuración del servidor y `before_send`: última puerta antes del envío. |
| `botica-san-juan-backend/app/Support/Observabilidad/Depurador.php` | Patrones y listas blancas del servidor. |
| `botica-san-juan-backend/tests/Feature/DepuracionSentryTest.php` | 14 pruebas que fijan el contrato en el servidor. |
| `botica-san-juan-frontend/src/observabilidad/depurador.ts` | Patrones, rutas prohibidas y depuración de URL y propiedades. |
| `botica-san-juan-frontend/src/observabilidad/index.ts` | Arranque de Sentry y PostHog, con todo lo apagado de la sección 2. |
| `botica-san-juan-frontend/src/observabilidad/__tests__/depurador.spec.ts` | 26 pruebas que fijan el contrato en el cliente. |

La observabilidad se inicia en `src/main.ts` **después** del router —PostHog se
engancha a `afterEach` para mandar la vista con la URL ya depurada— y **antes**
de montar, para que un error del primer render tenga a Sentry escuchando. No se
espera a que termine: son dos importaciones contra servidores de terceros, y la
aplicación no puede quedarse en blanco porque uno de ellos tarde.

---

## 6. Las pruebas son el contrato

Las 40 pruebas de los dos archivos no comprueban que Sentry o PostHog
funcionen —eso es asunto de ellos—, sino que **lo que se les entrega va
depurado**.

Están escritas sobre casos reales de este sistema: un DNI dentro del mensaje de
una excepción, un token de Sanctum en una cabecera, «amoxicilina» en la cadena
de consulta, la ruta de una receta. Una prueba con «foo» y «bar» no habría
encontrado ninguno de los cuatro.

**Si alguien amplía la analítica y una de estas pruebas se pone roja, la
pregunta correcta no es cómo arreglar la prueba: es si ese dato debía salir.**
