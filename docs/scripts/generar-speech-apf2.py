#!/usr/bin/env python3
"""Genera SPEECH_EXPOSICION_APF2.docx: guion de sustentación del APF2."""
from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Pt, RGBColor, Inches
from docx.enum.table import WD_TABLE_ALIGNMENT

AZUL = RGBColor(0x1F, 0x3C, 0x6E)
GRIS = RGBColor(0x5A, 0x5A, 0x5A)
VERDE = RGBColor(0x1B, 0x6E, 0x4B)
AMBAR = RGBColor(0x8A, 0x5A, 0x00)

doc = Document()

# --- Página y tipografía base ------------------------------------------------
sec = doc.sections[0]
sec.top_margin = sec.bottom_margin = Inches(0.7)
sec.left_margin = sec.right_margin = Inches(0.85)

normal = doc.styles["Normal"]
normal.font.name = "Calibri"
normal.font.size = Pt(12)          # cuerpo grande: se lee de un vistazo al exponer
normal.paragraph_format.space_after = Pt(8)
normal.paragraph_format.line_spacing = 1.25


def h(texto, nivel=1, color=AZUL, size=15, espacio_antes=14):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(espacio_antes)
    p.paragraph_format.space_after = Pt(4)
    r = p.add_run(texto)
    r.bold = True
    r.font.size = Pt(size)
    r.font.color.rgb = color
    return p


def decir(texto):
    """Lo que se dice en voz alta."""
    p = doc.add_paragraph(texto)
    p.paragraph_format.left_indent = Inches(0.12)
    return p


def cue(texto, color=VERDE):
    """Acotación de pantalla o de énfasis. No se lee en voz alta."""
    p = doc.add_paragraph()
    p.paragraph_format.left_indent = Inches(0.12)
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after = Pt(6)
    r = p.add_run(f"[ {texto} ]")
    r.italic = True
    r.bold = True
    r.font.size = Pt(10.5)
    r.font.color.rgb = color
    return p


def reloj(texto):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(10)
    p.paragraph_format.space_after = Pt(2)
    r = p.add_run(texto)
    r.bold = True
    r.font.size = Pt(9.5)
    r.font.color.rgb = GRIS
    return p


def nota(texto):
    p = doc.add_paragraph()
    p.paragraph_format.left_indent = Inches(0.12)
    p.paragraph_format.space_after = Pt(10)
    r = p.add_run(texto)
    r.font.size = Pt(10)
    r.font.color.rgb = AMBAR
    r.italic = True
    return p




def opcional(texto):
    """Párrafo que se puede saltar si vas justo de tiempo."""
    p = doc.add_paragraph()
    p.paragraph_format.left_indent = Inches(0.12)
    marca = p.add_run("[OPC] ")
    marca.bold = True
    marca.font.color.rgb = RGBColor(0x99, 0x99, 0x99)
    r = p.add_run(texto)
    r.font.color.rgb = RGBColor(0x55, 0x55, 0x55)
    return p

# =============================================================================
# PORTADA
# =============================================================================
t = doc.add_paragraph()
t.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = t.add_run("GUION DE SUSTENTACIÓN — APF2")
r.bold = True
r.font.size = Pt(20)
r.font.color.rgb = AZUL

st = doc.add_paragraph()
st.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = st.add_run("Sistema Web de Gestión y Control para la Botica San Juan\n"
               "Christopher Rafaile  ·  Curso Integrador II: Sistemas  ·  UTP")
r.font.size = Pt(11)
r.font.color.rgb = GRIS

d = doc.add_paragraph()
d.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = d.add_run("Uso personal. Exposición sin diapositivas: se comparte pantalla y se recorre el informe.")
r.italic = True
r.font.size = Pt(10)
r.font.color.rgb = GRIS

h("Reparto del tiempo  ·  objetivo 9 minutos", size=13, espacio_antes=16)

filas = [
    ("Bloque", "Duración", "Acumulado"),
    ("Apertura y resumen del APF1", "0:50", "0:50"),
    ("XII · Correcciones del APF1", "0:25", "1:15"),
    ("XIII · Base de datos", "1:30", "2:45"),
    ("XIV · Back-End e integración", "1:20", "4:05"),
    ("XV · Seguridad", "0:40", "4:45"),
    ("XVI · Pruebas", "1:40", "6:25"),
    ("XVII · Despliegue", "1:30", "7:55"),
    ("XVIII · Retrospectiva", "0:35", "8:30"),
    ("XIX y anexos · Evidencias", "0:30", "9:00"),
    ("Cierre", "0:30", "9:30"),
]

