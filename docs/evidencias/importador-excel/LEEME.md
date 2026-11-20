# Evidencias · Importador Excel/CSV asistido por IA

## Archivos de prueba incluidos
- `ejemplo-reposicion.csv`: archivo CSV separado por `;` con encabezados con tildes y mayúsculas, fechas mixtas (`dd/mm/aaaa` e ISO `aaaa-mm-dd`) y precios con coma decimal.
- `ejemplo-reposicion.xlsx`: libro Excel `.xlsx` válido con productos ficticios (`Amoxicilina`, `Azitromicina`, `Omeprazol`).

## Cómo reproducir la importación (UI y API)

### 1. Desde el widget del Asistente (navegador)
1. Inicia sesión en el panel administrativo (`/admin`) como usuario con rol `administrador`.
2. Abre el widget flotante **Asistente** (esquina inferior derecha).
3. Pulsa el botón de clip (**Adjuntar archivo Excel o CSV**) y selecciona `ejemplo-reposicion.csv` o `ejemplo-reposicion.xlsx`.
4. Verifica la tarjeta de **Previsualización de importación** (recuento de válidas, duplicados, advertencias y errores). En este punto no se ha escrito nada en la base de datos.
5. Pulsa **Confirmar importación** (o **Cancelar** para descartar el token).
6. Verifica en **Bitácora de operación** (`GET /api/eventos?codigo=IMPORTACION_INVENTARIO`) que se registró el evento con el resumen de productos y lotes ingresados.

### 2. Pruebas automatizadas
```powershell
cd botica-san-juan-backend
php artisan test --filter=AsistenteImportacionTest

cd ../botica-san-juan-frontend
.\node_modules\.bin\vitest.cmd run src/components/__tests__/AsistenteBoticaWidget.spec.ts
.\node_modules\.bin\vue-tsc.cmd --build --force
```
