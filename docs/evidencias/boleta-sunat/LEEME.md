# Representación impresa de la boleta y de la factura electrónica

Muestras del PDF que entrega `GET /api/facturacion/documentos/{comprobante}/pdf`
tras el rediseño según la estructura de la representación impresa SUNAT.

| Archivo | Qué es |
|---|---|
| `01-boleta-B001-00000001.pdf` | Boleta de venta electrónica: 3 unidades de amoxicilina 500 mg a un cliente con DNI. |
| `02-factura-F001-00000001.pdf` | Factura electrónica: 10 unidades de amoxicilina 500 mg a un cliente con RUC. |

**Todos los datos son ficticios.** El RUC del emisor (`20123456789`), la razón
social, la dirección y los clientes los fija la propia prueba; ninguno sale del
`.env` de la máquina donde se generaron.

## Qué comprobar en cada muestra

- **Cabecera a dos columnas.** A la izquierda el emisor; a la derecha el
  recuadro con el RUC, la denominación oficial y la serie-correlativo.
- **Adquirente y operación.** Tipo y número de documento, nombre o razón
  social, fecha de emisión, moneda y medio de pago.
- **Detalle.** Cantidad, unidad, descripción, valor unitario (sin IGV), precio
  unitario (con IGV) e importe.
- **Cierre.** Op. Gravada, IGV 18 %, Total a Pagar, importe en letras y QR.

Los importes cuadran al céntimo:

| Muestra | Op. Gravada | IGV | Total | En letras |
|---|---|---|---|---|
| Boleta | 31.78 | 5.72 | 37.50 | SON: TREINTA Y SIETE CON 50/100 SOLES |
| Factura | 105.93 | 19.07 | 125.00 | SON: CIENTO VEINTICINCO CON 00/100 SOLES |

El QR, leído con cualquier teléfono, devuelve la cadena con el orden que fija
la SUNAT (`RUC|tipo|serie|número|IGV|total|fecha|tipo doc.|nº doc.|`):

```
20123456789|03|B001|00000001|5.72|37.50|2026-11-11|1|45678912|
20123456789|01|F001|00000001|19.07|125.00|2026-11-11|6|20600000001|
```

## Cómo se regeneran

Los PDF los escribe la misma prueba que verifica la descarga, cuando se le
indica una carpeta. No hay un guion aparte: la evidencia sale del código y de
los datos que se prueban.

```powershell
cd botica-san-juan-backend
$env:MUESTRA_PDF_DIR = "$env:TEMP\muestra-boleta"
New-Item -ItemType Directory -Force $env:MUESTRA_PDF_DIR | Out-Null
php artisan test --filter=RepresentacionImpresaTest
Remove-Item Env:MUESTRA_PDF_DIR
```

Deja `muestra-B001-00000001.pdf` y `muestra-F001-00000001.pdf` en esa carpeta.
Sin la variable, la prueba no escribe nada en disco.

## Dónde está el código

| Archivo | Papel |
|---|---|
| `app/Services/Sunat/RepresentacionImpresa.php` | Arma los datos, la cadena del QR y el PDF. |
| `app/Services/Sunat/ImporteEnLetras.php` | Importe en letras, calculado en céntimos enteros. |
| `resources/views/pdf/comprobante.blade.php` | Plantilla; no calcula nada. |
| `tests/Feature/RepresentacionImpresaTest.php` | Denominación, serie, totales, QR y descarga. |
| `tests/Unit/ImporteEnLetrasTest.php` | Casos límite de la conversión a letras. |

## Lo que esta representación todavía no lleva

El envío a la SUNAT sigue en modo simulado y el XML no se firma. Por eso el QR
termina en la barra final sin el valor resumen de la firma, y el PDF no cita
una resolución de autorización. Ambos datos se añaden cuando exista el XML
UBL firmado; ver `docs/FACTURACION-SUNAT.md`.