tabla = doc.add_table(rows=len(filas), cols=3)
tabla.style = "Light Grid Accent 1"
tabla.alignment = WD_TABLE_ALIGNMENT.CENTER
anchos = [Inches(3.6), Inches(1.1), Inches(1.1)]
for i, fila in enumerate(filas):
    for j, valor in enumerate(fila):
        celda = tabla.rows[i].cells[j]
        celda.width = anchos[j]
        celda.text = ""
        p = celda.paragraphs[0]
        run = p.add_run(valor)
        run.font.size = Pt(10)
        if i == 0:
            run.bold = True
        if j > 0:
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER

nota("DOS DURACIONES. Leído entero son unos 13 minutos. Los párrafos marcados con [OPC] y en "
     "gris son prescindibles: saltándolos quedan unos 10 minutos, que es la ventana "
     "habitual. Decide sobre la marcha según el tiempo que te den.\nLo que NO se salta: el pedido 186 (FEFO), la atomicidad, el CLS y el fallo del despliegue. "
     "Son lo que distingue este avance de una maqueta.")

# =============================================================================
# 1. APERTURA
# =============================================================================
h("1.  Apertura", size=16, espacio_antes=18)
reloj("Tiempo  0:00 — 0:50")
cue("Compartir pantalla. Informe abierto en la CARÁTULA.")

decir("Buenas noches, profesora. Mi nombre es Christopher Rafaile y voy a sustentar el segundo "
      "avance del Sistema Web de Gestión y Control para la Botica San Juan.")

decir("En el primer avance presenté el diagnóstico: la botica opera con un sistema heredado en "
      "Visual FoxPro desconectado del facturador de la SUNAT, con comprobantes cargados a mano y "
      "hasta catorce días de retraso. Propuse una arquitectura web desacoplada que integrara "
      "venta, inventario por lotes y facturación, planificada con Scrum en sprints de dos semanas.")

decir("Aquel avance era un plan. Este es un sistema funcionando y desplegado. Voy a mostrarles las "
      "comprobaciones, no solo el código.")

cue("Pausa de un segundo. Es la frase que enmarca toda la exposición.", AMBAR)

# =============================================================================
# 2. CAPÍTULO XII
# =============================================================================
h("2.  Capítulo XII — Correcciones del APF1", size=16)
reloj("Tiempo  0:50 — 1:15")
cue("Desplazar al Capítulo XII. Mostrar el cuadro de observaciones.")

decir("El capítulo doce es el de correcciones. No recibí observaciones formales, así que revisé el "
      "avance anterior contra el propio sistema y corregí lo que no se sostenía.")

opcional("Encontré cosas incómodas: el enlace de «Productos» llevaba a una pantalla en blanco porque "
      "la ruta nunca se declaró, el icono del carrito a «página no encontrada», y el pie del "
      "portal publicaba un RUC que contenía el documento del propietario. Todo corregido y "
      "documentado aquí.")

# =============================================================================
# 3. CAPÍTULO XIII
# =============================================================================
h("3.  Capítulo XIII — Diseño e implementación de la base de datos", size=16)
reloj("Tiempo  1:15 — 2:45")
cue("Capítulo XIII. Mostrar la tabla de métricas del esquema y la Figura 26 (modelo).")

decir("El capítulo trece es la base de datos: cuarenta y cinco migraciones versionadas sobre "
      "PostgreSQL 16, sin un solo cambio aplicado a mano. Eso permite levantar el entorno "
      "completo desde cero con un comando.")

cue("Señalar: 32 tablas, 31 claves foráneas, 17 UNIQUE, 79 índices.")

decir("Pero lo destacable aquí no es el modelo: es una auditoría de datos que no estaba prevista.")

decir("El catálogo tenía productos repetidos. Medido: se había importado cuatro veces. "
      "Ochocientos sesenta y nueve grupos duplicados sobre tres mil trescientos sesenta y uno.")

cue("ÉNFASIS. Bajar el ritmo: es la decisión técnica más interesante del avance.", AMBAR)

