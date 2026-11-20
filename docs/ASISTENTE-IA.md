# Asistente IA de Botica San Juan

## 1. Arquitectura general

El asistente combina un agente de solo lectura en el backend (`laravel/ai` v1.x) con un widget ligero en Vue 3 montado tanto en el panel administrativo (`AdminLayout.vue`) como en el portal de clientes (`ClientLayout.vue`).

- **Configuración:** `botica-san-juan-backend/config/asistente.php`
- **Agente:** `App\Ai\Agents\AsistenteBotica` (instrucciones en español, defensa contra prompt-injection, tope de pasos de herramientas `#[MaxSteps(4)]`)
- **Servicio orquestador:** `App\Services\AsistenteService` (resolución de proveedores con clave, fallback ordenado, límites de longitud e historial)
- **Controlador y rutas:** `App\Http\Controllers\AsistenteController` (`GET /api/asistente/estado` y `POST /api/asistente/mensajes` con `auth:sanctum` y `throttle:asistente`) y `App\Http\Controllers\AsistenteImportacionController` (`POST /api/asistente/importaciones/previsualizar`, `POST /api/asistente/importaciones/confirmar` y `POST /api/asistente/importaciones/cancelar` con `auth:sanctum` y `admin`)
- **Frontend:** `src/composables/useAsistente.ts` (con `AbortController`), `src/utils/markdownSeguro.ts` (escape HTML previo) y `src/components/AsistenteBoticaWidget.vue`

## 2. Proveedores gratuitos y respaldo

Todos los proveedores configurados soportan nivel gratuito y se leen exclusivamente desde variables de entorno:

| Orden | Proveedor | Variable de clave | Modelo por defecto |
|---|---|---|---|
| Primario | Groq (`groq`) | `GROQ_API_KEY` | `openai/gpt-oss-120b` |
| Respaldo 1 | Google AI Studio (`gemini`) | `GEMINI_API_KEY` | `gemini-flash-latest` |
| Respaldo 2 | OpenRouter (`openrouter`) | `OPENROUTER_API_KEY` | `google/gemini-2.0-flash-exp:free` |

En `botica-san-juan-backend/.env.example` las tres claves se dejan **vacías a propósito**: sin ninguna clave configurada, `AsistenteService::estaDisponible()` devuelve `false`, el endpoint `POST /api/asistente/mensajes` responde `503 Service Unavailable` con un mensaje claro en español, no se invoca el SDK de IA y el widget muestra el estado deshabilitado.

## 3. Matriz Herramienta × Rol

Todas las herramientas llaman a los servicios y modelos del dominio (nunca SQL directo sin reglas de negocio). La autorización se aplica al construir la lista de herramientas del agente **y se revalida dentro de `handle()`** en cada herramienta:

| Herramienta | Tipo | Servicio / Modelo subyacente | `cliente` | `farmaceutico` | `administrador` |
|---|---|---|---|---|---|
| `BuscarMedicamentosTool` | Solo lectura | `Producto::scopeBuscar` (unaccent), `Lote::disponible`, `ConsultorDeInteracciones` | Sí (precio y stock público) | Sí | Sí |
| `ConsultarStockYLotesFefoTool` | Solo lectura | `Producto`, `Lote::ordenFefo` (lotes, vencimientos, costos) | No | Sí | Sí |
| `AlertasVencimientoTool` | Solo lectura | `AlertaVencimientoService` | No | Sí | Sí |
| `ResumenVentasYCajaTool` | Solo lectura | `ResumenTableroService`, `CajaService` | No | Sí | Sí |
| `ImportarInventarioDesdeExcelTool` | Escritura segura (token de un solo uso) | `ImportadorInventarioService` (`DB::transaction()`, `InventarioService::ingresar()`, `EventoOperacion`) | No | No | Sí |

### Flujo seguro de importación Excel/CSV
1. El administrador adjunta un archivo `.xlsx` o `.csv` (máximo 5 MB) en el widget o lo envía a `POST /api/asistente/importaciones/previsualizar`.
2. `LectorArchivoInventarioService` verifica la firma real del archivo (magic bytes, `finfo` y estructura ZIP/texto) sin confiar en la extensión y normaliza encabezados (tildes/mayúsculas), fechas (`dd/mm/aaaa`, ISO y serial Excel) y decimales con coma.
3. El backend devuelve la vista previa (filas válidas, duplicados, advertencias y errores) más un `token_confirmacion` temporal en caché (TTL 15 min, ligado al `usuario_id`) sin escribir nada en la base de datos.
4. Solo tras la confirmación explícita del administrador (botón «Confirmar importación» o invocación de `ImportarInventarioDesdeExcelTool` con el token), `ImportadorInventarioService::confirmar()` consume el token de un solo uso, crea/actualiza productos y lotes FEFO dentro de `DB::transaction()` y registra el evento `IMPORTACION_INVENTARIO` en `eventos_operacion`.

## 4. Privacidad, límites y seguridad

- **Sin PII al proveedor:** el agente no recibe ni envía DNI, correos electrónicos, teléfonos ni direcciones del usuario.
- **Sin registro de conversaciones:** `AsistenteController` no escribe prompts, respuestas ni claves en los logs de la aplicación.
- **Límites configurables (`config/asistente.php`):** máximo 12 mensajes de historial, 1000 caracteres por mensaje, 4 pasos de herramientas, 25 s de timeout y 15 peticiones/minuto por usuario autenticado.
- **Límites clínicos:** el prompt del sistema prohíbe emitir diagnósticos o indicar dosis, remitiendo siempre al químico farmacéutico.
- **Frontend seguro:** `renderizarMarkdownSeguro()` escapa todo HTML antes de aplicar formato Markdown mínimo (`**negrita**`, `*cursiva*`, listas y código en línea).

`ASISTENTE_IA_PROVEEDOR` y `ASISTENTE_IA_MODELO` (o `ASISTENTE_PROVEEDOR`) cambian el proveedor y el modelo primarios desde el entorno, sin tocar código.
