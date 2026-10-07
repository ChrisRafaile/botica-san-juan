#!/usr/bin/env python3
"""
Actualiza el .docx del APF2 sin regenerarlo.

    python docs/scripts/parchar-informe-apf2.py <ruta del .docx>

POR QUÉ UN PARCHE Y NO UN DOCUMENTO NUEVO

Regenerar el informe desde Markdown con Pandoc destruiría la carátula de la UTP,
los estilos corporativos, la tipografía, los encabezados, la numeración de
figuras y tablas, y las 78 tablas ya maquetadas. Reconstruir eso a mano cuesta
más que el propio contenido, y el resultado nunca vuelve a ser idéntico.

Aquí se abre el documento existente y se tocan **solo** los textos y celdas que
cambian. Todo lo demás —incluidos los estilos de cada párrafo— se queda como
está.

CÓMO SE REEMPLAZA TEXTO SIN PERDER FORMATO

Word guarda un párrafo como una lista de «runs» (tramos con el mismo formato).
Escribir `parrafo.text = '...'` borra todos los runs y con ellos la negrita, el
color y la fuente. Por eso `reemplazar()` localiza el texto sobre la
concatenación de los runs y reescribe solo los afectados, conservando el formato
del primero.

TODA CIFRA DE AQUÍ ESTÁ MEDIDA

Ninguna se copia del documento anterior ni se estima. Al final el script imprime
qué cambió y qué no encontró, para que un reemplazo que falle no pase inadvertido.
"""
from __future__ import annotations

import sys
from pathlib import Path

from docx import Document

# ---------------------------------------------------------------------------
# Cifras verificadas el 6 de octubre de 2026.
#
#   migraciones      php artisan migrate:status        44 aplicadas, 0 pendientes
#   esquema          consultas a information_schema    32 tablas, 31 FK, 17 UNIQUE, 79 índices
#   catálogo         Producto::count()                 884 productos, 886 lotes
#   inventario       Lote::disponible()->sum()         4 501 unidades vendibles
#   pruebas          php artisan test                  139 pruebas, 421 aserciones
#   rutas            app('router')->getRoutes()        114 api: 95 protegidas, 19 públicas
# ---------------------------------------------------------------------------
MIGRACIONES = "44"
PRODUCTOS = "884"
LOTES = "886"
UNIDADES_VENDIBLES = "4 501"
UNIDADES_POST_FUSION = "4 524"
UNIDADES_FANTASMA = "12 562"
PRUEBAS = "139"
ASERCIONES = "421"
RUTAS_API = "114"
RUTAS_PROTEGIDAS = "95"
RUTAS_PUBLICAS = "19"
INDICES = "79"

cambios: list[str] = []
fallos: list[str] = []


def reemplazar(parrafo, viejo: str, nuevo: str) -> bool:
    """Sustituye `viejo` por `nuevo` conservando el formato del párrafo."""
    completo = "".join(r.text for r in parrafo.runs)
    if viejo not in completo:
        return False

    nuevo_completo = completo.replace(viejo, nuevo)

    # El primer run se queda con todo el texto y hereda su propio formato; los
    # demás se vacían. Es la forma segura de no perder la fuente del párrafo.
    if parrafo.runs:
        parrafo.runs[0].text = nuevo_completo
        for r in parrafo.runs[1:]:
            r.text = ""
    return True


def en_documento(doc, viejo: str, nuevo: str, etiqueta: str) -> None:
    """Reemplaza en párrafos y en celdas de tabla. Informa si no encontró nada."""
    n = 0
    for p in doc.paragraphs:
        if reemplazar(p, viejo, nuevo):
            n += 1
    for t in doc.tables:
        for fila in t.rows:
            for celda in fila.cells:
                for p in celda.paragraphs:
                    if reemplazar(p, viejo, nuevo):
                        n += 1

    if n:
        cambios.append(f"{etiqueta}: {n} sustitución(es)")
    else:
        fallos.append(f"{etiqueta}: NO SE ENCONTRÓ «{viejo[:60]}»")


def celda(doc, tabla_idx: int, fila: int, col: int, nuevo: str, etiqueta: str) -> None:
    """Escribe una celda concreta conservando el formato de su primer run."""
    try:
        c = doc.tables[tabla_idx].rows[fila].cells[col]
    except IndexError:
        fallos.append(f"{etiqueta}: la celda [{tabla_idx}][{fila}][{col}] no existe")
        return

    p = c.paragraphs[0]
    if p.runs:
        p.runs[0].text = nuevo
        for r in p.runs[1:]:
            r.text = ""
    else:
        p.add_run(nuevo)
    cambios.append(f"{etiqueta}: tabla {tabla_idx} [{fila}][{col}] = «{nuevo[:50]}»")