decir("Lo delicado fue el stock. La tentación era sumarlo. Lo medí antes de actuar: en "
      "ochocientos cincuenta y ocho de esos grupos el stock era idéntico en todas las copias. Era "
      "la misma mercancía reimportada, no cuatro entregas. Sumarla habría llevado el inventario "
      "de cuatro mil cuatrocientas a casi diecisiete mil unidades, y el punto de venta habría "
      "prometido existencias que no están en el anaquel.")

decir("Se descartaron doce mil quinientas sesenta y dos unidades fantasma. El catálogo quedó en "
      "ochocientos ochenta y cuatro productos reales.")

opcional("Para que no se repita, lo impide el motor: un índice único con NULLS NOT DISTINCT. Esa "
      "cláusula hace falta porque en SQL dos nulos no son iguales entre sí, y cientos de "
      "productos tienen la concentración en nulo: un índice corriente los habría dejado pasar.")

decir("La segunda decisión es la trazabilidad. La columna que liga cada movimiento con su lote "
      "estaba con ON DELETE SET NULL: borrar un lote no fallaba, la base ponía el campo en nulo y "
      "seguía. El movimiento decía «salieron siete unidades» sin decir de dónde. Ante una alerta "
      "sanitaria, «¿a quién se lo vendimos?» dejaba de tener respuesta. Lo cambié a RESTRICT.")

# =============================================================================
# 4. CAPÍTULO XIV
# =============================================================================
h("4.  Capítulo XIV — Back-End e integración con el Front-End", size=16)
reloj("Tiempo  2:45 — 4:05")
cue("Capítulo XIV. Figura 31 (recorrido de una venta) y Figura 32 (POS).")

decir("El capítulo catorce es el back-end. La API expone ciento catorce rutas: noventa y cinco "
      "exigen token y ochenta de esas, además, rol de administrador. Diecinueve son públicas por "
      "decisión explícita y ninguna otra lo es.")

opcional("El contrato está en OpenAPI 3.1, generado desde el propio código con Scramble, en "
      "/docs/api y restringido al entorno local: publicar el inventario de endpoints facilita el "
      "trabajo a quien busque una ruta mal protegida.")

decir("Dos piezas que quiero destacar.")

decir("La cotización del carrito. El carrito vive en el navegador para que un visitante pueda "
      "armarlo sin crear cuenta, pero allí solo se guarda qué producto y cuánto: ni precio ni "
      "stock. Esos los pone el servidor, porque lo del navegador lo edita cualquiera desde la "
      "consola. Pidiendo noventa y nueve unidades de un producto que tiene tres, devuelve tres; "
      "enviando un precio de un céntimo, cobra los veinticinco cincuenta reales.")

decir("Y el impuesto, centralizado en un único servicio que usan el carrito y el mostrador. Lo "
      "extraje a propósito: repetir la aritmética habría separado el total de la web del de la "
      "boleta el día que cambie la tasa, y nadie lo habría notado hasta que un cliente los "
      "comparase.")

cue("ÉNFASIS. Si preguntan algo del capítulo, lo más probable es esto.", AMBAR)

decir("La regla no es la intuitiva: en el Perú el precio ya lleva el IGV dentro, así que se "
      "extrae, no se suma. Ciento dieciocho soles son cien de base más dieciocho de impuesto.")

# =============================================================================
# 5. CAPÍTULO XV
# =============================================================================
h("5.  Capítulo XV — Controles de seguridad", size=16)
reloj("Tiempo  4:05 — 4:45")
cue("Capítulo XV. Figura 33 (401 / 403 / 200 por ruta).")

decir("El capítulo quince es seguridad. Tokens de Sanctum, contraseñas con bcrypt y nunca en una "
      "respuesta. La autorización se comprueba ruta por ruta: sin token cuatrocientos uno, con "
      "rol cliente sobre gestión cuatrocientos tres, con administrador doscientos.")

decir("Dos controles no estaban en el plan: aparecieron al medir.")

opcional("La pantalla de acceso permitía averiguar qué documentos estaban registrados: medí ciento "
      "sesenta milisegundos de diferencia entre un DNI existente y uno que no. Se corrigió con un "
      "hash señuelo que iguala los dos caminos. Y el portal escribía el token de sesión en la "
      "consola del navegador; se eliminó.")

