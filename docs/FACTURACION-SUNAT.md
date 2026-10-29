# Facturación electrónica: lo que hay y lo que falta

> Botica San Juan · análisis del 29/10/2026
> Contrastado con la clase abierta **«Aprende a Conectar tu Facturador
> Electrónico PHP (Directo SUNAT)»**
> (`youtube.com/live/vcyl-n1cp5g`, transcripción automática de la sesión).

---

## 1. Por qué existe este documento

El informe describe un módulo de «facturación electrónica» y el sistema
emite comprobantes con serie y correlativo correctos. Eso puede leerse como
que el sistema factura ante la SUNAT, y **no es el caso**. Este documento
dice exactamente dónde está la línea, para que nadie —ni la profesora, ni un
futuro desarrollador, ni la propietaria— se la encuentre por sorpresa.

---

## 2. El flujo real, según la sesión

Siete pasos, de los cuales la sesión cubre del tercero en adelante:

| # | Paso | Detalle que la sesión subraya |
|---|---|---|
| 1 | Generar el XML en **UBL 2.1** | Etiquetas `Invoice`, `UBLExtensions`, `cbc:`, `cac:`. Se puede usar una biblioteca o plantillas propias |
| 2 | **Firmar digitalmente** con el certificado del emisor | La firma es lo que acredita de quién es el comprobante; si se modifica algo después, queda inválido |
| 3 | **Comprimir** el XML firmado en ZIP | — |
| 4 | Convertir el ZIP a **Base64** | El ZIP es binario y el sobre SOAP transporta texto |
| 5 | Armar el **sobre SOAP** con `fileName` y `contentFile` | La cabecera lleva RUC + **usuario SOL secundario** y su clave. El usuario principal no sirve |
| 6 | Enviar por **cURL** al endpoint beta o producción | — |
| 7 | **Guardar el CDR** | La respuesta es un sobre SOAP con un `applicationResponse` en Base64, que es un ZIP, que contiene el XML del CDR |

### Las dos reglas que más fácil se incumplen

**El nombre del archivo es parte del contrato.** Formato
`RUC-TIPO-SERIE-CORRELATIVO`, por ejemplo `20123456789-01-F001-00000123`.
Tipo según el catálogo 01: `01` factura, `03` boleta, `07` y `08` notas. Y la
letra de la serie tiene que corresponder al tipo: **si se manda tipo `01` con
una serie que no empieza por `F`, la SUNAT rechaza el comprobante** sin mirar
su contenido. En la beta esa validación está relajada; en producción no.

**El CDR no se puede borrar.** Es la constancia de recepción y la única
prueba, junto con la consulta en el portal, de que un comprobante llegó. La
sesión insiste dos veces en esto: se guarda, y punto.

---

## 3. Qué tiene hoy este proyecto

Conviene decirlo sin rodeos:

| Pieza del flujo | Estado | Qué hay realmente |
|---|---|---|
| XML UBL 2.1 | **No** | `FacturacionController::buildXml()` genera un XML propio, con etiquetas inventadas (`<ComprobanteElectronico>`, `<Numero>`). No tiene espacios de nombres UBL ni estructura `cbc:`/`cac:` |
| Firma digital | **No** | No hay certificado ni biblioteca de firmado |
| ZIP | **No** | El XML se entrega suelto |
| Base64 | **No** | — |
| Sobre SOAP | **No** | — |
| Envío | **Simulado** | `SunatClient` tiene modo `simulado` y modo `api`. El modo `api` hace un `POST` JSON con *bearer token* contra un endpoint configurable: eso es el patrón de un **proveedor intermediario** (OSE o facturador de terceros), no el envío directo a la SUNAT |
| CDR | **No** | La columna `sunat_response` guarda un JSON de la respuesta simulada. **Eso no es un CDR**: no hay archivo, no hay ZIP, no hay constancia |
| Serie y correlativo | **Sí** | `ComprobanteService` bloquea la fila de la serie (`FOR UPDATE`), hay índice único sobre serie y número, y el correlativo nunca retrocede |
| Nombre de archivo | **Sí, desde hoy** | `NombreArchivoSunat` aplica la convención completa y valida la correspondencia serie/tipo |

