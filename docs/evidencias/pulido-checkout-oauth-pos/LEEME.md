# Pulido: OAuth, checkout y POS visual

Medido en Chrome real (playwright-core) contra el stack local, a 1280 y 360 px.

| Punto | Resultado |
|---|---|
| `/perfil` tras Google OAuth | Redirige a `/client/perfil`, sin 404 (`perfil-redireccion.png`) |
| «Sin conexión» en `/cart` | Causa: el CORS no permitía la cabecera `Idempotency-Key`. Preflight local ahora lista la cabecera. Sin credenciales de Izipay el cobro responde 409 con mensaje claro |
| Miniaturas en `/admin/products` | 6 de 6 fotos reales cargadas en la búsqueda de un producto con foto (`productos-*.png`) |
| Cuadrícula del POS | 48 tarjetas, filtros por categoría (solo las que tienen productos), un clic agrega al carrito, sin desborde horizontal a 360 ni 1280 (`pos-*.png`) |

Reproducir: `IZIPAY_MODE=api` sin `IZIPAY_USERNAME`/`IZIPAY_PASSWORD`, servir backend en :8083 y frontend en :5173.
Nota: en local casi ningún producto tiene foto real; las que faltan caen al icono de respaldo.