opcional("Y una decisión de privacidad: producción contiene solo el catálogo. Los usuarios y pedidos "
      "reales no se copiaron porque llevan datos de personas. Por eso descarté una captura que "
      "mostraba los de un cliente real.")

# =============================================================================
# 6. CAPÍTULO XVI
# =============================================================================
h("6.  Capítulo XVI — Pruebas funcionales y no funcionales", size=16)
reloj("Tiempo  4:45 — 6:25")
cue("Capítulo XVI. A mano las Figuras 35 a 43 y los apartados 16.8, 16.9 y 16.10.")

decir("El capítulo dieciséis son las pruebas: ciento treinta y nueve automatizadas con "
      "cuatrocientas veintiuna aserciones, todas en verde, contra PostgreSQL —el mismo motor que "
      "producción—. Pero prefiero mostrarles tres resultados concretos antes que la cifra.")



cue("Apartado 16.8. El caso más demostrativo del avance.")

decir("El primero, el despacho por lotes. En el pedido ciento ochenta y seis se vendieron nueve "
      "unidades de un producto que tenía doce en dos lotes. El sistema tomó siete del lote que "
      "vence en marzo de dos mil veintisiete, agotándolo, y las dos restantes del de enero de dos "
      "mil veintiocho.")

cue("ÉNFASIS. Despacio: es FEFO sobre datos reales, no un ejemplo.", AMBAR)

decir("Ese es FEFO: sale primero lo que antes vence. Y cada unidad quedó ligada a su lote, que es "
      "lo que permite responder a quién se le vendió un lote ante un retiro del mercado.")

cue("Apartado 16.9.")

decir("El segundo es la atomicidad, el requisito no funcional seis. Esa orden corre por omisión "
      "en simulación: crea el pedido completo y después revierte. Los pedidos ciento ochenta y "
      "cuatro y ciento ochenta y cinco se crearon así y no existen en la base.")

cue("Apartado 16.10.")

decir("El tercero es una medición que me desmintió. Yo daba el catálogo por libre de saltos de "
      "maquetación. Medido, el valor real era cero coma cuarenta y cuatro: casi el doble del "
      "umbral de «pobre».")

decir("No eran las imágenes: era el pie de página, que se pintaba a mitad de pantalla con la "
      "rejilla vacía y se iba fuera al llegar los productos. Con un esqueleto de carga bajó a "
      "cero coma cero quince, un noventa y seis por ciento menos.")

decir("Lo presento con la cifra mala delante porque la conclusión es la útil: compilar sin "
      "errores no es evidencia de que la pantalla funcione.")

# =============================================================================
# 7. CAPÍTULO XVII
# =============================================================================
h("7.  Capítulo XVII — Despliegue a producción de la versión 1", size=16)
reloj("Tiempo  6:25 — 7:55")
cue("Capítulo XVII. Figura 52 (catálogo con 884 en producción) y Figura 53 (POS con IGV).")

decir("El diecisiete es el despliegue: portal en Vercel, API en Render y base en Neon.")

decir("La verificación de humo está automatizada: catorce comprobaciones correctas de catorce. El "
      "catálogo devuelve ochocientos ochenta y cuatro productos, que es lo que acredita que el "
      "catálogo depurado está en producción y no solo en mi equipo. Las rutas de gestión "
      "devuelven cuatrocientos uno sin token, y la mediana de respuesta es de trescientos sesenta "
      "y cinco milisegundos contra un umbral de dos segundos.")

cue("Apartado 17.6. Si solo cuentas una cosa de este capítulo, es esta.")

decir("Pero lo que de verdad enseñó este capítulo fue un fallo.")

decir("Durante nueve días ningún despliegue llegó a producción, y el síntoma externo era "
      "inexistente: el servicio respondía doscientos a todo. La construcción fallaba, Render "
      "mantenía vivo el contenedor anterior, y desde fuera lo único observable era que el código "
      "nuevo no aparecía nunca.")

cue("ÉNFASIS. Pausa breve antes de dar la causa.", AMBAR)

decir("La causa: una actualización de dependencias resuelta en un equipo con PHP ocho punto "
      "cuatro fijó paquetes que exigen esa versión, mientras el contenedor llevaba ocho punto "
      "tres. Y el manifiesto declaraba que admitía ocho punto dos, así que nada advertía hasta "
      "que la construcción abortaba.")

