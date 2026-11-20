# Auditoría en navegador · Asistente con importador Excel/CSV y OpenAPI 0.8.0

Fecha: 2026-11-20 · Chrome real vía `playwright-core` · 1280 px y 360 px ·
backend en `:8083` y frontend en `:5173` · cuentas demo del seeder (DNI 10000001
administrador, 10000002 cliente; las contraseñas viven solo en el `.env` local).

## Resultados

| Comprobación | Resultado |
|---|---|
| Scalar `/docs/v1` muestra 0.8.0 | Sí |
| Stoplight `/docs/api` muestra 0.8.0 | Sí |
| Aparece «0.5.0», «VR002» o endpoints `_N` en alguna de las dos | No |
| `scramble:export` | Sin errores ni warnings |
| Administrador ve «Adjuntar archivo Excel o CSV» (1280 y 360 px) | Sí |
| Cliente ve el botón del Asistente pero NO el de adjuntar | Correcto |
| Subir `ejemplo-reposicion.csv`: aparece la tarjeta de vista previa | Sí (`03-previsualizacion-*.png`) |
| «Confirmar importación» | «3 fila(s) procesada(s), 3 producto(s) nuevo(s), 3 lote(s) y 135 unidades ingresadas» |
| «Cancelar» a 360 px | Cierra la tarjeta sin escribir |
| Desborde horizontal a 360 px y a 1280 px | No |
| CLS acumulado en `/admin/products` y `/admin/pos` | 0 |

## Observaciones (sin bloqueo)

- `/admin/products` no muestra miniaturas: la tabla usa un icono por fila, es
  el diseño actual, no un fallo de carga.
- `/admin/pos` pinta foto solo donde el producto la tiene (195 de 887); el resto
  usa la ilustración de respaldo. Los 3 productos importados nacen sin foto.
- La importación escribe de verdad en la base local: repetir el procedimiento
  vuelve a sumar lotes. Para repetirlo limpio, usar otro lote en el CSV.

## Reproducir

```powershell
$env:PLAYWRIGHT_CORE = "$env:LOCALAPPDATA\npm-cache\_npx\<hash>\node_modules\playwright-core"
php artisan db:seed --class=UsuariosDemostracionSeeder   # desde botica-san-juan-backend
node docs\evidencias\auditoria-asistente-excel-v08\auditar.mjs
```
