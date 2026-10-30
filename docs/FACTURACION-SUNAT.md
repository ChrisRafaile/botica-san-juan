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
| ZIP | **Sí** | `EmpaquetadoSunat::comprimir()` deja un único `.xml` en la raíz del ZIP, sin carpetas |
| Base64 | **Sí** | El ZIP viaja codificado dentro del sobre |
| Sobre SOAP | **Sí** | `EmpaquetadoSunat::sobreSoap()` arma el `sendBill` con WS-Security y RUC+usuario SOL concatenados |
| Envío | **Simulado, por el camino real** | `FacturadorSunat` recorre las mismas capas en los dos modos; lo único que cambia es a dónde se manda. El modo `api` apunta al `billService` de la SUNAT (beta por omisión), no a un intermediario |
| CDR | **Sí, con la estructura formal** | Se extrae el `applicationResponse`, se guarda el ZIP como `R-[RUC]-[TIPO]-[SERIE]-[CORRELATIVO].zip` con su SHA-256, y `sunat_response` pasa a guardar código, descripción, notas y estado |
| Serie y correlativo | **Sí** | `ComprobanteService` bloquea la fila de la serie (`FOR UPDATE`), hay índice único sobre serie y número, y el correlativo nunca retrocede |
| Nombre de archivo | **Sí, desde hoy** | `NombreArchivoSunat` aplica la convención completa y valida la correspondencia serie/tipo |

**Resumen honesto:** está resuelta la *numeración y el control interno* —lo
que más se rompe en silencio y más caro sale de arreglar después— y está
resuelta toda la *envoltura* del envío. Lo que sigue faltando son las dos
cosas que no dependen de programar: **el XML UBL 2.1 de verdad y la firma
digital**. Sin firma, lo que se manda no lo acepta nadie.

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

### Empaquetado, sobre y constancia (hecho)

Todo vive en `app/Services/Sunat/`, separado en piezas que se pueden probar
por su cuenta:

| Clase | Responsabilidad |
|---|---|
| `EmpaquetadoSunat` | Comprimir, descomprimir, armar el sobre SOAP y extraer el `applicationResponse` |
| `CredencialesSunat` | RUC + usuario SOL **secundario** + clave, con `usuarioCompleto()` pegándolos sin separador |
| `ConstanciaRecepcion` | Guardar el CDR con su nombre normalizado y traducir el `ResponseCode` a un estado |
| `SimuladorSunat` | Devolver un sobre SOAP real, con un `ApplicationResponse` UBL dentro |
| `FacturadorSunat` | Encadenar los pasos y dejar el comprobante actualizado |

Tres decisiones que vale la pena dejar escritas:

**El simulador devuelve un sobre, no un array.** Lo que falla el primer día
en producción no es la lógica de negocio: es el nombre del archivo dentro del
ZIP, el Base64, o la extracción del `applicationResponse` de una respuesta
con prefijos de espacio de nombres distintos a los esperados. Un simulador
que devuelve un JSON inventado deja todo eso sin probar y da la falsa
sensación de que el camino ya está recorrido. Aquí se comprime de verdad, se
codifica de verdad y se desarma de verdad; lo único que cambia al pasar a
producción es a dónde se manda. El simulador también sabe **rechazar y
observar** (`__forzar_estado`), porque uno que solo sabe aceptar no prueba lo
que de verdad cuesta.

**Un fallo de transporte no es un rechazo.** Si el envío no llega o la
respuesta no se entiende, el comprobante queda `pendiente`, no `rechazada`.
La diferencia importa: un rechazo se corrige y se reenvía; un fallo de
transporte se reintenta tal cual. Confundirlos lleva a reenviar lo que la
SUNAT ya tiene —es decir, a duplicarlo— o a dar por perdido lo que solo
tardó.

**El código 4000 en adelante es «observado», que es ACEPTADO con reparos.**
Tratarlo como rechazo produciría exactamente el duplicado del párrafo
anterior. `ConstanciaRecepcion::estadoDesdeCodigo()` distingue los tres
casos: `0` aceptada, `4000+` observada, el resto rechazada.

Se guarda también el ZIP enviado (`facturacion/enviados/`). Sin él, ante una
discrepancia solo quedaría la palabra del sistema contra la del servicio.

Once pruebas cubren esta capa (`tests/Feature/EmpaquetadoSunatTest.php`),
incluido el caso de una respuesta con prefijos distintos y el de un ZIP sin
XML dentro.

Para auditoría externa, `GET /api/facturacion/documentos/{id}/cdr` entrega el
archivo de la constancia, y la columna `sunat_response` guarda su lectura
formal —código, descripción, notas, estado y SHA-256— para consultarla sin
abrir archivos.

### Lo que NO se tocó, y por qué

No se implementó **la firma digital ni el XML UBL 2.1 definitivo**. No es una
cuestión de esfuerzo de programación: **hace falta un certificado digital
tributario, un RUC activo con facturación electrónica habilitada y un usuario
SOL secundario**. Son trámites del negocio, no del sistema, y fingirlos con
datos inventados produciría un módulo que pasa pruebas y falla el primer día
real.

Tampoco se activó el modo `api` por omisión. Queda configurado y apuntando al
entorno beta, pero encenderlo sin credenciales solo produciría errores de
transporte que ensuciarían la bitácora sin enseñar nada.

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
| # | Paso | Estado | Depende de |
|---|---|---|---|
| 1 | Reemplazar `buildXml` por un generador UBL 2.1 real | Pendiente | Solo de programación |
| 2 | Columnas `cdr_path`, `cdr_recibido_en`, `cdr_hash` | **Hecho** | — |
| 3 | Empaquetado ZIP + Base64 y armado del sobre SOAP | **Hecho** | — |
| 4 | Lectura y archivo del CDR con su nombre normalizado | **Hecho** | — |
| 5 | Firma digital del XML | Pendiente | **Certificado digital tributario del negocio** |
| 6 | Envío contra el entorno beta | Pendiente | **Usuario SOL secundario** |
| 7 | Envío contra producción | Pendiente | **RUC con facturación electrónica habilitada** |

Queda el paso 1, que es solo programación, y los pasos 5 a 7, que no dependen
del sistema. El estado «declarada» se decidió no añadirlo todavía: mientras
el XML no sea UBL firmado, una constancia simulada no justifica un estado
nuevo en el ciclo de vida de la venta.

---

## 7. Una reserva que conviene dejar escrita

Esta nota resume una sesión formativa y la contrasta con el código. **No es
asesoría tributaria.** Los plazos de envío, qué se entrega al cliente en
contingencia y qué régimen aplica a esta botica son cosas que debe confirmar
su contador, como ya quedó anotado en la sección 6 de
`docs/ARQUITECTURA-OFFLINE.md`.