**Resumen honesto:** lo que está resuelto es la parte de *numeración y
control interno*, que es la que más se rompe en silencio y la más cara de
arreglar después. Lo que falta es toda la *integración tributaria*.

---

## 4. Lo que se refinó a raíz de este análisis

### Convención de nombre (hecho)

`app/Services/NombreArchivoSunat.php` construye y valida el nombre:

```
20123456789-01-F001-00000123.xml     comprobante
20123456789-01-F001-00000123.zip     paquete de envío
R-20123456789-01-F001-00000123.zip   constancia de recepción
```

Valida cuatro cosas y lanza excepción en vez de dejar pasar: RUC de once
dígitos, tipo contemplado, formato de serie, y **correspondencia entre la
letra de la serie y el tipo**. Ocho pruebas lo cubren.

El prefijo `R-` del CDR no es decorativo: permite emparejar comprobante y
constancia mirando el directorio, sin consultar la base.

Se adopta ahora aunque el envío siga simulado porque hacerlo bien desde el
principio es gratis, y renombrar después archivos de los que ya dependen
registros guardados, no.

### Lo que NO se tocó, y por qué

No se implementó el envío real. No es una cuestión de esfuerzo de
programación: **hace falta un certificado digital tributario, un RUC activo
con facturación electrónica habilitada y un usuario SOL secundario**. Son
trámites del negocio, no del sistema, y fingirlos con datos inventados
produciría un módulo que pasa pruebas y falla el primer día real.

---

## 5. Cómo encaja con la contingencia offline

El análisis confirma la decisión que ya estaba tomada en
`docs/ARQUITECTURA-OFFLINE.md`:

- La **nota de contingencia `NV-xxxxxx`** que la caja entrega sin conexión no
  es un comprobante de pago, y no puede serlo: sin conexión no hay forma de
  obtener un CDR, que es lo que convierte un XML en comprobante aceptado.
- El **correlativo oficial se asigna al sincronizar**, no en la caja. La
  sesión lo confirma desde otro ángulo: la serie y el correlativo son parte
  de la identidad del documento ante la SUNAT y los valida el emisor, no el
  terminal.
- Cuando el envío real exista, **el CDR será el cierre natural del ciclo
  offline**: la venta pasa de «sincronizada» a «declarada» cuando su
  constancia esté guardada. Hoy ese estado no existe porque no hay constancia
  que guardar.

---

## 6. Qué haría falta para cerrar el ciclo

En orden, y con la dependencia de negocio señalada:

| # | Paso | Depende de |
|---|---|---|
| 1 | Reemplazar `buildXml` por un generador UBL 2.1 real | Solo de programación |
| 2 | Añadir columnas `cdr_path`, `cdr_recibido_en` y el estado «declarada» | Solo de programación |
| 3 | Empaquetado ZIP + Base64 y armado del sobre SOAP | Solo de programación |
| 4 | Firma digital del XML | **Certificado digital tributario del negocio** |
| 5 | Envío contra el entorno beta | **Usuario SOL secundario** |
| 6 | Envío contra producción | **RUC con facturación electrónica habilitada** |

Los pasos 1 a 3 se pueden hacer y probar contra el entorno beta sin que la
botica tenga nada; los pasos 4 a 6 no.

---

## 7. Una reserva que conviene dejar escrita

Esta nota resume una sesión formativa y la contrasta con el código. **No es
asesoría tributaria.** Los plazos de envío, qué se entrega al cliente en
contingencia y qué régimen aplica a esta botica son cosas que debe confirmar
su contador, como ya quedó anotado en la sección 6 de
`docs/ARQUITECTURA-OFFLINE.md`.
