# Plan — Duplicados del catálogo y refactor del catálogo público

## Spec (autoridad vinculante)

El portal debe mostrar el **stock real vendible**, el mismo que vería una venta
de mostrador, y el catálogo no debe presentar como productos distintos filas
que son la misma mercancía. El portal público debe permitir navegar al resto
de páginas públicas.

## Contexto medido (no suposiciones)

- 3 361 productos, 0 códigos de barras repetidos.
- "PARACETAMOL" aparece 64 veces, pero son productos DISTINTOS: distinta
  concentración, presentación y laboratorio. No se tocan.
- **1 071 grupos idénticos en todos los campos de negocio** (nombre,
  concentración, presentación, laboratorio, tipo, adicional, precio,
  código DIGEMID), cubriendo **3 314 filas**. El catálogo se importó varias
  veces.
- Ejemplo: CLOBETASOL 0.05 % Tubo 25 g FARMINDUSTRIA → ids 307/1081 idénticos
  (CREMA, 12.00) y 1951/2821 idénticos (CREMA, 12.50). CREMA vs UNGÜENTO sí es
  una distinción real.
- Las 165 filas duplicadas de una muestra TIENEN LOTES; 1 tiene ventas.
  El stock está repartido entre copias: el anaquel dice 6 y el sistema implica 24.
- Tablas que apuntan a productos.id: lotes (3 363), pedido_detalles (23),
  movimientos_stock (12), conteo_detalles (5), incidencias_venta (2), carrito (0).

## Global Constraints

- NADA se borra sin que el comando haya corrido antes en modo simulación y el
  dueño del proyecto lo autorice. Por omisión el comando SIMULA.
- La fusión va en UNA transacción; si algo falla, revierte entera.
- El superviviente de cada grupo es el de id MENOR (el primero importado), pero
  conserva el PRECIO del id mayor (la importación más reciente trae el precio
  vigente). Esta decisión se imprime en el informe para que se pueda discutir.
- El stock NO se suma ciegamente: los lotes se reapuntan al superviviente y el
  stock del producto se recalcula desde los lotes, que es la fuente de verdad.
- Identidad visual azul y tokens del design system. Sin Three.js ni WebGL.
- El portal muestra `stock_disponible` (excluye lotes vencidos), nunca `stock`.

## Tarea 1 — Comando de fusión de duplicados (backend)

Crear `php artisan catalogo:fusionar-duplicados`:

- Por omisión SIMULA (`--aplicar` ejecuta de verdad).
- Agrupa por: nombre, concentracion, presentacion, laboratorio, tipo,
  adicional, codigo_digemid. **NO** agrupa por precio: dos filas iguales con
  precio distinto son la misma mercancía reimportada, y fusionarlas es el
  objetivo. El precio del superviviente es el del id mayor del grupo.
- Para cada grupo: elige superviviente (id menor), reapunta `producto_id` en
  lotes, pedido_detalles, movimientos_stock, conteo_detalles e
  incidencias_venta, y borra los perdedores.
- Recalcula `productos.stock` del superviviente sumando `lotes.cantidad_actual`.
- Imprime: grupos encontrados, filas a eliminar, filas de cada tabla
  reapuntadas, y los 10 primeros grupos con detalle.
- Todo dentro de una transacción.

Prueba: un test de Feature que cree dos productos idénticos con lotes, ejecute
la fusión y compruebe que queda uno, con los lotes de ambos y el stock sumado.

## Tarea 2 — Restricción para que no vuelva a ocurrir

Migración que añade un índice UNIQUE sobre
(nombre, concentracion, presentacion, laboratorio, tipo, adicional).
Debe aplicarse DESPUÉS de la fusión; la migración falla si quedan duplicados,
y ese fallo es deseable: avisa en vez de permitirlos.

## Tarea 3 — Refactor del catálogo público (frontend)

- Volver a incluir Header y Footer: la reescritura anterior los perdió y la
  página quedó sin forma de navegar al resto del portal. Es una regresión.
- La tarjeta debe mostrar lo que DISTINGUE a un producto de otro con el mismo
  nombre: concentración y presentación. Sin ellas, dos productos legítimamente
  distintos parecen duplicados.
- Mostrar `stock_disponible` (stock vendible, sin lotes vencidos), no `stock`.
  Si el servidor no lo envía en el listado público, pedirlo.
- `content-visibility: auto` + `contain-intrinsic-size` en las tarjetas que
  quedan bajo el pliegue (guía modern-web-guidance: defer-rendering-heavy-content).
  NO aplicarlo a las de la primera pantalla.
- Mantener lo que ya funciona: paginación y filtros en servidor, búsqueda con
  espera, estados de carga/error/vacío, responsive y contraste en ambos temas.