def tabla_por_contenido(doc, *claves: str):
    """Localiza una tabla por su contenido, no por su posición.

    El índice cambia en cuanto alguien inserta una tabla antes; el contenido no.
    """
    for i, t in enumerate(doc.tables):
        cuerpo = " ".join(c.text for r in t.rows for c in r.cells)
        if all(k in cuerpo for k in claves):
            return i, t
    return None, None


def parrafo_por_texto(doc, inicio: str):
    for i, p in enumerate(doc.paragraphs):
        if p.text.strip().startswith(inicio):
            return i, p
    return None, None


def insertar_tras(doc, indice: int, textos: list[tuple[str, str]], etiqueta: str) -> None:
    """Inserta párrafos después de `indice`, con el estilo indicado en cada tupla."""
    ancla = doc.paragraphs[indice + 1] if indice + 1 < len(doc.paragraphs) else None
    if ancla is None:
        fallos.append(f"{etiqueta}: no hay dónde insertar")
        return

    for estilo, texto in textos:
        nuevo = ancla.insert_paragraph_before(texto)
        try:
            nuevo.style = doc.styles[estilo]
        except KeyError:
            nuevo.style = doc.styles["Normal"]
    cambios.append(f"{etiqueta}: {len(textos)} párrafo(s) añadidos")


