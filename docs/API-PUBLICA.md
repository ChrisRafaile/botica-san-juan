# API pública · Botica San Juan

Catálogo de medicamentos de solo lectura, **sin token**, pensado para que otros
proyectos lo consuman. En la línea de [PokeAPI](https://pokeapi.co/) y la
[Dragon Ball API](https://web.dragonball-api.com/).

```
https://botica-san-juan-api.onrender.com
```

> **Despierta despacio.** El servicio vive en la capa gratuita de Render: se
> duerme tras quince minutos sin tráfico y la primera petición tarda unos
> cincuenta segundos. No es una caída; es el precio de que el enlace siga vivo
> dentro de tres semanas. Si vas a hacer una demostración en vivo, lanza una
> petición un minuto antes.

---

## Prueba en diez segundos

```bash
curl https://botica-san-juan-api.onrender.com/api/catalogo/destacados
```

```js
const r = await fetch('https://botica-san-juan-api.onrender.com/api/catalogo/destacados')
const { datos } = await r.json()
console.table(datos)
```

Sin cabeceras, sin clave, sin registro. Desde cualquier dominio: estas rutas
responden `Access-Control-Allow-Origin: *`, así que el `fetch` funciona desde
tu propio frontend sin que el navegador lo bloquee.

---

## Documentación interactiva

| | |
|---|---|
| **Scalar** | `/docs/v1` — el visor más cómodo para explorar |
| **Stoplight Elements** | `/docs/api` |
| **Esquema OpenAPI 3.1** | `/docs/openapi.json` — para importar en Postman, Insomnia o un generador de clientes |

Las tres leen el **mismo** documento, generado desde el código: si una ruta
cambia, la documentación cambia con ella. No hay una copia escrita a mano que
se pueda quedar vieja.

---

## Endpoints

### `GET /api/catalogo`

Listado paginado.

| Parámetro | Tipo | Qué hace |
|---|---|---|
| `buscar` | texto | Busca en nombre, principio activo y laboratorio |
| `categoria` | texto | Slug (`analgesicos`) o id numérico |
| `requiere_receta` | booleano | Filtra por venta bajo receta |
| `per_page` | entero 1–100 | Elementos por página (20 por omisión) |
| `page` | entero | Página |

```bash
curl "https://botica-san-juan-api.onrender.com/api/catalogo?buscar=paracetamol&per_page=5"
```

```json
{
  "datos": [
    {
      "id": 21,
      "nombre": "PARACETAMOL",
      "concentracion": "500 mg",
      "presentacion": "Tableta",
      "laboratorio": "Portugal",
      "principio_activo": "paracetamol",
      "categoria": "Analgesicos y Antiinflamatorios",
      "categoria_slug": "analgesicos-antiinflamatorios",
      "precio": 0.5,
      "moneda": "PEN",
      "requiere_receta": false,
      "disponible": true,
      "imagen": "images/default_image.png"
    }
  ],
  "paginacion": { "pagina": 1, "por_pagina": 5, "total": 1, "paginas": 1 }
}
```

### `GET /api/catalogo/{id}`

Un producto. `404` si no existe.

### `GET /api/catalogo/destacados`

Una muestra corta. Acepta `limite` (1–50, por omisión 12).

El criterio **viene declarado en la respuesta**, en el campo `criterio`: no hay
una columna «destacado» ni nadie los marca a mano, así que la regla es
explícita y comprobable — productos con existencias y de venta sin receta,
ordenados por nombre.

### `GET /api/salud`

Comprobación de vida. Útil para despertar el servicio antes de una demostración.

```json
{ "ok": true, "base": true, "hora": "2026-11-04T04:19:57+00:00" }
```

---

## Ejemplo completo de consumo

```html
<!doctype html>
<meta charset="utf-8">
<title>Catálogo Botica San Juan</title>
<ul id="lista"></ul>

<script type="module">
const API = 'https://botica-san-juan-api.onrender.com/api'

async function cargar(termino = '') {
  const url = new URL(API + '/catalogo')
  if (termino) url.searchParams.set('buscar', termino)
  url.searchParams.set('per_page', '12')

  const respuesta = await fetch(url)

  // 429 es el límite por minuto, no un fallo tuyo: espera y reintenta.
  if (respuesta.status === 429) throw new Error('Demasiadas peticiones, espera un momento')
  if (!respuesta.ok) throw new Error('La API respondió ' + respuesta.status)

  const { datos, paginacion } = await respuesta.json()
  console.log(`${datos.length} de ${paginacion.total}`)
  return datos
}

const lista = document.getElementById('lista')
for (const p of await cargar('amoxicilina')) {
  const li = document.createElement('li')
  // Texto, no innerHTML: el nombre viene de una API y se trata como dato.
  li.textContent = `${p.nombre} ${p.concentracion} — S/ ${p.precio}`
  lista.append(li)
}
</script>
```

---

## Límites y reglas

**300 peticiones por minuto y por IP.** Al pasarte recibes `429` con la
cabecera `Retry-After`. El tope existe porque la instancia es gratuita y
compartida: un bucle mal escrito en un proyecto la tumba para todos.

**Los precios están en soles (`PEN`)** y son los del mostrador. El `precio` es
el de la unidad mínima de venta.

**`disponible` dice si hay existencias, no cuántas.** Es deliberado: la
cantidad exacta de cada medicamento es información comercial y, mirada en el
tiempo, dice qué se está vendiendo en el barrio.

**El contrato no cambia sin avisar.** Los campos de la respuesta están fijados
uno a uno en `CatalogoPublicoTest`: añadir o quitar uno rompe la suite, así
que tiene que ser una decisión y no un descuido. `/api/catalogo` es estable;
`/api/productos` es el detalle interno del panel y **puede** cambiar de forma.
Si consumes desde fuera, usa `/api/catalogo`.

---

## Errores

| Código | Qué pasó |
|---|---|
| `404` | El producto no existe, o la ruta no es la que crees |
| `422` | Un parámetro no es válido — por ejemplo `per_page=5000` |
| `429` | Te pasaste del límite por minuto; mira `Retry-After` |
| `503` | El servicio está despertando; reintenta en unos segundos |

Los errores llegan como JSON con una clave `message`.

---

## Para el curso de SOA

Lo que esta API demuestra, y dónde mirarlo:

| Concepto | Dónde |
|---|---|
| Contrato formal y versionado | `/docs/openapi.json`, OpenAPI 3.1 generado desde el código |
| Descubrimiento | `/docs/v1` y `/docs/api`, dos visores del mismo documento |
| Bajo acoplamiento | La fachada pública no expone el modelo interno |
| Interoperabilidad | JSON sobre HTTP, CORS abierto, sin SDK propio |
| Calidad de servicio | Límite por IP, comprobación de vida, códigos de error documentados |
| Seguridad por capas | El carril público es de solo lectura; todo lo demás sigue exigiendo token |

El esquema se puede arrastrar a [jsoncrack.com](https://jsoncrack.com) para ver
el grafo de servicios: `/docs/openapi.json` se sirve como adjunto y con nombre
fijo justamente para eso.

---

## Lo que NO hay aquí

Ni datos de personas, ni pedidos, ni recetas, ni existencias exactas, ni
precios de compra. No porque estén protegidos detrás de esta API, sino porque
**no pasan por ella**: la fachada construye su respuesta campo a campo y lo que
no está en esa lista no sale. Ver `CatalogoPublicoController::comoContrato`.

Los criterios de privacidad del sistema completo están en
`docs/OBSERVABILIDAD.md`.
