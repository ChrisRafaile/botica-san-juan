# Informe de tercera pasada · Extractor de imágenes del catálogo

Medida el 2026-11-20 sobre la base local, con `--segunda-pasada` (misma política
de la segunda pasada: concentración estricta, exclusiones y forma compatible).

| Indicador | Segunda pasada | Tercera pasada |
|---|---:|---:|
| Catálogo externo indexado | — | 5 120 productos |
| Productos locales emparejados | 195 | 197 |
| Descargas fallidas (HTTP 404 en origen) | 2 | 2 |
| Productos con foto real asignada | 195 | **195** |
| Productos que conservan respaldo | 689 | 692 (el catálogo local creció) |
| Cambios en `correspondencia_imagenes.json` | — | ninguno |

## Lectura

- Los 2 emparejamientos adicionales respecto a la segunda pasada son los mismos
  dos que ya fallaban con 404 en el CDN (`Naproxeno Sódico 550 mg`): no aportan
  fotos nuevas. La cobertura real se mantiene en 195 productos.
- No se rompió ninguna ruta existente: `php artisan catalogo:poblar-imagenes
  --dry-run` informa 0 actualizados, 195 sin cambios y 0 archivos faltantes.
- El catálogo local pasó de 884 a 887 productos por la importación de prueba del
  Asistente (ver `docs/evidencias/importador-excel/`). Son productos ficticios
  sin foto, y conservan la ilustración de respaldo.
- Las exclusiones manuales de `scripts/exclusiones_imagenes.json` siguen activas.
- Conclusión: el emparejador conservador llegó a su techo con esta fuente. Subir
  la cobertura exige otra fuente de fotografías, no relajar los umbrales.

## Cómo reproducirla

```powershell
python -m pytest scripts\test_extraer_imagenes_catalogo.py
python scripts\extraer_imagenes_catalogo.py --solo-indexar
python scripts\extraer_imagenes_catalogo.py --solo-emparejar --segunda-pasada
python scripts\extraer_imagenes_catalogo.py --segunda-pasada
cd botica-san-juan-backend
php artisan catalogo:poblar-imagenes --dry-run
```
