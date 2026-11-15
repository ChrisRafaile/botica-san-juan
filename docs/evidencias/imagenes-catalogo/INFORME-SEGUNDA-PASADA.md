# Informe de segunda pasada controlada · Extractor de imágenes del catálogo

## Resumen cuantitativo

| Indicador | Primera pasada | Segunda pasada | Diferencia |
|---|---:|---:|---:|
| Total de productos en catálogo local | 884 | 884 | — |
| Productos con fotografía real asignada | 116 | 195 | **+79** |
| Productos con ilustración referencial de respaldo | 768 | 689 | -79 |
| Archivos `.webp` únicos versionados en disco | 56 | 99 | +43 |
| Cobertura sobre catálogo | 13.1 % | 22.1 % | +8.9 pp |

- **Coincidencias de primera pasada alteradas o perdidas:** 0 (se preservan íntegras las 116 correspondencias previas).
- **Exclusiones manuales (`scripts/exclusiones_imagenes.json`):** siguen activas en ambas pasadas (productos `191`, `205`, `547` y sus `fuente_pid` asociados).
- **Descargas fallidas en CDN origen (HTTP 404):** 2 (`#651` y `#652` *Naproxeno Sódico 550 mg*, recurso remoto `12318-BLISTER` devolvió `404`; se descartaron automáticamente para no registrar imágenes rotas).

## Criterios aplicados en la segunda pasada (`--segunda-pasada`)

La relajación solo se activa al pasar explícitamente `--segunda-pasada` en CLI o `segunda_pasada=True` en `emparejar_productos(...)`, y mantiene invariables las reglas de seguridad sanitaria:

1. **Concentración estricta:** nunca se emparejan concentraciones distintas (`500 mg ≠ 1 g`, `15 mg/5 mL ≠ 30 mg/5 mL`) ni casos donde solo un lado declare concentración.
2. **Forma farmacéutica compatible:** nunca se cruzan formas distintas (`jarabe ≠ tableta`, `tableta ≠ cápsula`, `crema ≠ ungüento`, `tableta ≠ masticable/efervescente`). Solo se aceptan equivalencias seguras comprobadas:
   - `tipo = SOLUCION` oral DIGEMID (frascos no tópicos/nasales/oftálmicos/óticos) frente a `Jarabe` / `Solución Oral` con idéntica concentración y volumen.
   - Títulos externos sin palabra explícita de forma (p. ej. `Celecoxib 200 mg - Blister 10 UN`) únicamente cuando el envase primario (`blister`, `caja`, `frasco`, `tubo`) fija la forma y **no existe ambigüedad** en el catálogo local para el mismo `(principio/marca, concentración)`.
   - Corrección morfológica `emulgel(?:es)?` e indicadores `IM`/`IV` para inyectables.
3. **Variantes léxicas seguras:**
   - Adjetivos descriptivos de envase/forma en masculino o sabor (`recubierto`, `blando`, `sabor`, `bebible`, `antimicotica`, `gastrorresistentes`).
   - Sufijo de sal farmacéutica omitido en nombre comercial (`clorhidrato`, `maleato`, `hiclato`, `sodico`) únicamente si la otra parte no declara una sal distinta en conflicto (`sódico ≠ potásico`).
4. **Presentación y envase controlado:**
   - Sólidos orales `blister` / `caja` con preferencia de puntaje hacia coincidencia exacta de envase.
   - Inyectables donde el volumen aparece dentro de la concentración (`500 mg/2 mL`) y el catálogo externo omite repetir `2 mL` después del guion (`Ampolla - Caja 10 UN`).
   - Frascos gotero (`Caja Frasco gotero x 15 mL` vs `Caja 15 ML`) con idéntica forma y volumen, y correcciones tipográficas de unidad en frascos/tubos del mismo volumen numérico (`Frasco 120 UN` vs `120 mL`, `Tubo x 20 mL` vs `Tubo 20 G` en cremas).

## Procedimiento para reproducir

