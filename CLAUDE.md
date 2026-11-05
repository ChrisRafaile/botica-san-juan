# Botica San Juan · memoria del proyecto

Laravel 12 + Vue 3 + PostgreSQL 16. UTP, Curso Integrador II.

Este archivo es lo que un agente debe saber **antes** de tocar el código, para
no volver a descubrir por tercera vez lo mismo. No es documentación del
sistema: eso está en `docs/`. Es la lista de decisiones y trampas que ya
costaron tiempo.

---

## Reglas que no se negocian

- **El RUC del emisor y el nombre del contribuyente del sistema anterior no se
  escriben en ningún archivo versionado, informe, captura ni respuesta.** Solo
  en el `.env` local. Si hace falta un ejemplo, es ficticio.
- **El APF3 y los documentos del informe (`.docx`) se dejan intactos** salvo
  petición explícita. Los entregables nuevos son archivos nuevos.
- **Nunca se imprime el valor de una credencial** en una respuesta ni se
  escribe en un archivo versionado. Los `.env.example` llevan las claves
  vacías, con el comentario de por qué.
- **Las operaciones destructivas de base de datos** necesitan autorización
  explícita y respaldo previo. La base de producción tiene **solo el
  catálogo**: no hay usuarios ni pedidos reales.
- **El login no enumera usuarios.** Credenciales malas → respuesta genérica,
  igual para «no existe» y para «contraseña incorrecta». Lo mismo en la
  recuperación de contraseña: los dos casos salen por la misma puerta.
- **Sin claves, la observabilidad no se carga.** Ver `docs/OBSERVABILIDAD.md`.

## Estándares del Sprint 5

Identidad visual azul y tokens del design system; cero valores de color o
espaciado a mano. Sin Three.js, WebGL ni shaders. Animación con
`<Transition>` de Vue o GSAP, solo propiedades compuestas por GPU, respetando
`prefers-reduced-motion`. CLS ≤ 0,015. Limpieza estricta de temporizadores y
escuchadores. WCAG AA. Sin inflar el paquete.

---

## Trampas ya pagadas

Cada una de estas costó una sesión de diagnóstico. Están aquí para que no
cueste una segunda.

### Backend

- **`Model::create()` descarta en silencio lo que no está en `$fillable`.** Así
  nacieron sin verificar las cuentas creadas con Google: `email_verified_at`
  no estaba en la lista y el atributo desaparecía sin error. Para campos de
  control, `forceFill`.
- **La columna `password` nunca es nula, ni en las cuentas de Google.** Esas
  llevan un hash aleatorio de 64 caracteres que nadie ha visto, así que
  `password !== null` no responde «¿hay una contraseña que esta persona
  conozca?». Para eso está `contrasena_establecida_en`, que se escribe SOLO
  desde `Usuario::establecerContrasena()`. Si añades un sitio que escriba
  `password`, pásalo por ahí o una cuenta quedará sin poder desvincular Google.
- **Las ventas descuentan por FEFO sobre `lotes`, no sobre `productos.stock`.**
  Una prueba que crea un producto con stock y espera poder venderlo falla con
  «no hay stock». Hay que crear un `Lote`.
- **La tabla `usuarios` no tiene `remember_token`.** Tocarlo da SQLSTATE 42703.
  Lo que hay que invalidar al cambiar la contraseña son los tokens de Sanctum.
- **`lang/es.json` solo cubre las cadenas con clave JSON.** Los mensajes
  `validation.*` viven en `lang/es/validation.php`, que es un archivo de
  array. Poner `APP_LOCALE=es` sin ese archivo hace que todos los formularios
  muestren literalmente `validation.required`. `APP_FALLBACK_LOCALE=en` como
  red de seguridad.
- **El middleware `signed` devuelve la página de error de Laravel.** Para poder
  redirigir al portal con un resultado, se valida en el controlador con
  `$request->hasValidSignature()`.
- **Los documentos clínicos van en `storage/app/private`** y se sirven por un
  endpoint autenticado con `Cache-Control: no-store, private`, nunca por disco
  público.

### Frontend