decir("Lo corregí en dos niveles: subir la imagen, y declarar en el manifiesto la plataforma de "
      "resolución para que Composer resuelva siempre contra la versión del despliegue.")

opcional("Descarté añadir una bandera que ignorase el requisito: no corrige nada, silencia el aviso "
      "que detectó el fallo y lo traslada al tiempo de ejecución.")

decir("La lección quedó escrita: un servicio que responde doscientos no demuestra que el "
      "despliegue haya funcionado. Lo demuestra consultar algo que solo exista en la versión "
      "nueva, y por eso el script de humo lo incluye.")

# =============================================================================
# 8. CAPÍTULO XVIII
# =============================================================================
h("8.  Capítulo XVIII — Retrospectiva del Sprint 4", size=16)
reloj("Tiempo  7:55 — 8:30")
cue("Capítulo XVIII.")

decir("En la retrospectiva recojo dos aprendizajes que no son de programación.")

decir("Auditar los datos y no solo el código: el modelo era correcto y aun así el catálogo tenía "
      "ochocientos sesenta y nueve grupos duplicados. Ninguna revisión del esquema lo habría "
      "encontrado; lo encontró contar.")

opcional("Y un obstáculo de entorno que costó un día: la ruta de este proyecto tiene ciento veinte "
      "caracteres y Windows trunca nombres al extraer los paquetes, sin ningún error. La suite "
      "abortaba señalando un problema de versiones inexistente. Documenté la solución y los cinco "
      "intentos que no funcionaron.")

# =============================================================================
# 9. CAPÍTULO XIX Y ANEXOS
# =============================================================================
h("9.  Capítulo XIX y anexos — Evidencias", size=16)
reloj("Tiempo  8:30 — 9:00")
cue("Capítulo XIX y después bajar a los Anexos C y D.")

decir("Las evidencias están en el repositorio con su índice explicativo: once artefactos que "
      "cumplen dos condiciones: son la salida literal de una ejecución real y llevan la orden "
      "exacta que los reproduce.")

opcional("Incluí las mediciones desfavorables junto a las favorables: el salto de maquetación figura "
      "en cero coma cuarenta y cuatro y en cero coma cero quince, y la verificación de humo "
      "conserva la ejecución en la que cuatro comprobaciones fallaban, que es la que destapó los "
      "nueve días de despliegue roto.")

cue("Mostrar Figuras C.6, C.7 y D.9 a D.11: tablero, inventario, conteo y facturación en vivo.")

decir("En los anexos están las capturas del sistema funcionando en la nube.")

# =============================================================================
# 10. CIERRE
# =============================================================================
h("10.  Cierre", size=16)
reloj("Tiempo  9:00 — 9:30")
cue("Dejar en pantalla la Figura 52.")

decir("Para cerrar. El primer avance entregaba un plan; este entrega un sistema que se puede abrir "
      "ahora mismo en un navegador: vende, descuenta el stock del lote correcto, calcula el "
      "impuesto como lo calcula una boleta y deja registro de todo.")

decir("Y lo que me parece más importante no son las ciento treinta y nueve pruebas ni las catorce "
      "comprobaciones de humo, sino que cada cifra del informe lleva debajo el comando que la "
      "reproduce. Incluidas las que me desmintieron.")

cue("ÉNFASIS. Frase de cierre. Sin prisa, mirando a cámara.", AMBAR)

decir("Muchas gracias, profesora. Quedo atento a sus preguntas.")

# =============================================================================
# ANEXO DEL GUION: posibles preguntas
# =============================================================================
doc.add_page_break()
h("Anexo del guion — respuestas rápidas a preguntas probables", size=16, espacio_antes=0)
nota("No se lee. Es para tenerlo a mano si la profesora pregunta.")

