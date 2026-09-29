# Cómo se trabaja en este repositorio

Sistema Web de Gestión y Control · Botica San Juan

Este documento recoge las convenciones que ya se venían usando, para que estén
escritas en algún sitio y no sólo en la cabeza de quien las aplica.

---

## Ramas

Se sigue GitFlow, adaptado a un proyecto con un solo desarrollador:

| Rama | Para qué | Nace de | Vuelve a |
|---|---|---|---|
| `main` | Lo que está en producción | — | — |
| `develop` | Integración de lo terminado | `main` | `main` |
| `feature/hu-XX-descripcion` | Una historia de usuario | `develop` | `develop` |
| `fix/descripcion` | Corrección de un fallo | `develop` | `develop` |
| `hotfix/descripcion` | Urgencia en producción | `main` | `main` y `develop` |

El nombre de la rama lleva el código de la historia cuando existe:
`feature/hu-06-punto-de-venta`. Así, al ver el historial meses después, se sabe
de dónde salió cada cosa sin abrir el gestor de tareas.

## Mensajes de commit

Se usa [Conventional Commits](https://www.conventionalcommits.org/es/), en
español y en presente:

```
tipo(ámbito): qué hace el cambio

Por qué era necesario, y qué alternativa se descartó si la hubo. Esta parte
importa más que la lista de archivos tocados: el diff ya dice qué cambió,
pero no por qué.
```

Tipos: `feat`, `fix`, `refactor`, `perf`, `docs`, `test`, `build`, `ci`, `chore`.

Ámbitos habituales: `pos`, `inventario`, `conteo`, `igv`, `admin`, `auth`,
`sunat`, `infra`.

Un ejemplo del propio repositorio:

```
fix(pos): rechazar la venta cuando no hay nada que entregar

Confirmar una entrega parcial de un producto agotado registraba un pedido
fantasma: total 0, sin lineas y con una sola incidencia. Ahora se lanza
VentaSinStockException, que revierte la transaccion completa y devuelve 422.
```

## Antes de abrir un Pull Request

```bash
# Frontend
cd botica-san-juan-frontend
pnpm install --frozen-lockfile
pnpm exec vue-tsc --noEmit -p tsconfig.app.json
pnpm build

# Backend: las reglas de negocio se prueban contra la base real y revierten
cd ../botica-san-juan-backend
php artisan pos:probar
php artisan conteo:probar
php artisan igv:probar
php artisan pos:probar-presentaciones
```

## Reglas que el código no puede romper

Están escritas aquí porque son del negocio, no del framework, y sobreviven a
cualquier refactorización:

1. **El stock nunca queda negativo.** Si no alcanza, se entrega lo que hay y se
   registra la incidencia.
2. **Todo cambio de inventario deja movimiento con motivo.** Un ajuste sin
   explicación es indistinguible de un descuadre.
3. **El IGV se extrae del precio, nunca se suma.** Los precios de la base ya son
   el importe final que paga el cliente.
4. **La afectación tributaria es del producto, no de la presentación.** Unidad,
   blíster y caja tributan igual.
5. **Lo que toca dinero o stock va dentro de una transacción.** Una venta a
   medias es peor que una venta fallida.
6. **Una boleta emitida no se recalcula.** El tratamiento tributario se copia a
   la línea al vender y se queda ahí.

## Qué nunca entra al repositorio

- Archivos `.env` con credenciales reales
- Volcados de base de datos (`*.dump`, `*.sql`) — contienen datos de clientes y
  de ventas de la botica
- El RUC o la razón social del contribuyente en capturas compartidas
- Claves, certificados y tokens de SUNAT

## Accesibilidad e interfaz

- Texto normal: contraste mínimo 4.5:1; texto grande: 3:1
- El contraste se mide, no se estima. La forma fiable es pintar el color en un
  canvas de 1×1 y leer el sRGB: parsear `oklch()` a ojo da resultados falsos
- Todo lo que se opera con ratón debe poder operarse con teclado
- Sin colores fijos de Tailwind: se usan los tokens de `design-system.css`, o el
  modo oscuro se rompe
- Las animaciones respetan `prefers-reduced-motion`