- **Una relación de Eloquent se serializa en `snake_case`.** `pedidoDetalles()`
  llega al JSON como `pedido_detalles`. Leer el nombre del método en el cliente
  da «0 producto(s)» en todas las filas sin ningún error.
- **`min-h-0` es obligatorio en el hijo flex que debe hacer scroll.** Su
  `min-height: auto` por omisión le impide encogerse. Esto fue el
  desbordamiento del modal «Editar Producto»: un `calc(90vh-140px)` a mano
  adivinando una altura de cabecera y pie que en realidad eran ~190px, dentro
  de un contenedor con `overflow-hidden`.
- **Node 26 define su propio `localStorage` global**, `undefined` sin
  `--localstorage-file`, y tapa el de jsdom. `vitest.setup.ts` devuelve una
  implementación en memoria.
- **pnpm 10 rompe `pnpm add` si `pnpm-workspace.yaml` no tiene `packages:`.**
  `allowBuilds` **sí** es una clave válida: pnpm reescribe el archivo él mismo.
- **`<img src>` no lleva cabecera `Authorization`.** Para una imagen protegida:
  descargar como blob y crear una object URL.
- **El panel del navegador integrado ejecuta el JavaScript en un mundo
  aislado.** Un `DataTransfer`/`File` creado ahí no rellena el `input.files`
  del mundo principal: las pruebas de arrastrar y soltar no se pueden simular
  así.
- **`sendDefaultPii` no existe en el SDK de navegador de Sentry**, solo en los
  de servidor. Pasarlo da TS2353.

---

## Cómo se verifica

Son los comandos reales, con las rutas que funcionan en esta máquina. `pnpm`
por corepack está roto aquí, así que se invocan los binarios directamente.

```powershell
# Backend (379 pruebas, 1068 aserciones)
cd botica-san-juan-backend
php artisan test

# Frontend (116 pruebas)
cd botica-san-juan-frontend
.\node_modules\.bin\vitest.cmd run

# Tipos y compilación
.\node_modules\.bin\vue-tsc.cmd --build --force
.\node_modules\.bin\vite.cmd build
```

**Nada se declara terminado sin la salida de estos comandos delante.** Una
corrección de interfaz, además, se comprueba midiendo en un navegador de
verdad: el modal se cerró cuando se verificó que el contenido de 1231 px
cabía en un área desplazable de 453 px y que el botón de enviar quedaba a
y=659 de 720, no cuando el CSS pareció razonable.

---

## Convenciones

- **El código y los comentarios están en español**, incluidos los nombres de
  clases, métodos y archivos (`RecetaController`, `Depurador::texto`,
  `ZonaDeReceta.vue`). Mantenerlo.
- Los comentarios explican **por qué**, no qué. Si un comentario describe lo
  que la línea siguiente ya dice, sobra.
- Las pruebas se escriben sobre **casos reales del dominio** —«amoxicilina»,
  un DNI de ocho cifras, un token de Sanctum—, no sobre `foo` y `bar`. Las de
  privacidad del monitoreo encontraron tres fugas que un `foo` no habría
  encontrado.
- Las evidencias van en `docs/evidencias/<tema>/`, numeradas, con el
  procedimiento para reproducirlas dentro del propio archivo.

---

## Dónde está documentado qué

| Documento | Tema |
|---|---|
| `docs/OBSERVABILIDAD.md` | Sentry, PostHog y criterios de anonimización. |
| `docs/GOOGLE-OAUTH.md` | Flujo OAuth y URIs exactas de Google Cloud Console. |
| `docs/RECETAS-MEDICAS.md` | Subida y validación de recetas. |
| `docs/CORREOS-TRANSACCIONALES.md` | Verificación de correo y recuperación de contraseña. |
| `docs/INFORME-APF2.md` | Entregable académico del APF2. |
| `docs/evidencias/figura11/LEEME.md` | Figura 11 corregida para el informe final, y por qué el APF3 no se tocó. |

El APF3 ya se entregó y se calificó: es un registro histórico y no se modifica.
Lo que lo corrige se entrega como archivo aparte en `docs/evidencias/`.
