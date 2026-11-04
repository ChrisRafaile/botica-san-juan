# Estabilidad visual del panel de indicadores

Fecha de la medición: 31/10/2026 · Vista `/admin/indicadores` · Viewport 1440×900

## Lo que NO se pudo medir, y por qué

El objetivo es CLS ≤ 0,015. La medición directa con la API `layout-shift` **no
es válida en el entorno desde el que se verificó**: el panel del navegador
corre la página con `document.visibilityState === 'hidden'`, y Chrome no emite
entradas `layout-shift` en páginas ocultas.

Esto se comprobó en lugar de suponerse. Con el observador instalado se insertó
un bloque de 280 px al principio del contenido —un empujón que tiene que
registrar un desplazamiento grande— y el contador siguió en cero. Un cero que
no sube ante un salto provocado no mide nada, así que **no se reporta ese cero
como cumplimiento del umbral**.

## Lo que sí se midió

El CLS de un tablero nace de una causa concreta: las tarjetas crecen cuando
llegan los datos y empujan todo lo que tienen debajo. Esa causa se puede medir
de forma determinista comparando la geometría de cada sección durante el
esqueleto de carga y después de la respuesta.

| Tarjeta | Top esqueleto | Top con datos | Alto esqueleto | Alto con datos | Se movió |
|---|---|---|---|---|---|
| Vencimiento de lotes | 222 px | 222 px | 416 px | 416 px | No |
| Arqueo por medio de pago | 662 px | 662 px | 288 px | 288 px | No |
| Más entregados | 662 px | 662 px | 288 px | 288 px | No |

Ninguna de las tres cambia de posición ni de alto. Si el hueco no cambia, nada
de lo que hay debajo se desplaza, y no hay desplazamiento que acumular.

## Cómo se consigue

- Cada `<section>` declara un `min-h` **idéntico en los tres estados**:
  esqueleto, vacío y con gráfico. El contenido se sustituye dentro de un hueco
  que ya existe.
- Los SVG llevan `viewBox`, de modo que su relación de aspecto está fijada
  antes de que lleguen los datos. Una librería de gráficos que mide el
  contenedor al montarse provoca justamente el salto que aquí se evita; es una
  de las razones por las que los tres gráficos se dibujaron a mano.
- Las animaciones de entrada usan `transform` (`scaleX`, `rotate`), que la GPU
  compone sin recalcular maquetación. Animar `width` haría reflow en cada
  fotograma de las diez barras a la vez.
- `prefers-reduced-motion` **amortigua** a 80 ms en lugar de anular: una
  animación cortada a 0,01 ms salta más de lo que acompaña.

## Pendiente

Queda medir el CLS real en un navegador con la pestaña visible, idealmente con
Lighthouse sobre la compilación de producción. La medición de arriba acota la
causa principal, pero no sustituye al número.

## Reproducir

1. Abrir `/admin/indicadores` con la pestaña **visible**.
2. En la consola, instalar el observador:

```js
let cls = 0
new PerformanceObserver((l) => {
  for (const e of l.getEntries()) if (!e.hadRecentInput) cls += e.value
}).observe({ type: 'layout-shift', buffered: true })
```

3. Pulsar «Actualizar» y leer `cls`.
4. Control obligatorio: insertar un bloque alto al principio del contenido y
   confirmar que el contador sube. Si no sube, la medición no vale.