Desde la raíz del repositorio:

```powershell
# 1. Ejecutar las pruebas unitarias del emparejador (sin red ni base de datos)
python -m pytest scripts\test_extraer_imagenes_catalogo.py

# 2. Indexar el catálogo público externo (respetando robots.txt y caché en disco)
python scripts\extraer_imagenes_catalogo.py --solo-indexar

# 3. Previsualizar coincidencias de primera y segunda pasada sin descargar imágenes
python scripts\extraer_imagenes_catalogo.py --solo-emparejar --segunda-pasada

# 4. Descargar, optimizar a WebP (máx. 600 px) y regenerar correspondencia_imagenes.json
python scripts\extraer_imagenes_catalogo.py --segunda-pasada

# 5. Paso de base de datos (idempotente; en despliegue lo ejecuta entrypoint.sh)
cd botica-san-juan-backend
php artisan catalogo:poblar-imagenes --dry-run
php artisan catalogo:poblar-imagenes
```

## Emparejamientos nuevos incorporados (79 productos)

| ID | Producto local | Concentración | Presentación | PID fuente | Título catálogo externo | Puntaje | Archivo WebP |
|---:|---|---|---|---|---|---:|---|
| 10 | AB-BRONCOL NF 600 | 600 mg | Caja 1 Vial + 1 Ampolla Solvente x 5 mL | `00141` | AB Broncol NF 600Mg Suspensión Inyectable - Caja 1 UN | 95.0 | `storage/productos/00141.webp` |
| 23 | ACICLOVIR | 200 mg | Caja Envase Blister Tabletas | `07421` | Aciclovir 200 Mg - Caja 100 UN | 90.0 | `storage/productos/07421.webp` |
| 25 | ACICLOVIR | 200 mg | Caja Envase Blister Tabletas | `07421` | Aciclovir 200 Mg - Caja 100 UN | 90.0 | `storage/productos/07421.webp` |
| 33 | AERO OM SABOR A FRESAS | 100 mg/mL | Caja Frasco gotero x 15mL | `04458` | Gotas Aero OM 100 mg/mL Sabor Fresa - Caja 15 ML | 101.0 | `storage/productos/04458.webp` |
| 58 | AMBROXOL | 15 mg/5 mL | Frasco x 120 mL | `14763` | Ambroxol 15mg/5ml Jarabe - Frasco 120 ML | 100.0 | `storage/productos/14763.webp` |
| 63 | AMIKACINA | 500 mg/2 mL | Ampolla x 2mL | `41915` | Amikacina 500Mg/2Ml Ampolla - Caja 10 UN | 98.0 | `storage/productos/41915.webp` |
| 64 | AMIKACINA | 500 mg/2 mL | Ampolla 2 mL | `41915` | Amikacina 500Mg/2Ml Ampolla - Caja 10 UN | 98.0 | `storage/productos/41915.webp` |
| 141 | ATORVASTATINA | 20 mg | Caja envase | `12545` | Atorvastatina 20 Mg  - Caja 100 UN | 91.0 | `storage/productos/12545.webp` |
| 143 | ATORVASTATINA | 20 mg | Caja envase | `12545` | Atorvastatina 20 Mg  - Caja 100 UN | 91.0 | `storage/productos/12545.webp` |
| 187 | BETAMETASONA | 0.05 % | Tubo X 20  mL | `44123` | Betametasona 0.05% - Tubo 20 G | 94.0 | `storage/productos/44123.webp` |
| 206 | BISOLVON ADULTOS | 8 mg/5 mL | Caja Frasco x 120 mL + Cuchara dosificadora | `00796` | Bisolvon Adultos 8Mg/5Ml Jarabe - Frasco 120 ML | 100.0 | `storage/productos/00796.webp` |
| 240 | BUSCAPINA | 20 mg/mL | Ampolla X 1 mL | `03210` | Buscapina 20Mg/1Ml Solución Inyectable - Ampolla 1 UN | 98.0 | `storage/productos/03210.webp` |
| 242 | BUSCAPINA | 20 mg/mL | Caja Ampolla x 1 mL | `03210` | Buscapina 20Mg/1Ml Solución Inyectable - Ampolla 1 UN | 98.0 | `storage/productos/03210.webp` |
| 243 | BUSCAPINA COMPOSITUM N | Caja Envase Blíster Comprimidos | Caja Envase Blíster Comprimidos | `00803-BLISTER` | Buscapina Compositum-N Comprimidos Recubiertos - Blister 10 UN | 81.0 | `storage/productos/00803-blister.webp` |
| 259 | CEFACROL IM 1000 | 1000 mg | Vial | `13020` | Cefacrol IM 1000 Mg - Caja 1 UN | 93.0 | `storage/productos/13020.webp` |
| 275 | CELECOXIB | 200 mg | Caja Envase Blister Cápsula | `16690-BLISTER` | Celecoxib 200 mg  - Blister 10 UN | 91.0 | `storage/productos/16690-blister.webp` |
| 276 | CELECOXIB | 200 mg | Caja Envase Blister Cápsulas | `16690-BLISTER` | Celecoxib 200 mg  - Blister 10 UN | 91.0 | `storage/productos/16690-blister.webp` |
| 277 | CELECOXIB | 200 mg | Caja Envase Blister Cápsulas | `16690-BLISTER` | Celecoxib 200 mg  - Blister 10 UN | 91.0 | `storage/productos/16690-blister.webp` |
| 278 | CELECOXIB | 200 mg | Caja Envase Blister Cápsulas | `16690-BLISTER` | Celecoxib 200 mg  - Blister 10 UN | 91.0 | `storage/productos/16690-blister.webp` |
| 280 | CETIRIZINA | 5 mg/5 mL | Caja Frasco X 60 mL | `14565` | Cetirizina 5Mg/5Ml Jarabe - Frasco 60 ML | 100.0 | `storage/productos/14565.webp` |
| 282 | CETIRIZINA | 5 mg/5 mL | Caja Frasco X 60 mL | `14565` | Cetirizina 5Mg/5Ml Jarabe - Frasco 60 ML | 100.0 | `storage/productos/14565.webp` |
| 284 | CETIRIZINA | 5 mg/5 mL | Frasco X 60 mL | `14565` | Cetirizina 5Mg/5Ml Jarabe - Frasco 60 ML | 100.0 | `storage/productos/14565.webp` |
| 300 | CLINDAMICINA | 600 mg/4 mL | Caja Ampolla x 4 mL | `41916-AMPOLLA` | Clindamicina 600 Mg / 4 ML - Caja 1 Ampolla | 98.0 | `storage/productos/41916-ampolla.webp` |
| 301 | CLINDAMICINA | 300 mg | Caja Envase Blister Cápsulas | `25819` | Clindamicina 300 Mg - Blister 10 UN | 91.0 | `storage/productos/25819.webp` |
| 302 | CLINDAMICINA | 600 mg/4 mL | Caja Ampolla X 4 mL | `41916-AMPOLLA` | Clindamicina 600 Mg / 4 ML - Caja 1 Ampolla | 98.0 | `storage/productos/41916-ampolla.webp` |
| 303 | CLINDAMICINA | 300 mg | Caja Envase Blister Cápsulas | `25819` | Clindamicina 300 Mg - Blister 10 UN | 91.0 | `storage/productos/25819.webp` |
| 304 | CLINDAMICINA | 300 mg | Caja Envase Blister Cápsulas | `25819` | Clindamicina 300 Mg - Blister 10 UN | 91.0 | `storage/productos/25819.webp` |
| 305 | CLINDAMICINA | 600 mg/4 mL | Ampolla x 4 mL | `41916-AMPOLLA` | Clindamicina 600 Mg / 4 ML - Caja 1 Ampolla | 98.0 | `storage/productos/41916-ampolla.webp` |
| 320 | CLORFENAMINA MALEATO | 4 mg | Caja Envase Blister Tabletas | `13988` | Clorfenamina 4 Mg Tableta - Caja 100 UN | 92.0 | `storage/productos/13988.webp` |
| 321 | CLORFENAMINA MALEATO | 2 mg/5 mL | Caja Frasco X 60 mL | `12575` | Clorfenamina Maleato 2mg/5 mL Solución oral - Frasco 60 ML | 100.0 | `storage/productos/12575.webp` |
| 322 | CLORFENAMINA MALEATO | 4 mg | Caja Envase Blister Tabletas | `13988` | Clorfenamina 4 Mg Tableta - Caja 100 UN | 92.0 | `storage/productos/13988.webp` |
| 323 | CLORFENAMINA MALEATO | 4 mg | NULL | `13988` | Clorfenamina 4 Mg Tableta - Caja 100 UN | 92.0 | `storage/productos/13988.webp` |
| 344 | CORTIFLEX | 50 mg/5 mL | Caja Vial X 5 mL | `23055` | Cortiflex 50 Mg/5 Ml Ampolla - Caja 1 UN | 95.0 | `storage/productos/23055.webp` |
| 345 | DAFLON | 500 mg | Caja Envase Blister Tabletas | `38370-BLISTER` | Daflon 500 Mg Comprimido Recubierto - Blister 15 UN | 96.0 | `storage/productos/38370-blister.webp` |
| 355 | DEXAMETASONA | 4 mg | Caja Envase Blister Tabletas | `20734-BLISTER` | Dexametasona 4Mg - Blister 10 UN | 91.0 | `storage/productos/20734-blister.webp` |
| 377 | DIBROLAX | 5 mg | Caja Envase Blister Tabletas | `04074-BLISTER` | Dibrolax 5 Mg  - Blister 10 UN | 91.0 | `storage/productos/04074-blister.webp` |
| 430 | DOLONET FORTE | 400 mg | Caja Envase Blister Cápsulas | `12500-BLISTER` | Dolonet Forte 400 Mg - Blíster 10 UN | 91.0 | `storage/productos/12500-blister.webp` |
| 443 | DOXICICLINA HICLATO | 100 mg | Caja Envase Blister Cápsulas | `14295-BLISTER` | Doxiciclina 100 Mg Cápsula - Blister 10 UN | 93.0 | `storage/productos/14295-blister.webp` |
| 453 | ENTEROGERMINA | 2000 millones 2 millardos/5 | Caja Ampolla x 5 mL | `15349-FRASCO` | Enterogermina Ampolla Bebible - Frasco 1 UN | 83.0 | `storage/productos/15349-frasco.webp` |
| 550 | GYNOCANESTEN® | 1% | Caja Tubo x 50 g + Accesorio | `13672` | Gynocanesten 1% Crema Antimicótica Vaginal - Tubo 50 G | 104.0 | `storage/productos/13672.webp` |
| 559 | HISALER | 10 mg | Caja Envase Blíster Comprimidos | `01734` | Hisaler 10 Mg  - Blister 10 UN | 91.0 | `storage/productos/01734.webp` |
| 570 | IBUPROFENO | 100 mg/5 mL | Frasco X 60 mL | `13823` | Ibuprofeno 100Mg/5ml  - Frasco 60Ml | 100.0 | `storage/productos/13823.webp` |
| 579 | IRRIGOR PLUS | 100 mg + 30 mg | Caja Envase Blíster Comprimidos | `06873` | Irrigor Plus 30 Mg /100 Mg - Caja 30 UN | 90.0 | `storage/productos/06873.webp` |
| 592 | KETOPROFENO | 100 mg | Caja Envase Blister Tabletas | `05354` | Ketoprofeno 100 mg  - Caja 30 UN | 90.0 | `storage/productos/05354.webp` |
| 593 | KETOPROFENO | 100 mg/2 mL | Caja Ampolla x 2 mL | `03101` | ketoprofeno 100 Mg/2 ML Solución Inyectable - Caja 6 UN | 95.0 | `storage/productos/03101.webp` |
| 594 | KETOPROFENO | 100 mg | Caja Envase Blister Tabletas | `05354` | Ketoprofeno 100 mg  - Caja 30 UN | 90.0 | `storage/productos/05354.webp` |
| 596 | KETOPROFENO | 100 mg/2 mL | Caja Ampolla x 2 mL | `03101` | ketoprofeno 100 Mg/2 ML Solución Inyectable - Caja 6 UN | 95.0 | `storage/productos/03101.webp` |
| 599 | KETOROLACO | 10 mg | Caja Envase Blister Tabletas | `13267-BLISTER` | Ketorolaco 10 mg - Blister 10 UN | 91.0 | `storage/productos/13267-blister.webp` |
| 600 | KETOROLACO | 10 mg | Caja Envase Blister Tabletas | `13267-BLISTER` | Ketorolaco 10 mg - Blister 10 UN | 91.0 | `storage/productos/13267-blister.webp` |
| 603 | KETOROLACO | 10 mg | Caja Envase Blister Tabletas | `13267-BLISTER` | Ketorolaco 10 mg - Blister 10 UN | 91.0 | `storage/productos/13267-blister.webp` |
| 609 | LEVOFLOXACINO | 500 mg | Caja Envase Blister Tabletas | `14128` | Levofloxacino 500 Mg  - Caja 7UN | 90.0 | `storage/productos/14128.webp` |
| 624 | MEDICORT | 4 mg/2 mL | Caja Ampolla x 2 mL | `10012` | Medicort 4Mg/2ML - Ampolla 1 UN | 98.0 | `storage/productos/10012.webp` |
| 635 | METFORMINA CLORHIDRATO | 850 mg | Caja Envase Blister Tabletas | `13902` | Metformina 850 Mg Tableta - Caja 30 UN | 92.0 | `storage/productos/13902.webp` |
| 636 | METFORMINA CLORHIDRATO | 850 mg | Caja Envase Blister Tabletas | `13902` | Metformina 850 Mg Tableta - Caja 30 UN | 92.0 | `storage/productos/13902.webp` |
| 642 | METRONIDAZOL | 250 mg/5 mL | Caja Frasco x 120 mL + Accesorio | `14774` | Metronidazol 250mg/5ml - Frasco 120 ML | 100.0 | `storage/productos/14774.webp` |
| 644 | METRONIDAZOL | 250 mg/5 mL | Frasco X 120 mL | `14774` | Metronidazol 250mg/5ml - Frasco 120 ML | 100.0 | `storage/productos/14774.webp` |
| 658 | OMEPRAZOL | 20 mg | Caja Envase Blister Cápsulas | `15486-BLISTER` | Omeprazol 20 Mg - Blister 10 UN | 91.0 | `storage/productos/15486-blister.webp` |
| 659 | OMEPRAZOL | 20 mg | Caja Envase Blister Cápsulas | `15486-BLISTER` | Omeprazol 20 Mg - Blister 10 UN | 91.0 | `storage/productos/15486-blister.webp` |
| 664 | PARACETAMOL | 120 mg/5 mL | Frasco X 60 mL | `12975` | Paracetamol Jarabe 120 mg/5ml- Frasco 60 ML | 100.0 | `storage/productos/12975.webp` |
| 665 | PARACETAMOL | 500 mg | Caja Envase Blister Tabletas | `05596` | Paracetamol 500 Mg - Caja 100 UN | 90.0 | `storage/productos/05596.webp` |
| 667 | PARACETAMOL | 500 mg | Caja Envase Blister Tabletas | `05596` | Paracetamol 500 Mg - Caja 100 UN | 90.0 | `storage/productos/05596.webp` |
| 669 | PARACETAMOL | 500 mg | Caja Envase Blister Tabletas | `05596` | Paracetamol 500 Mg - Caja 100 UN | 90.0 | `storage/productos/05596.webp` |
| 670 | PARACETAMOL | 500 mg | Caja Envase Blister Tabletas | `05596` | Paracetamol 500 Mg - Caja 100 UN | 90.0 | `storage/productos/05596.webp` |
| 672 | PARACETAMOL | 500 mg | Caja Envase Blister Tabletas | `05596` | Paracetamol 500 Mg - Caja 100 UN | 90.0 | `storage/productos/05596.webp` |
| 673 | PARACETAMOL | 120 mg/5 mL | Frasco X 60 mL | `12975` | Paracetamol Jarabe 120 mg/5ml- Frasco 60 ML | 100.0 | `storage/productos/12975.webp` |
| 674 | PARACETAMOL | 500 mg | Caja Envase Blister Tabletas | `05596` | Paracetamol 500 Mg - Caja 100 UN | 90.0 | `storage/productos/05596.webp` |
| 676 | PARACETAMOL | 120 mg/5 mL | Frasco X 60 mL | `12975` | Paracetamol Jarabe 120 mg/5ml- Frasco 60 ML | 100.0 | `storage/productos/12975.webp` |
| 678 | PARACETAMOL | 500 mg | Caja Envase Blister Tabletas | `05596` | Paracetamol 500 Mg - Caja 100 UN | 90.0 | `storage/productos/05596.webp` |
| 690 | PREDNISONA | 20 mg | Caja Envase Blister Tabletas | `36033-BLISTER` | Prednisona 20 Mg - Blíster 10 UN | 91.0 | `storage/productos/36033-blister.webp` |
| 695 | PREDNISONA | 20 mg | Caja Envase Blister Tabletas | `36033-BLISTER` | Prednisona 20 Mg - Blíster 10 UN | 91.0 | `storage/productos/36033-blister.webp` |
| 698 | PREDNISONA | 20 mg | Caja Envase Blister Tabletas | `36033-BLISTER` | Prednisona 20 Mg - Blíster 10 UN | 91.0 | `storage/productos/36033-blister.webp` |
| 711 | SALBUTAMOL | 2 mg/5 mL | Caja Frasco x 120 mL | `14777` | Salbutamol 2 Mg/5 Ml Jarabe - Frasco 120 UN | 96.0 | `storage/productos/14777.webp` |
| 714 | SILDENAFILO | 50 mg | Caja Envase Blister Tabletas | `09042` | Sildenafilo 50 Mg - Caja 1UN | 90.0 | `storage/productos/09042.webp` |
| 715 | SILDENAFILO | 50 mg | Caja Envase Blister Tabletas | `09042` | Sildenafilo 50 Mg - Caja 1UN | 90.0 | `storage/productos/09042.webp` |
| 716 | SIMETICONA | 80 mg | Caja Envase Blister Tabletas | `12845` | Simeticona 80 mg  - Caja 30UN | 90.0 | `storage/productos/12845.webp` |
| 727 | TERBINAFINA | 250 mg | Caja Envase Blister Tabletas | `24985-BLISTER` | Terbinafina 250 mg  - Blister 10 UN | 91.0 | `storage/productos/24985-blister.webp` |
| 728 | TERBINAFINA | 250 mg | Caja Envase Blister Tabletas | `24985-BLISTER` | Terbinafina 250 mg  - Blister 10 UN | 91.0 | `storage/productos/24985-blister.webp` |
| 1310 | FRUTTIFLEX 50 SABOR A FRESA | ELECTROLITOS 50 mEq/L Na | Frasco x 1L | `01935` | Fruttiflex 50 Fresa - Frasco 1000 ML | 85.0 | `storage/productos/01935.webp` |
| 1377 | GYNOCANESTEN® | 0.01 | Caja Tubo x 50 g + Accesorio | `13672` | Gynocanesten 1% Crema Antimicótica Vaginal - Tubo 50 G | 104.0 | `storage/productos/13672.webp` |
