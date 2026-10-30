# Interoperabilidad: la API como conjunto de servicios

> Estado: vigente desde el Sprint 6 · Repositorio: `botica-san-juan-backend`

## 1. Qué se quiso resolver

Hasta ahora la API existía pero no estaba **descrita**. Quien quisiera
consumirla desde fuera —otro sistema, un evaluador, un compañero de
equipo— tenía que leer `routes/api.php` y los controladores para averiguar
qué rutas hay, qué espera cada una y cuáles exigen token.

Eso es exactamente lo contrario de lo que plantea una arquitectura orientada
a servicios: un servicio que no publica su contrato no es un servicio, es una
caja cerrada con la que hay que negociar caso por caso.

Se añadieron tres cosas, en este orden de importancia:

1. Un **documento OpenAPI 3.1** generado del código, no escrito a mano.
2. Dos **interfaces de lectura y prueba** sobre ese mismo documento.
3. Un **carril público de CORS** para las rutas que un cliente de navegador
   necesita alcanzar.

---

## 2. El documento OpenAPI

Lo genera [Scramble](https://scramble.dedoc.co) leyendo el código: rutas,
reglas de validación, tipos de retorno y comentarios. **No hay un archivo de
especificación que alguien mantenga a mano**, y esa es la decisión de fondo:
una especificación escrita aparte se desincroniza del código en la primera
semana y a partir de ahí miente con autoridad.

| Dato | Valor |
|---|---|
| Versión OpenAPI | 3.1.0 |
| Rutas documentadas | 80 |
| Operaciones | 120 |
| Dominios (etiquetas) | 23 |
| Operaciones que exigen token | 100 |
| Operaciones públicas | 20 |

### Dónde se consulta

| Ruta | Qué es |
|---|---|
| `/docs/api` | Interfaz Stoplight Elements (la que trae Scramble por omisión) |
| `/docs/v1` | Interfaz **Scalar**, con modo de prueba y ejemplos de cliente |
| `/docs/api.json` | El documento, servido por Scramble |
| `/docs/openapi.json` | El mismo documento, servido como descarga |

Las dos interfaces son **dos lecturas del mismo documento**, no dos
documentaciones. Si alguna vez mostraran cosas distintas, el problema estaría
en el documento y no en los visores. `/docs/v1` se registra como una API
adicional de Scramble clonada de la configuración por omisión, en lugar de
con una plantilla propia: así la plantilla, la versión del visor y el manejo
del CSRF los mantiene el paquete y no este proyecto.

En producción las cuatro rutas están detrás de `RestrictedDocsAccess`, el
middleware de Scramble que solo las abre en entorno local.

### La seguridad también está descrita

Se activó `MiddlewareAuthSecurityStrategy`: el documento declara el esquema
`bearer` globalmente y marca con `security: []` las operaciones que no pasan
por `auth`. Sin eso, un consumidor externo no puede distinguir en el
documento qué necesita token y qué no, y lo averigua a base de 401.

La lista de las 20 públicas es revisable de un vistazo en
`docs/mapa-servicios.json`, y conviene revisarla: es la superficie expuesta
del sistema.

### Exportación

```bash
php artisan scramble:export      # -> docs/openapi.json   (~600 KB)
php artisan soa:mapa             # -> docs/mapa-servicios.json (~27 KB)
```

Ambos archivos están versionados y **se regeneran, no se editan**. Si el
grafo muestra un servicio que ya no existe, es que nadie volvió a ejecutar
los comandos.

---

## 3. Por qué hay dos archivos

`openapi.json` lleva el esquema de cada cuerpo, de cada respuesta y de cada
modelo. Eso es justo lo que necesita un cliente que va a consumir la API, y
justo lo que sobra cuando lo que se quiere es **mirar** la arquitectura: en
un visor de grafos como [jsoncrack.com](https://jsoncrack.com) los nodos de
esquema entierran a los de servicio y no se distingue nada. Medio megabyte
de JSON tampoco ayuda al visor.

`mapa-servicios.json` destila el mismo documento a su estructura: qué
dominios hay, qué operaciones tiene cada uno, por qué verbo y ruta se llega,
y cuáles exigen token. Veintisiete kilobytes, que es lo que un grafo
interactivo puede dibujar sin convertirse en una maraña.

```
docs/mapa-servicios.json
├── sistema, version, generado
├── resumen        → dominios, operaciones, con token, públicas
└── dominios
    ├── Pos        → [ { operacion, metodo, ruta, acceso }, … ]
    ├── Pedido     → …
    └── …
```

Para la presentación: arrastrar `mapa-servicios.json` a jsoncrack.com.

---

## 4. CORS: el carril público

`config/cors.php` declara ahora una lista de `rutas_publicas` —salud,
catálogo de caja, sincronización de ventas, `api/v1/*` y la documentación— a
las que `HandleCors` responde con `Access-Control-Allow-Origin: *` y **sin
credenciales**. El resto conserva el origen resuelto y las credenciales.

Dos cosas que conviene no confundir:

**`*` y las credenciales son excluyentes.** No es una elección de estilo: el
navegador rechaza la combinación. Por eso el carril público va sin
credenciales, y por eso las rutas que dependen de cookies de sesión no pueden
estar en él.

**CORS no autoriza nada.** Es una regla que aplica el navegador sobre qué
página puede leer la respuesta; no sustituye a la autenticación ni la
relaja. `GET /api/pos/catalogo` está en la lista pública de CORS **y sigue
exigiendo token de administrador**: lo que se abrió es el carril del
navegador, no la puerta.

---

## 5. Un efecto lateral que valió la pena

Generar el documento sacó a la luz trece reglas de validación que el
generador no podía leer. Todas eran la misma forma:

```php
$registro = Modelo::findOrFail($id);
$request->validate([
    'codigo' => 'unique:tabla,codigo,' . $registro->id,
]);
```

Construir la regla concatenando el id de un modelo leído antes obliga a
consultar la base **solo para armar una cadena**, y deja la regla invisible
para cualquiera que lea el código sin ejecutarlo —incluido el generador, que
la omitía del documento publicado—. Se reemplazó por:

```php
$request->validate([
    'codigo' => ['required', 'string', Rule::unique('tabla', 'codigo')->ignore($id)],
]);
```

y la consulta se movió detrás de la validación, que es donde hace falta. Tres
controladores, trece avisos a uno.

El aviso que queda es `POST /api/pedidos/confirmar`: sus reglas dependen de
si quien llama trae token o no, y eso se decide en ejecución. El endpoint
está documentado; lo que no se documenta es qué campos pasan a ser
obligatorios para un invitado. Es una limitación real del análisis estático,
no un descuido.

---

## 6. Límites de lo que hay

- **El documento se genera en el entorno local**, así que el `openapi.json`
  versionado incluye `GET /api/test`, una ruta de depuración que solo existe
  en local. No está en producción.
- **Los `summary` de muchas operaciones son los que deduce el generador**
  («Display a listing of the resource»). Son correctos pero genéricos;
  mejorarlos es cuestión de escribir el comentario de cada método.
- **No hay versionado del contrato.** Hoy el prefijo `v1` solo lo llevan las
  rutas de sincronización. Si la API llega a tener consumidores externos
  reales, habrá que decidir una política de versiones antes y no después.