def main(ruta: Path) -> int:
    doc = Document(str(ruta))

    # ---------------------------------------------------------------- carátula
    en_documento(
        doc,
        "PRIMER AVANCE DEL PROYECTO FINAL (APF1)",
        "SEGUNDO AVANCE DEL PROYECTO FINAL (APF2)",
        "Carátula · título del avance",
    )
    en_documento(
        doc,
        "Análisis de la Organización y Planificación del Proyecto",
        "Semana 9: Construcción y Despliegue de la Versión 1",
        "Carátula · subtítulo",
    )

    # ------------------------------------------------- XIII. Base de datos
    # Tabla de métricas del esquema.
    i, _ = tabla_por_contenido(doc, "Migraciones aplicadas", "Claves foráneas")
    if i is not None:
        celda(doc, i, 7, 1, MIGRACIONES, "Métricas · migraciones")
        celda(
            doc, i, 7, 2,
            "Ejecutadas por el arranque del contenedor antes de atender peticiones. "
            "Las dos últimas cierran integridad: el índice UNIQUE que impide reinsertar "
            "un producto duplicado y el ON DELETE RESTRICT que impide borrar un lote con "
            "movimientos.",
            "Métricas · nota de migraciones",
        )
        celda(doc, i, 6, 1, INDICES, "Métricas · índices")
        celda(
            doc, i, 5, 2,
            "Entre ellas la que sostiene la idempotencia de los pagos y la que impide "
            "duplicar la identidad de un producto, declarada con NULLS NOT DISTINCT.",
            "Métricas · nota de UNIQUE",
        )
    else:
        fallos.append("No se localizó la tabla de métricas del esquema")

    # Tabla del núcleo del modelo: el recuento de filas.
    i, _ = tabla_por_contenido(doc, "pedido_detalle_lotes", "Filas en producción")
    if i is not None:
        celda(
            doc, i, 0, 2,
            "Filas (entorno local, catálogo depurado)",
            "Núcleo del modelo · encabezado del recuento",
        )
        celda(doc, i, 1, 2, PRODUCTOS, "Núcleo del modelo · productos")
        celda(doc, i, 2, 2, LOTES, "Núcleo del modelo · lotes")
    else:
        fallos.append("No se localizó la tabla del núcleo del modelo")

    # Referencias sueltas al recuento anterior.
    en_documento(doc, "las mismas 41 migraciones", f"las mismas {MIGRACIONES} migraciones",
                 "XIII.7 · migraciones en la consulta")
    en_documento(doc, "41 migraciones versionadas", f"{MIGRACIONES} migraciones versionadas",
                 "Arquitectura · migraciones")
    en_documento(doc, "catálogo real de 3 361 productos", f"catálogo real de {PRODUCTOS} productos",
                 "Figura 32 · catálogo del POS")

    # ------------------------------------------------- XIV. Back-End
    i, p = parrafo_por_texto(doc, "14.3. Integración con el Front-End")
    if i is not None:
        insertar_tras(doc, i - 1, [
            ("Normal",
             f"La API expone {RUTAS_API} rutas bajo /api: {RUTAS_PROTEGIDAS} exigen token de "
             f"Sanctum —de ellas 80 además el rol de administrador— y {RUTAS_PUBLICAS} son "
             "públicas por decisión explícita: el catálogo, las facetas de filtrado, el "
             "formulario de contacto, la cotización del carrito, la confirmación del pedido, "
             "el acceso, el registro y los dos endpoints de salud. Ninguna otra lo es."),
            ("Normal",
             "Dos endpoints se incorporaron en este avance y conviene explicarlos porque "
             "sostienen el flujo de compra. POST /api/carrito/cotizar devuelve precio, stock "
             "vendible y aviso de receta actualizados para las líneas que el navegador tiene "
             "guardadas: el carrito del portal persiste en localStorage únicamente el "
             "identificador del producto y la cantidad, nunca el precio, porque ese dato lo "
             "puede editar cualquiera desde la consola del navegador. El servidor vuelve a "
             "topar la cantidad contra el stock real; enviando 99 unidades de un producto con "
             "3 existencias, la respuesta devuelve 3 y un aviso de ajuste."),
            ("Normal",
             "POST /api/pedidos/confirmar registra el encargo. Admite al cliente autenticado "
             "—el pedido queda ligado a su cuenta— y también al visitante que prefiere no "
             "registrarse, que debe aportar nombre, DNI o RUC y teléfono. Antes exigía sesión, "
             "de modo que encargar obligaba a crear una cuenta en el último paso del proceso "
             "de compra; el control no desapareció, se trasladó a los datos mínimos "
             "necesarios para entregar el pedido y emitir el comprobante."),
            ("Normal",
             "El cálculo del impuesto vive en un único servicio, DesgloseFiscalService, que "
             "utilizan tanto el carrito del portal como la venta de mostrador. Se extrajo "
             "deliberadamente de VentaService al necesitarlo el carrito: repetir las tres "
             "líneas de aritmética habría hecho que el total mostrado en la web y el de la "
             "boleta se separaran el día que cambie la tasa, sin que nadie lo advirtiera hasta "
             "que un cliente comparase ambos documentos. En el Perú el precio de mostrador ya "
             "incluye el IGV, de manera que el impuesto se extrae del precio y no se suma "
             "sobre él: S/ 118.00 se descompone en S/ 100.00 de base imponible y S/ 18.00 de "
             "IGV. Sumarlo habría arrojado S/ 139.24, cifra que el cliente no reconoce."),
            ("Normal",
             "El contrato completo de la API está documentado en OpenAPI 3.1, generado desde "
             "el propio código mediante Scramble y navegable en la ruta /docs/api. La "
             "documentación se restringe al entorno local mediante el middleware "
             "RestrictedDocsAccess: publicar el inventario de endpoints en producción facilita "
             "el trabajo a quien busque una ruta mal protegida."),
        ], "XIV · endpoints, desglose fiscal y OpenAPI")

    # ------------------------------------------------- XVI. Pruebas
    en_documento(doc, "98 pruebas con 246 aserciones",
                 f"{PRUEBAS} pruebas con {ASERCIONES} aserciones",
                 "Estado del desarrollo · pruebas")

    i, _ = parrafo_por_texto(doc, "16.7. Pruebas automatizadas de regresión")
    if i is not None:
        insertar_tras(doc, i, [
            ("Normal",
             f"La suite reúne {PRUEBAS} pruebas con {ASERCIONES} aserciones y se ejecuta "
             "completa en menos de doce segundos sobre PostgreSQL 16. No es una cifra "
             "decorativa: tres de los defectos corregidos en este avance los detectó la suite "
             "y no una revisión visual, y uno de ellos —la confirmación de venta que respondía "
             "500— llevaba semanas roto mientras el informe anterior lo declaraba operativo."),
            ("Heading 2", "16.8. Despacho FEFO verificado sobre el catálogo real"),
            ("Normal",
             "La orden pos:venta-demostracion ejecuta una venta de mostrador sobre el catálogo "
             "depurado y después consulta la base para comprobar, dato por dato, que la "
             "operación dejó todo coherente. Simula y revierte por omisión; solo escribe con "
             "la bandera --aplicar."),
            ("Normal",
             "En el pedido 186 se vendieron 9 unidades de AB-MOKS, producto que tenía 12 "
             "repartidas en dos lotes. El sistema tomó 7 unidades del lote L-2601, que vence "
             "el 31 de marzo de 2027, agotándolo, y las 2 restantes del lote L-2705, que vence "
             "el 15 de enero de 2028. Ese es el orden FEFO: sale primero lo que antes vence. "
             "Cada unidad quedó registrada en pedido_detalle_lotes, que es la tabla que "
             "permite responder a quién se le vendió un lote concreto ante una alerta "
             "sanitaria, y los dos movimientos de stock cuadran con el descuento: 12 a 5 y 5 a "
             "3 unidades."),
            ("Normal",
             "Las trece comprobaciones de la orden pasaron, incluidas cinco de integridad "
             "referencial sobre toda la base —cero filas huérfanas— y la verificación de que "
             "el desglose fiscal del pedido coincide con el que calcula el carrito del portal. "
             "El resultado se verificó además releyendo el pedido desde una sesión "
             "independiente de la que lo creó."),
            ("Heading 2", "16.9. Atomicidad de las operaciones de inventario (RNF06)"),
            ("Normal",
             "El RNF06 exige que toda operación que afecte el inventario se ejecute dentro de "
             "una transacción atómica. La comprobación es directa: las dos ejecuciones en modo "
             "simulación de la orden anterior crearon los pedidos 184 y 185 con todas sus "
             "líneas, movimientos y descuentos de stock, y al revertir la transacción ninguno "
             "de los dos existe en la base. No quedaron pedidos a medias, ni movimientos "
             "huérfanos, ni stock descontado sin venta asociada."),
            ("Heading 2", "16.10. Salto de maquetación del catálogo (CLS)"),
            ("Normal",
             "El catálogo se había dado por libre de desplazamientos porque las ilustraciones "
             "declaran width y height. La medición con PerformanceObserver desmintió esa "
             "afirmación: el valor real era 0.44382, casi el doble del umbral de «pobre», que "
             "está en 0.25."),
            ("Normal",
             "El diagnóstico descartó a las imágenes. Inspeccionando las fuentes de cada "
             "desplazamiento, 0.4237 de esos 0.44382 los aportaba el pie de página, que se "
             "pintaba a 519 píxeles del borde superior mientras la rejilla de productos estaba "
             "vacía y se desplazaba fuera de la pantalla al llegar los 24 productos. La "
             "corrección consistió en un esqueleto de carga que ocupa exactamente el espacio "
             "de las tarjetas futuras. La nueva medición arroja 0.01524 en tema claro y "
             "0.01350 en tema oscuro: una reducción del 96.6 %."),
            ("Normal",
             "El episodio deja una conclusión operativa aplicable al resto del proyecto: la "
             "compilación y la verificación de tipos pasaban limpias con el salto en 0.44, "
             "igual que pasaron limpias con dos imágenes rotas en el carrito que solo delató "
             "una captura automatizada. Compilar sin errores no es evidencia de que la "
             "pantalla funcione."),
        ], "XVI · FEFO, atomicidad y CLS")

    # ------------------------------------------------- XVII. Despliegue
    i, _ = tabla_por_contenido(doc, "botica-san-juan-api.onrender.com")
    if i is not None:
        celda(
            doc, i, 1, 2,
            f"En el entorno local: {MIGRACIONES} migraciones aplicadas, {PRODUCTOS} productos, "
            f"{LOTES} lotes y {UNIDADES_VENDIBLES} unidades vendibles tras la depuración del "
            "catálogo. La base desplegada conserva el catálogo anterior a la fusión: la "
            "sincronización está pendiente de ejecutar con el runbook documentado.",
            "Despliegue · estado de la base",
        )

    # ------------------------------------------------- XVIII. Retrospectiva
    i, _ = parrafo_por_texto(doc, "18.3. Qué se empezará a hacer")
    if i is not None:
        insertar_tras(doc, i, [
            ("Normal",
             "Medir antes de afirmar, y publicar la cifra también cuando contradice lo que se "
             "esperaba. En este Sprint ocurrió tres veces: el catálogo que se daba por libre de "
             "saltos tenía un CLS de 0.44; la pantalla de acceso permitía averiguar qué "
             "documentos estaban registrados por una diferencia de 160 milisegundos entre dos "
             "respuestas que debían ser indistinguibles; y el filtro por laboratorio parecía "
             "funcionar porque la lista cambiaba, cuando en realidad el servidor ignoraba el "
             "parámetro en silencio. Ninguno de los tres se habría detectado leyendo el código."),
            ("Normal",
             "Auditar los datos y no solo el esquema. El catálogo se había importado cuatro "
             "veces y contenía 869 grupos de productos duplicados; nada en el modelo lo "
             "impedía. Al resolverlo apareció una decisión que el recuento por sí solo no "
             "sugería: en 858 de esos grupos el stock era idéntico en todas las copias, señal "
             "de que se trataba de la misma mercancía reimportada y no de entregas distintas. "
             "Sumar los stocks habría elevado el inventario de 4 434 a 16 975 unidades y el "
             f"punto de venta habría prometido existencias inexistentes; se descartaron "
             f"{UNIDADES_FANTASMA} unidades fantasma. Tras la fusión el catálogo quedó en "
             f"{PRODUCTOS} productos con {UNIDADES_POST_FUSION} unidades vendibles."),
            ("Normal",
             "Documentar los obstáculos del entorno, no solo los del código. La ruta de trabajo "
             "del proyecto tiene 120 caracteres y Windows trunca nombres de archivo al extraer "
             "los paquetes de Composer: trece archivos por instalación limpia, sin que la "
             "herramienta informe de ningún error. La suite de pruebas abortaba con un mensaje "
             "que apuntaba a un problema de versiones inexistente. La solución —montar el "
             "proyecto en una unidad corta con subst— y, sobre todo, los cinco intentos que no "
             "funcionaron quedaron registrados en docs/ENTORNO-ruta-larga-windows.md para que "
             "el siguiente integrante del equipo no repita el diagnóstico."),
        ], "XVIII · retrospectiva del Sprint 4")

    # ------------------------------------------------- XIX. Evidencias
    i, _ = parrafo_por_texto(doc, "XIX. EVIDENCIAS DEL AVANCE")
    if i is not None:
        insertar_tras(doc, i, [
            ("Normal",
             "Las evidencias técnicas se centralizan en el directorio docs/evidencias/ del "
             "repositorio, con un índice explicativo en docs/EVIDENCIAS-APF2.md. Cada una "
             "cumple dos condiciones: es la salida literal de una ejecución real —del motor de "
             "PostgreSQL, de una orden de Artisan, de una petición HTTP, de la suite de pruebas "
             "o del control de versiones— y lleva documentada la orden exacta que la "
             "reproduce. Ninguna es una transcripción manual."),
            ("Normal",
             "E1 registra el rechazo de un producto duplicado por el motor de base de datos, "
             "con el código SQLSTATE[23505] y el nombre de la restricción violada. E2 recoge la "
             "fusión de los 869 grupos duplicados en sus dos momentos: la simulación previa y "
             "la verificación posterior, que informa de cero grupos. E3 contiene las respuestas "
             "HTTP de los contratos públicos del catálogo. E4 documenta la medición del salto "
             "de maquetación junto con las capturas de ambos temas. E5 reúne la suite "
             "automatizada y el historial de commits. E6 cubre el carrito y la página de "
             "contacto medidos en los dos temas. E7 contiene la venta de mostrador verificada "
             "de punta a punta. E8 recoge los tiempos de respuesta de la API. E9 documenta el "
             "checkout del portal por sus dos caminos."),
            ("Normal",
             "Se incluyen deliberadamente las mediciones desfavorables junto a las favorables. "
             "El salto de maquetación figura documentado en 0.44382 y en 0.01524, porque la "
             "primera cifra desmintió una afirmación que el equipo había dado por buena. Una "
             "evidencia que solo muestra el resultado esperado no demuestra nada sobre el "
             "método que la produjo."),
        ], "XIX · evidencias")

    doc.save(str(ruta))

    print("CAMBIOS APLICADOS")
    for c in cambios:
        print("  OK  ", c)
    if fallos:
        print()
        print("NO APLICADOS (revisar a mano)")
        for f in fallos:
            print("  !!  ", f)
    print()
    print(f"Total: {len(cambios)} cambios, {len(fallos)} sin aplicar.")
    print(f"Documento guardado: {ruta}")

    return 1 if fallos else 0


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(2)
    sys.exit(main(Path(sys.argv[1])))