qa = [
    ("¿Por qué no se sumaron los stocks duplicados?",
     "Porque en 858 de los 869 grupos el stock era idéntico en todas las copias: misma mercancía "
     "reimportada, no entregas distintas. Sumarlos habría inflado el inventario de 4 434 a 16 975 "
     "unidades y el POS habría prometido existencias inexistentes."),
    ("¿Cómo se eligió qué fila conservar al fusionar?",
     "Por actividad, no por identificador. Si exactamente una fila del grupo tenía movimientos o "
     "ventas, esa se conserva, tenga el id que tenga. Se midió: en los 11 grupos con stock "
     "discordante la fila viva era siempre la de menos stock, y en uno de ellos no era la de id "
     "menor."),
    ("¿El sistema ya factura electrónicamente ante SUNAT?",
     "No, y el informe lo dice. El comprobante se emite dentro del sistema con serie y número "
     "correlativo —boleta B001 o factura F001 según el documento—, pero el envío está simulado a "
     "la espera del certificado digital y las credenciales del operador. Nace con estado «pendiente»."),
    ("¿Por qué el aviso de receta médica no aparece en todos los productos?",
     "Porque es un dato regulatorio que sale del catálogo DIGEMID, que hoy tiene cinco filas de "
     "demostración. La función está implementada y probada; se marcaron 34 productos de cinco "
     "familias de antibióticos y corticoides para poder demostrarla, y está declarado en el "
     "informe que es una marcación de demostración."),
    ("¿Qué queda pendiente?",
     "La sección 8 del informe técnico lo lista: cargar el catálogo DIGEMID real, la exoneración "
     "de IGV, la prueba de carga con 10 000 registros que no se ejecutó, y poblar las fechas de "
     "vencimiento del inventario heredado mediante el conteo por ciclos."),
    ("¿Cuántas migraciones y por qué tantas?",
     "Cuarenta y cinco. El esquema evoluciona solo por migraciones versionadas y reversibles; no "
     "hay ningún cambio aplicado a mano. Las tres últimas son de este avance: la fusión de "
     "duplicados, el índice único de identidad y la restricción de borrado de lotes."),
]

for pregunta, respuesta in qa:
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(8)
    p.paragraph_format.space_after = Pt(2)
    r = p.add_run(pregunta)
    r.bold = True
    r.font.size = Pt(11)
    r.font.color.rgb = AZUL
    pr = doc.add_paragraph(respuesta)
    pr.paragraph_format.left_indent = Inches(0.15)
    pr.paragraph_format.space_after = Pt(4)
    for run in pr.runs:
        run.font.size = Pt(10.5)

# --- Chuleta de cifras -------------------------------------------------------
h("Chuleta de cifras", size=14, espacio_antes=16)
nota("Por si se te va una en el momento.")

cifras = [
    ("Catálogo", "3 361 → 884 productos · 869 grupos duplicados · 12 562 unidades fantasma descartadas"),
    ("Base de datos", "45 migraciones · PostgreSQL 16 · 32 tablas · 31 claves foráneas · 79 índices"),
    ("API", "114 rutas: 95 protegidas (80 solo admin) · 19 públicas"),
    ("Pruebas", "139 pruebas · 421 aserciones · todas en verde"),
    ("FEFO", "Pedido 186: 9 unidades = 7 del lote que vence 31/03/2027 + 2 del de 15/01/2028"),
    ("Atomicidad", "Pedidos 184 y 185 creados en simulación: no existen tras revertir"),
    ("CLS", "0.44382 → 0.01524 (−96.6 %) · el footer aportaba 0.4237"),
    ("Smoke test", "14 de 14 · mediana de respuesta 365 ms (umbral 2 000 ms)"),
    ("IGV", "S/ 118.00 = S/ 100.00 de base + S/ 18.00 de impuesto (se extrae, no se suma)"),
    ("Repositorio", "100 commits en main · Conventional Commits · 7 ramas · 4 etiquetas"),
]

t2 = doc.add_table(rows=len(cifras), cols=2)
t2.style = "Light List Accent 1"
for i, (clave, valor) in enumerate(cifras):
    c0, c1 = t2.rows[i].cells
    c0.width = Inches(1.5)
    c1.width = Inches(5.3)
    c0.text = ""
    c1.text = ""
    r0 = c0.paragraphs[0].add_run(clave)
    r0.bold = True
    r0.font.size = Pt(9.5)
    r1 = c1.paragraphs[0].add_run(valor)
    r1.font.size = Pt(9.5)

doc.save("SPEECH_EXPOSICION_APF2.docx")
print("SPEECH_EXPOSICION_APF2.docx generado")
