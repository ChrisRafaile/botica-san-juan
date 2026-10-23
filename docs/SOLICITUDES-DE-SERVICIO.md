# Catálogo de solicitudes de servicio · Botica San Juan

Procedimiento básico de gestión de solicitudes de servicio del sistema, exigido
por el APF3 (punto 23 del trabajo final) y por la práctica de la sesión S10/S19.

**Seguimiento:** [GitHub Projects · Botica San Juan · Solicitudes de servicio (APF3)](https://github.com/users/ChrisRafaile/projects/3)
**Registro formal:** cada solicitud es una issue del repositorio; la issue **es**
el ticket. SOL-001 a SOL-006 corresponden a las issues **#14 a #19**.

El tablero lleva cinco campos propios —Tipo, Prioridad, Estado solicitud,
Aprobación y Fecha— además del estado nativo de GitHub. Conviene saber que
**no son lo mismo**: el estado nativo pasa a *Done* en cuanto la issue se
cierra, de modo que SOL-004 figura ahí como *Done* aunque fue **rechazada**.
El campo que manda para este procedimiento es **Estado solicitud**, porque una
solicitud denegada también se cierra, y cerrarla no significa haberla
concedido.

---

## 1. Qué es y qué no es una solicitud

Las tres cosas llegan al mismo sitio y se tratan distinto. Confundirlas es el
error habitual: una caída atendida como «petición» espera turno, y una petición
tratada como incidente dispara una urgencia que no existe.

| Solicitud | Incidente | Cambio |
|---|---|---|
| «Necesito algo» | «Algo que debería funcionar está fallando» | «Necesito modificar el servicio» |
| Crear una cuenta, pedir acceso, consultar cómo se hace algo | El catálogo no carga, la venta responde 500 | Un módulo nuevo, otra pasarela de pago |

Ejemplo real de este proyecto: el despliegue roto nueve días (octubre) **no fue
una solicitud**, fue un incidente. La actualización de PHP a 8.4.27 del 21 de
octubre **no fue una solicitud**, fue un cambio. Ninguno de los dos entra en
este catálogo, y por eso se registran aparte.

## 2. Ciclo de atención

```
Registrar → Clasificar → Validar → Autorizar → Atender → Verificar → Cerrar
```

Con un estado alternativo, **Rechazada**, cuando la solicitud no corresponde,
carece de autorización o no puede atenderse como solicitud estándar. El paso de
autorización no se omite nunca: si la aprobación no corresponde, se rechaza o
se escala. SOL-004 es exactamente ese caso.

**Estados:** Registrada · En revisión · En atención · Resuelta · Cerrada · Rechazada

## 3. Principio de mínimo privilegio

Cada usuario recibe solo los permisos que su trabajo necesita. El sistema ya lo
implementa en los roles, y las solicitudes de acceso se resuelven contra esta
tabla, no contra la insistencia de quien pide.

| Función | Administrador | Vendedor | Cliente |
|---|---|---|---|
| Consultar el catálogo público | Sí | Sí | Sí |
| Registrar una venta en mostrador | Sí | Sí | No |
| Ver el historial de sus propios pedidos | Sí | Sí | Sí |
| Ver pedidos de otros usuarios | Sí | No | **No** |
| Reportes de ventas y tablero | Sí | No | No |
| Gestionar usuarios | Sí | No | No |
| Respaldo y restauración | Sí | No | No |

## 4. Sobre los solicitantes

Se identifican **por rol y turno, no por nombre y apellido**. La botica es un
negocio real y sus trabajadoras son personas reales: un trabajo académico que
circula por correo y se sube a un repositorio no es sitio para sus datos
personales. El rol es suficiente para saber quién pide, qué puede pedir y quién
autoriza, que es lo que el procedimiento necesita.

---

## 5. Catálogo

| Código | Tipo | Descripción | Prioridad | Aprobación | Estado |
|---|---|---|---|---|---|
| SOL-001 | Información | Cómo generar el reporte de ventas del mes | Baja | No | Cerrada |
| SOL-002 | Información | El catálogo muestra menos unidades que el anaquel | Media | No | Cerrada |
| SOL-003 | Acceso | Alta de cuenta para vendedor nuevo | Media | Sí | Cerrada |
| SOL-004 | Acceso | Cliente pide acceso al módulo de reportes | Baja | Sí | **Rechazada** |
| SOL-005 | Estándar | Restablecer contraseña de vendedor | Alta | Sí | Cerrada |
| SOL-006 | Estándar | Respaldo de la base antes de una carga masiva | Alta | Sí | Cerrada |

---

### SOL-001 · Cómo generar el reporte de ventas del mes

| Campo | Valor |
|---|---|
| **Código** | SOL-001 |
| **Fecha** | 14/10/2026 |
| **Solicitante** | Vendedor de mostrador · turno mañana |
| **Tipo** | Solicitud de información |
| **Descripción** | Pide saber cómo obtener el total vendido del mes para cuadrarlo con el cuaderno de caja. |
| **Prioridad** | Baja |
| **Responsable de atención** | Administrador del sistema |
| **¿Requiere aprobación?** | No. Es orientación sobre una función que su rol ya tiene. |
| **Aprobador** | No aplica |
| **Solución brindada** | Se indica la ruta `/admin/reportes`, cómo fijar el rango de fechas y dónde queda el total. Se aclara que el reporte toma la fecha de la venta y no la de emisión del comprobante, que es lo que descuadraba su conteo. |
| **Evidencia** | Issue cerrada con la respuesta + captura de la pantalla de reportes con el rango aplicado. |
| **Estado final** | Cerrada |

### SOL-002 · El catálogo muestra menos unidades que el anaquel

| Campo | Valor |
|---|---|
| **Código** | SOL-002 |
| **Fecha** | 16/10/2026 |
| **Solicitante** | Propietario de la botica |
| **Tipo** | Solicitud de información |
| **Descripción** | Observa que un producto con existencias físicas aparece con menos unidades disponibles en el portal y pregunta si el sistema está equivocado. |
| **Prioridad** | Media |
| **Responsable de atención** | Administrador del sistema |
| **¿Requiere aprobación?** | No |
| **Aprobador** | No aplica |
| **Solución brindada** | No es un error: el sistema distingue **stock contable** (lo que hay en el anaquel) de **stock vendible** (lo que se puede prometer a un cliente). Los lotes vencidos cuentan en el primero y se excluyen del segundo, porque están físicamente ahí pero no se pueden dispensar. Se identificó el lote vencido responsable de la diferencia y se programó su retiro. |
| **Evidencia** | Respuesta documentada en la issue + consulta sobre `lotes` mostrando el lote vencido y su fecha. |
| **Estado final** | Cerrada |

### SOL-003 · Alta de cuenta para vendedor nuevo

| Campo | Valor |
|---|---|
| **Código** | SOL-003 |
| **Fecha** | 17/10/2026 |
| **Solicitante** | Propietario de la botica |
| **Tipo** | Solicitud de acceso |
| **Descripción** | Ingresa un vendedor al turno tarde y necesita cuenta para registrar ventas en el punto de venta. |
| **Prioridad** | Media |
| **Responsable de atención** | Administrador del sistema |
| **¿Requiere aprobación?** | Sí. Toda alta de cuenta con acceso al punto de venta la autoriza el propietario. |
| **Aprobador** | Propietario de la botica |
| **Solución brindada** | Cuenta creada con rol **vendedor**, no administrador: puede registrar ventas y consultar inventario, y no accede a reportes, gestión de usuarios ni respaldos. La contraseña inicial se entregó en persona, de un solo uso, con cambio obligatorio en el primer ingreso. |
| **Evidencia** | Issue cerrada + registro de la cuenta en la tabla de usuarios con su rol. **La contraseña no se registra en el ticket ni en ningún archivo del repositorio.** |
| **Estado final** | Cerrada |

### SOL-004 · Cliente pide acceso al módulo de reportes

| Campo | Valor |
|---|---|
| **Código** | SOL-004 |
| **Fecha** | 18/10/2026 |
| **Solicitante** | Cliente registrado del portal |
| **Tipo** | Solicitud de acceso |
| **Descripción** | Pide acceso a los reportes de ventas «para comparar precios históricos». |
| **Prioridad** | Baja |
| **Responsable de atención** | Administrador del sistema |
| **¿Requiere aprobación?** | Sí |
| **Aprobador** | Propietario de la botica · **deniega** |
| **Solución brindada** | **Rechazada por mínimo privilegio.** Los reportes contienen márgenes, costos de compra y datos de otros clientes; el rol cliente no los contempla y conceder el permiso expondría información del negocio y de terceros. Se ofrece la alternativa que sí corresponde: su historial de pedidos y sus comprobantes, accesibles desde su propio panel. |
| **Evidencia** | La API ya impide el acceso por diseño: un token de rol cliente contra las rutas de gestión responde 401/403, y hay pruebas automatizadas que fijan esa regla para que no se afloje por descuido. |
| **Estado final** | **Rechazada** |

### SOL-005 · Restablecer contraseña de vendedor

| Campo | Valor |
|---|---|
| **Código** | SOL-005 |
| **Fecha** | 19/10/2026 |
| **Solicitante** | Vendedor de mostrador · turno tarde |
| **Tipo** | Solicitud estándar (modelo «Restablecer contraseña») |
| **Descripción** | No puede iniciar sesión al comenzar su turno y el punto de venta queda sin atender. |
| **Prioridad** | Alta · bloquea la atención en mostrador |
| **Responsable de atención** | Administrador del sistema |
| **¿Requiere aprobación?** | Sí. Se verifica la identidad de quien pide antes de restablecer nada: un restablecimiento concedido por teléfono a quien dice ser otra persona es un acceso regalado. |
| **Aprobador** | Propietario de la botica |
| **Tiempo objetivo** | 2 horas hábiles |
| **Solución brindada** | Identidad verificada en persona, contraseña restablecida con valor temporal y cambio obligatorio en el primer ingreso. |
| **Evidencia** | Issue cerrada + registro del acceso posterior de la cuenta. **No se registra el valor de la contraseña en ninguna parte.** |
| **Estado final** | Cerrada |

### SOL-006 · Respaldo de la base antes de una carga masiva

| Campo | Valor |
|---|---|
| **Código** | SOL-006 |
| **Fecha** | 20/10/2026 |
| **Solicitante** | Propietario de la botica |
| **Tipo** | Solicitud estándar (modelo «Solicitar backup») |
| **Descripción** | Antes de cargar una lista nueva de productos por carga masiva, pide un respaldo para poder volver atrás si la carga sale mal. |
| **Prioridad** | Alta |
| **Responsable de atención** | Administrador del sistema |
| **¿Requiere aprobación?** | Sí |
| **Aprobador** | Propietario de la botica |
| **Solución brindada** | Respaldo ejecutado **antes** de la carga y verificado por conteo de filas de las tablas principales. Es el mismo procedimiento que ya se aplicó antes de la fusión de productos duplicados, donde el respaldo previo fue lo que permitió autorizar una operación destructiva sobre 869 grupos de productos sin asumir el riesgo a ciegas. |
| **Evidencia** | Archivo de respaldo con su fecha + registro del comando ejecutado y del conteo de filas antes y después. |
| **Estado final** | Cerrada |

---

## 6. Modelos de solicitud estándar

Cuando una solicitud se repite, se atiende siempre igual. Eso es lo que la
convierte en estándar y lo que permite medir si se está cumpliendo.

| Modelo | Datos requeridos | Autoriza | Atiende | Tiempo objetivo | Evidencia |
|---|---|---|---|---|---|
| Crear cuenta de vendedor | Nombre, documento, turno, rol | Propietario | Administrador | 8 h hábiles | Ticket + registro de usuario |
| Restablecer contraseña | Identidad verificada del solicitante | Propietario | Administrador | 2 h hábiles | Ticket + registro de acceso |
| Solicitar respaldo | Motivo y fecha objetivo | Propietario | Administrador | 4 h hábiles | Archivo de respaldo + conteos |
| Generar reporte de ventas | Rango de fechas | No requiere | Administrador | 1 h hábil | Reporte exportado |

## 7. Qué se mide

| Indicador | Cómo se obtiene |
|---|---|
| Solicitudes atendidas dentro del tiempo objetivo | Fecha de cierre menos fecha de registro en la issue |
| Solicitudes rechazadas y su motivo | Estado *Rechazada* en el tablero |
| Reparto por tipo | Campo **Tipo** del tablero |
| Solicitudes sin evidencia adjunta | Revisión de cierre: una solicitud sin evidencia no se cierra |
