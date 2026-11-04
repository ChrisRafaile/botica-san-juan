#!/usr/bin/env python3
"""Genera SPEECH_EXPOSICION_APF3.docx: recorrido página a página del informe.

NO toca el informe APF3. Solo lo lee para verificar la paginación y escribe un
documento nuevo.

La paginación se obtuvo renderizando el .docx a PDF y se contrastó con el
índice del propio informe: XXII en la 100, Anexo C en la 139 y Anexo D en la
142 coinciden en ambos. Son 148 páginas.
"""
from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Pt, RGBColor, Inches
from docx.enum.table import WD_TABLE_ALIGNMENT

AZUL = RGBColor(0x1F, 0x3C, 0x6E)
GRIS = RGBColor(0x5A, 0x5A, 0x5A)
VERDE = RGBColor(0x1B, 0x6E, 0x4B)
AMBAR = RGBColor(0x8A, 0x5A, 0x00)

"""
EL RELOJ SE CALCULA, NO SE ESCRIBE A MANO
-----------------------------------------------------------------------------
La primera versión de este guion llevaba los tiempos puestos a ojo y decía
«15 minutos». Medido, el texto hablado eran 23. Un guion que miente sobre su
propia duración es peor que uno sin tiempos: te hace confiar en que llegas.

Así que el documento se construye en dos pasadas. La primera acumula los
bloques; la segunda cuenta las palabras habladas de cada uno y escribe el
tramo de reloj que le corresponde. Si mañana se añade o se recorta un
párrafo, los tiempos se recalculan solos.

El ritmo de referencia es 135 palabras por minuto. No son 150: en una
sustentación se desplaza el documento, se señala y se respira.
"""

PALABRAS_POR_MINUTO = 135

# Cola de operaciones: se rellena al describir el guion y se vuelca al final.
_guion = []

doc = Document()

sec = doc.sections[0]
sec.top_margin = sec.bottom_margin = Inches(0.7)
sec.left_margin = sec.right_margin = Inches(0.85)

normal = doc.styles["Normal"]
normal.font.name = "Calibri"
normal.font.size = Pt(12)
normal.paragraph_format.space_after = Pt(8)
normal.paragraph_format.line_spacing = 1.25


def h(texto, nivel=1, color=AZUL, size=15, espacio_antes=14):
    _guion.append(("h", (texto, color, size, espacio_antes)))


def decir(texto):
    _guion.append(("decir", texto))


def opcional(texto):
    _guion.append(("opcional", texto))


def cue(texto, color=VERDE):
    _guion.append(("cue", (texto, color)))


def reloj():
    """Marcador de tiempo. El tramo lo calcula la segunda pasada."""
    _guion.append(("reloj", None))


def nota(texto):
    _guion.append(("nota", texto))


def tabla(filas, estilo="Light Grid Accent 1", anchos=(1.5, 5.0), size=10):
    _guion.append(("tabla", (filas, estilo, anchos, size)))


def salto():
    _guion.append(("salto", None))


# --------------------------------------------------------------- renderizado

def _h(texto, color=AZUL, size=15, espacio_antes=14):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(espacio_antes)
    p.paragraph_format.space_after = Pt(4)
    p.paragraph_format.keep_with_next = True
    r = p.add_run(texto)
    r.bold = True
    r.font.size = Pt(size)
    r.font.color.rgb = color
    return p


def _decir(texto):
    """Lo que se dice en voz alta."""
    p = doc.add_paragraph(texto)
    p.paragraph_format.left_indent = Inches(0.12)
    return p


def _opcional(texto):
    """Párrafo prescindible si vas justo de tiempo."""
    p = doc.add_paragraph()
    p.paragraph_format.left_indent = Inches(0.12)
    marca = p.add_run("[OPC] ")
    marca.bold = True
    marca.font.color.rgb = RGBColor(0x99, 0x99, 0x99)
    r = p.add_run(texto)
    r.font.color.rgb = RGBColor(0x55, 0x55, 0x55)
    return p


def _cue(texto, color=VERDE):
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


def _reloj(texto):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(10)
    p.paragraph_format.space_after = Pt(2)
    r = p.add_run(texto)
    r.bold = True
    r.font.size = Pt(9.5)
    r.font.color.rgb = GRIS
    return p


def _nota(texto):
    p = doc.add_paragraph()
    p.paragraph_format.left_indent = Inches(0.12)
    p.paragraph_format.space_after = Pt(10)
    r = p.add_run(texto)
    r.font.size = Pt(10)
    r.font.color.rgb = AMBAR
    r.italic = True
    return p


def _tabla(filas, estilo="Light Grid Accent 1", anchos=(1.5, 5.0), size=10):
    t = doc.add_table(rows=len(filas), cols=len(filas[0]))
    t.style = estilo
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, fila in enumerate(filas):
        for j, valor in enumerate(fila):
            celda = t.rows[i].cells[j]
            celda.width = Inches(anchos[j] if j < len(anchos) else 2.0)
            celda.text = ""
            run = celda.paragraphs[0].add_run(valor)
            run.font.size = Pt(size)
            if i == 0 and estilo.startswith("Light Grid"):
                run.bold = True
    return t


# =============================================================================
# PORTADA
# =============================================================================
t = doc.add_paragraph()
t.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = t.add_run("GUION DE SUSTENTACIÓN — APF3")
r.bold = True
r.font.size = Pt(20)
r.font.color.rgb = AZUL

st = doc.add_paragraph()
st.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = st.add_run("Resumen del APF2 y recorrido página a página del APF3\n"
               "Christopher Rafaile  ·  Curso Integrador II: Sistemas  ·  UTP")
r.font.size = Pt(11)
r.font.color.rgb = GRIS

d = doc.add_paragraph()
d.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = d.add_run("Uso personal. Exposición sin diapositivas: se comparte pantalla y se desplaza el informe.")
r.italic = True
r.font.size = Pt(10)
r.font.color.rgb = GRIS

_nota("PAGINACIÓN VERIFICADA. El informe tiene 148 páginas. Los números de este guion se "
     "contrastaron contra el índice del propio documento y coinciden en las tres anclas que se "
     "comprobaron: el capítulo XXII abre en la 100, el Anexo C en la 139 y el Anexo D en la 142. "
     "Si al actualizar el índice con F9 algo se desplaza una página, el título de cada bloque te "
     "sitúa igual.")

_h("Cómo usar este guion", size=13, espacio_antes=14)

_tabla([
    ("Marca", "Significado"),
    ("Página NN", "Dónde debes estar al decir lo que sigue."),
    ("[ ... ]", "Acotación: qué señalar o dónde hacer énfasis. No se lee."),
    ("[OPC]", "Párrafo prescindible. Sáltalo si vas justo de tiempo."),
    ("★", "No se salta. Es lo que distingue este avance de un informe de trámite."),
], anchos=(1.3, 5.0))

_nota_duracion = _nota("DURACIÓN REAL. (se calcula al generar)")

_h("Rutas según el tiempo que te den", size=13, espacio_antes=14)

_tabla([
    ("Ventana", "Qué haces"),
    ("25 min", "El guion entero, incluidos los [OPC]. Es el recorrido completo."),
    ("18 min", "Saltas todos los [OPC]. No pierdes ningún argumento, solo matices."),
    ("12 min", "Saltas los [OPC] y además comprimes tres bloques en una frase cada uno: "
               "las seis fichas de solicitudes (pp. 89-99), los expedientes de cambios "
               "(pp. 109-111) y los cuatro anexos (pp. 120-148)."),
    ("7 min", "Solo los bloques marcados con ★: la tabla de observaciones abiertas (p. 80), "
              "la solicitud rechazada (p. 86), el cambio de emergencia (p. 101), las 44 "
              "integraciones detenidas (p. 104), la columna de Pull Request vacía (p. 107) "
              "y lo que no se ha medido (p. 111). Más apertura y cierre."),
], anchos=(1.2, 5.1), size=9.5)

_nota("LO QUE NO DEBES PROMETER. Tres cosas del informe no están hechas, y están declaradas como "
     "no hechas: no hay prueba de carga, la facturación ante la SUNAT sigue simulada a la espera "
     "del certificado, y cuatro observaciones del APF2 siguen abiertas. Si te preguntan, "
     "respóndelo así; el informe ya lo dice y queda peor desmentirlo que asumirlo.")

# =============================================================================
# APERTURA
# =============================================================================
salto()
h("Apertura  ·  antes de desplazar nada", size=16, espacio_antes=0)
reloj()
cue("Compartir pantalla con el informe abierto en la CARÁTULA.")

decir("Buenas noches, profesora. Mi nombre es Christopher Rafaile y voy a sustentar el tercer "
      "avance del Sistema Web de Gestión y Control para la Botica San Juan.")

decir("Voy a hacerlo en dos tiempos. Primero un resumen corto de dónde quedó el segundo avance, "
      "para situar el punto de partida. Y después el recorrido por los capítulos nuevos, que son "
      "del veinte al veintisiete, más los cuatro anexos.")

# =============================================================================
# RESUMEN DEL APF2
# =============================================================================
h("Resumen del APF2  ·  de dónde venimos", size=16)
reloj()
cue("En pantalla: el ÍNDICE. No hace falta desplazar todavía.")

decir("En el primer avance presenté el diagnóstico. La botica opera con un sistema heredado en "
      "Visual FoxPro que no se comunica con el facturador de la SUNAT: cada comprobante se digita "
      "dos veces y el envío llegaba a retrasarse hasta catorce días.")

decir("El segundo avance ya no era un plan: era el sistema construido y desplegado. Lo resumo en "
      "cinco puntos.")

decir("Uno. La base de datos quedó implementada sobre PostgreSQL 16, con el esquema construido "
      "solo por migraciones versionadas, sin un cambio aplicado a mano.")

decir("Dos. El back-end y el front-end quedaron integrados: el punto de venta opera contra la API "
      "real, con el cálculo del IGV centralizado en un único sitio.")

decir("Tres. Se documentaron los controles de seguridad y se verificaron contra la API en "
      "ejecución: cada ruta de gestión responde 401 sin credenciales, 403 con rol cliente y 200 "
      "con administrador.")

decir("Cuatro. Se ejecutaron pruebas funcionales y no funcionales sobre el sistema real, no sobre "
      "un simulacro, y se desplegó la versión 1 a producción.")

decir("Y cinco, el hallazgo que más me enseñó: al verificar el despliegue descubrí que llevaba "
      "nueve días roto sin que nadie lo notara. El servicio respondía correctamente porque la "
      "plataforma mantenía en ejecución el contenedor anterior, mientras la construcción de la "
      "imagen nueva fallaba en silencio.")

cue("★ Esa frase conecta los dos avances. Dila despacio.")

decir("Ese hallazgo es el que explica la mitad de este tercer avance. Si un despliegue puede estar "
      "roto nueve días sin avisar, el problema no se arregla con más código: se arregla con "
      "procedimiento. Y eso es lo que traen los capítulos nuevos.")

# =============================================================================
# MAPA DEL AVANCE
# =============================================================================
h("Qué trae este avance  ·  mapa antes de recorrerlo", size=16)
reloj()
cue("En pantalla: el índice, señalando del capítulo XX hacia abajo.")

decir("Los capítulos nuevos son ocho y se agrupan en tres bloques.")

decir("El primero es de corrección: el capítulo veinte recoge las observaciones del avance "
      "anterior, las subsanadas y las que siguen abiertas.")

decir("El segundo es de gestión del servicio: solicitudes de servicio, procedimiento de control de "
      "cambios, y capacidad y rendimiento. Son los capítulos veintiuno, veintidós y veintitrés.")

decir("Y el tercero es de cierre: despliegue de la versión final, retrospectiva del Sprint 6 y "
      "conclusiones.")

opcional("Los capítulos del uno al diecinueve son los que ya sustenté en el avance anterior. Están "
         "en el documento porque el informe es acumulativo, y los recorro solo de pasada.")

cue("Desplazar con ritmo desde el índice hasta la página 79. No te detengas en el camino.")

h("Mapa de páginas  ·  por si te piden saltar a un capítulo", size=13, espacio_antes=16)
nota("No se lee. Es para localizar cualquier capítulo en un segundo si la profesora pide verlo.")

tabla([
    ("Pág.", "Capítulo"),
    ("7", "I. Análisis de la organización"),
    ("11", "II. Planificación del proyecto"),
    ("14", "III. Historias de usuario y Product Backlog"),
    ("21", "IV. Sprint Backlog"),
    ("24", "V. Cronograma  ·  VI. Herramientas e infraestructura"),
    ("29", "VII. Arquitectura de la solución"),
    ("32", "VIII. Diseño y prototipos"),
    ("34", "IX. Avance del sistema en desarrollo"),
    ("39", "X. Gestión de riesgos"),
    ("43", "XI. Acuerdo de nivel de servicio"),
    ("44", "XII. Correcciones del APF1"),
    ("45", "XIII. Diseño e implementación de la base de datos"),
    ("53", "XIV. Back-End e integración con el Front-End"),
    ("55", "XV. Controles de seguridad"),
    ("58", "XVI. Pruebas funcionales y no funcionales"),
    ("66", "XVII. Despliegue a producción de la versión 1"),
    ("74", "XVIII. Retrospectiva del Sprint 4"),
    ("77", "XIX. Evidencias del avance"),
    ("79", "★ XX. Correcciones del APF2"),
    ("85", "★ XXI. Gestión de solicitudes de servicio"),
    ("100", "★ XXII. Procedimiento de control de cambios"),
    ("111", "★ XXIII. Gestión de capacidad y rendimiento"),
    ("112", "★ XXIV. Despliegue de la versión final"),
    ("116", "★ XXV. Retrospectiva del Sprint 6"),
    ("118", "★ XXVI. Conclusiones"),
    ("119", "XXVII. Referencias"),
    ("120", "Anexo A. Evidencia fotográfica del sistema actual"),
    ("136", "Anexo B. Evidencia del código fuente"),
    ("139", "Anexo C. Salidas de verificación"),
    ("142", "Anexo D. Capturas del sistema en funcionamiento"),
], anchos=(0.7, 5.6), size=9.5)

# =============================================================================
# CAPÍTULO XX
# =============================================================================
salto()
h("Página 79  ·  XX. Correcciones del APF2", size=15, espacio_antes=0)
reloj()
cue("En pantalla: Tabla 63, observaciones subsanadas.")

decir("Empezamos por el capítulo de correcciones. La tabla recoge cinco observaciones del avance "
      "anterior con la corrección aplicada y el commit donde vive.")

decir("La primera es la que más pesa. El intérprete PHP del servidor quedó expuesto a dos "
      "vulnerabilidades críticas de ejecución remota de código sin autenticación, con puntuación "
      "nueve coma dos. Se actualizó el entorno y, sobre todo, se añadió un comprobador que aborta "
      "la construcción y el arranque si detecta un intérprete vulnerable.")

opcional("Las otras cuatro: el recuento de la suite aparecía con tres cifras distintas en el mismo "
         "documento; el defecto de propiedad de los comprobantes reapareció y se trasladó a una "
         "política única; figuraba un colaborador que no correspondía; y el modal de editar "
         "producto mostraba el icono de imagen rota del navegador.")

h("Página 80  ·  20.2. Observaciones que siguen abiertas", size=15)
reloj()
cue("★ En pantalla: Tabla 64. Esta página es la que distingue el capítulo.")

decir("Esta es la tabla que quería mostrarle. Son cuatro observaciones que siguen abiertas, y "
      "están aquí a propósito: un cuadro que solo recogiera lo corregido no sería un cuadro de "
      "observaciones, sería una lista de logros.")

decir("La primera y la segunda son del mismo tipo: hay figuras cuyos números quedaron superados "
      "por el avance del propio sistema. Las Figuras 43 y C punto 3 muestran ejecuciones de la "
      "suite con cifras ya antiguas, y la Figura 11 lleva rotulado «26 migraciones» dentro del "
      "PNG, cuando hoy son muchas más.")

decir("No las corregí reescribiendo el pie de foto, y el motivo está en la tabla: cambiar el texto "
      "haría que el pie mintiera sobre su propia imagen. Lo que corresponde es volver a tomar la "
      "captura, y eso quedó comprometido como acción A-08 en la retrospectiva.")

opcional("Las otras dos: las cifras del portal público no coinciden entre tres páginas, que es una "
         "decisión de negocio sobre cuál es la correcta; y los 884 productos apuntan a archivos de "
         "imagen que ya no existen, de modo que el respaldo visual lo resuelve en pantalla pero el "
         "catálogo sigue sin fotografías.")

h("Páginas 80 a 85  ·  20.3. Comparación antes y después", size=15)
reloj()
cue("Desplazar por las Figuras 53 a 58 sin detenerse en cada una.")

decir("Estas seis figuras son el antes y el después de las correcciones de interfaz. Se tomaron "
      "sobre el entorno en ejecución, no sobre un prototipo.")

opcional("Y cada una se acompañó de una comprobación sobre el propio documento HTML, porque hay cosas "
      "que la captura no muestra: dónde queda el foco del teclado y qué campos se marcan como "
      "inválidos. En la Figura 54 el foco se sitúa en el primer campo que falla; en la 56, el foco "
      "del diálogo de cierre de sesión entra en la opción segura y no en la destructiva.")

# =============================================================================
# CAPÍTULO XXI
# =============================================================================
h("Página 85  ·  XXI. Gestión de solicitudes de servicio", size=15)
reloj()
cue("En pantalla: el inicio del capítulo y la Tabla 65, el ciclo de atención.")

decir("Entramos al capítulo de solicitudes de servicio. Empiezo por la distinción que lo ordena "
      "todo, porque confundirla tiene coste real.")

decir("Una solicitud es una petición habitual: información, un acceso, una acción estándar ya "
      "definida. Un incidente es algo que debería funcionar y está fallando. Y un cambio modifica "
      "el servicio.")

decir("Si una caída se atiende como petición, espera turno. Y si una petición se trata como "
      "incidente, dispara una urgencia que no existe. La tabla recoge el ciclo de siete pasos: "
      "registrar, clasificar, validar, autorizar, atender, verificar y cerrar.")

h("Página 86  ·  21.2. Catálogo de solicitudes atendidas", size=15)
reloj()
cue("★ En pantalla: Tabla 66, las seis solicitudes.")

decir("Seis solicitudes registradas y cerradas: dos de información, dos de acceso y dos "
      "estándar.")

decir("La que quiero destacar es la SOL-004, porque es la única rechazada. Un cliente del portal "
      "pidió acceso al módulo de reportes «para comparar precios históricos». Conceder lo que se "
      "pide es lo cómodo, y el procedimiento existe precisamente para los casos en que no "
      "corresponde.")

decir("Se denegó por el principio de mínimo privilegio: los reportes contienen márgenes, costos de "
      "compra y datos de otros clientes. Y se le ofreció la alternativa que sí le pertenece, que "
      "es su propio historial de pedidos y sus comprobantes.")

cue("★ Remata con esto.")

decir("La evidencia de esa denegación no es una captura de pantalla: es el propio sistema "
      "respondiendo 401 y 403 a un token de rol cliente, con pruebas automatizadas que fijan la "
      "regla para que no se afloje por comodidad más adelante.")

h("Páginas 87 y 88  ·  Figuras 59 y 60 — el tablero y la ficha", size=15)
reloj()
cue("En pantalla: Figura 59, tablero de GitHub Projects; luego Figura 60, ficha de SOL-004.")

opcional("El tablero con las seis solicitudes registradas y cerradas, y debajo la ficha de SOL-004 "
      "con sus campos de seguimiento.")

decir("Hay un matiz en esta página que conviene advertir, porque induce a error. El estado propio "
      "de GitHub pasa a «Done» en cuanto la incidencia se cierra, de modo que SOL-004 figura allí "
      "como completada aunque fue rechazada.")

decir("El campo que manda en este procedimiento es «Estado solicitud». Una solicitud denegada "
      "también se cierra, y cerrarla no significa haberla concedido.")

h("Páginas 89 a 99  ·  21.3. El ciclo aplicado a cada solicitud", size=15)
reloj()
cue("Desplazar por las seis fichas con ritmo. Detente solo en la 99 (SOL-006).")

decir("Estas once páginas no enuncian el ciclo en abstracto: lo recorren entero sobre cada una de "
      "las seis solicitudes, con la ficha de seguimiento que se mantiene en el repositorio. El "
      "contenido de cada ficha coincide con el publicado en su incidencia, de modo que el informe "
      "y el repositorio dicen lo mismo.")

opcional("La SOL-002 es la que mejor explica una regla del negocio: el propietario observó que un "
         "producto con existencias físicas aparecía con menos unidades en el portal. No era un "
         "error. El sistema distingue stock contable, que es lo que hay en el anaquel, de stock "
         "vendible, que es lo que se le puede prometer a un cliente. Los lotes vencidos cuentan en "
         "el primero y se excluyen del segundo.")

cue("Detente en la página 99, ficha de SOL-006.")

decir("Y la SOL-006 es la que ata este capítulo con el siguiente. Es una petición de respaldo "
      "antes de una carga masiva, y se atendió ejecutando el respaldo antes y no después, que es "
      "lo único que sirve para volver atrás. Es el mismo procedimiento que permitió autorizar la "
      "fusión de 869 grupos de productos duplicados sin asumir el riesgo a ciegas.")

# =============================================================================
# CAPÍTULO XXII
# =============================================================================
salto()
h("Página 100  ·  XXII. Procedimiento de control de cambios", size=15, espacio_antes=0)
reloj()
cue("En pantalla: el inicio del capítulo y la Tabla 73, clasificación.")

decir("Capítulo de control de cambios. El flujo es registrar, evaluar impacto y riesgo, autorizar, "
      "preparar el plan de vuelta atrás, ejecutar, verificar y cerrar documentando.")

decir("La frase que gobierna el procedimiento es esta: el plan de vuelta atrás no es opcional. Un "
      "cambio sin forma de deshacerlo no se autoriza. En este proyecto además es barato, porque la "
      "plataforma conserva los despliegues anteriores y uno se reactiva desde el panel; de modo "
      "que no hay excusa para omitirlo.")

opcional("La tabla clasifica los cambios en tres tipos —estándar, normal y de emergencia— y cada fila "
      "lleva un ejemplo real del proyecto, no un ejemplo de manual.")

h("Página 101  ·  22.2. Caso de estudio: el cambio de emergencia CAM-001", size=15)
reloj()
cue("★ En pantalla: Tabla 74. Es el caso de estudio del capítulo.")

decir("Este es el único cambio de emergencia del proyecto, y es real. El 21 de octubre apareció un "
      "aviso de seguridad de php punto net sobre dos vulnerabilidades con puntuación nueve coma "
      "dos: ejecución remota de código sin autenticación.")

decir("Lo que lo convierte en emergencia está en la fila de impacto. El aviso nombra a FrankenPHP, "
      "que es exactamente el servidor de esta API. Y el fallo se dispara durante el análisis de la "
      "petición, antes de que se ejecute una sola línea de la aplicación: ni la autenticación ni "
      "los roles llegan a intervenir.")

decir("Por tratarse de una emergencia se ejecutó primero y se documentó después. Eso está dicho en "
      "la tabla, no escondido.")

opcional("El plan de vuelta atrás también está dicho con su contrapartida: reactivar el despliegue "
         "anterior desde el panel volvería a dejar el servidor expuesto, de modo que solo se usaría "
         "si el parche rompiera el servicio.")

h("Página 102  ·  La salvaguarda que dejó el cambio", size=15)
reloj()

decir("Esta página explica algo que me parece más valioso que el parche en sí. La etiqueta de la "
      "imagen del contenedor se mantiene flotante a propósito, para que la siguiente corrección "
      "llegue sola al reconstruir.")

opcional("Pero una etiqueta flotante tiene un defecto: no permite demostrar qué versión se está "
      "ejecutando. Eso lo cubre el comprobador que aborta el arranque sobre un intérprete "
      "vulnerable, y que compara contra una tabla de versiones mínimas por rama, no contra un "
      "único número, porque un único número daría un falso negativo con una rama posterior aún sin "
      "corregir.")

h("Páginas 102 y 103  ·  22.3 y 22.4. Plantilla y registro de cambios", size=15)
reloj()
cue("En pantalla: Tabla 75, plantilla; luego Tabla 76, los cinco cambios.")

opcional("La plantilla de solicitud de cambio responde, antes de tocar nada, qué cambia, por qué, "
      "quién lo aprueba, cómo se prueba y cómo se deshace. La regla se enuncia mejor por su "
      "contrapositivo: un cambio que no se puede describir ni revertir no pasa a producción.")

decir("Y debajo, el registro de los cinco cambios del proyecto. Lo que quiero que vea es la "
      "columna de riesgo asumido, que no dice «bajo» en todas: el CAM-003, la contingencia offline "
      "del punto de venta, figura con riesgo alto, porque duplicar ventas o comprobantes es una "
      "contingencia tributaria.")

h("Páginas 103 y 104  ·  22.5 y 22.6. Calendario e integración continua", size=15)
reloj()
cue("★ En pantalla: Tabla 78, los tres trabajos del pipeline.")

opcional("El calendario de cambios existe para que dos modificaciones críticas no compitan por la "
      "misma ventana, y para que ninguna entre cuando no hay nadie que pueda comprobar el "
      "resultado. Esa regla no salió de un manual: salió del despliegue que estuvo nueve días roto.")

decir("Debajo está la integración continua, con tres trabajos y un orden que no es casual. El "
      "escaneo de secretos va primero y bloquea a los demás: si hay una credencial en el commit, "
      "lo último que interesa es gastar cinco minutos corriendo pruebas.")

decir("Las pruebas del servidor corren sobre PostgreSQL 16, el mismo motor que producción, y eso "
      "también está justificado: SQLite no entiende las construcciones que usan las consultas de "
      "este proyecto, así que probar contra SQLite sería probar otra cosa.")

cue("★ El dato de las 71 ejecuciones. No lo saltes.")

decir("Y el dato que no voy a esconder: el historial recoge 71 ejecuciones, de las cuales 27 "
      "terminaron en verde y 44 en rojo. Esas 44 no son un mal resultado: son el argumento a favor "
      "de tener integración continua, porque cada una detuvo un cambio antes de que llegara a "
      "producción.")

opcional("Un caso con nombre: una ejecución de octubre superó el escaneo de secretos y las pruebas "
         "del servidor, y falló en la compilación del front-end. Es decir, el código del servidor "
         "estaba bien y lo que se habría desplegado era una interfaz que no compilaba.")

h("Páginas 105 a 107  ·  Figuras 67 a 69 — el pipeline", size=15)
reloj()
cue("Desplazar por las tres figuras.")

opcional("Las tres figuras son el historial del pipeline, el detalle de una ejecución disparada por "
         "un Pull Request con los tres trabajos en verde, y el historial filtrado por fallos, que "
         "es donde se ven las 44 integraciones detenidas.")

h("Páginas 107 y 108  ·  22.7. Vinculación con rama, commit y Pull Request", size=15)
reloj()
cue("★ En pantalla: Tabla 79. Mira la columna «Pull Request».")

decir("Esta tabla es la que más me costó escribir, y es la que más defiendo.")

decir("Vincular cada cambio con su rama, su commit y su Pull Request obliga a reconocer algo que "
      "el informe no debería maquillar: de los cinco cambios, cuatro no tienen Pull Request. La "
      "celda lo dice con todas sus letras en lugar de quedarse vacía.")

decir("Hasta el CAM-004 la práctica se cumplía a medias: había commits directos a la rama de "
      "desarrollo y una fusión directa a la principal. En un proyecto de un solo autor, un Pull "
      "Request consigo mismo parece un trámite.")

decir("Pero el procedimiento no está escrito para el caso cómodo. Sirve para que el pipeline se "
      "pronuncie antes de que el cambio toque la rama que despliega, y para que exista un sitio "
      "donde quede escrito qué se revisó.")

cue("★ Cierra con esto, es el remate del capítulo.")

decir("A partir del CAM-005 el flujo cambia: una rama por cambio, Pull Request, y fusión solo con "
      "el pipeline en verde. Y este mismo apartado entró por ese camino, en el Pull Request número "
      "veinte. Su propia evidencia demuestra el procedimiento que describe.")

h("Páginas 108 a 111  ·  22.8 y 22.9. Pendientes y expedientes", size=15)
reloj()
cue("Desplazar por los cinco expedientes sin leerlos.")

opcional("El apartado 22.8 lista los cambios registrados y todavía no ejecutados. Van aparte para "
         "que el registro del 22.4 siga diciendo la verdad sobre lo que se hizo y no mezcle "
         "intenciones con hechos.")

decir("Y el 22.9 desarrolla el expediente completo de cada uno de los cinco cambios, paso a paso: "
      "selección, solicitud, impacto y riesgo, calendario y vuelta atrás, trazabilidad y "
      "validación en el pipeline.")

# =============================================================================
# CAPÍTULO XXIII
# =============================================================================
h("Páginas 111 y 112  ·  XXIII. Gestión de capacidad y rendimiento", size=15)
reloj()
cue("★ En pantalla: Tabla 86, mediciones; luego Tabla 87, capacidad.")

decir("Capítulo de capacidad y rendimiento. La primera tabla son mediciones, con su método al "
      "lado, no estimaciones.")

decir("El catálogo en producción responde con una mediana de 382 milisegundos, frente a un umbral "
      "de dos segundos. El salto de maquetación se queda en 0.015 cuando el umbral de «bueno» es "
      "0.1. Y la compilación del cliente entrega una carga inicial de 189 kilobytes, 71 "
      "comprimidos.")

opcional("La medición del catálogo descarta la primera petición a propósito, y el motivo está "
         "escrito: el plan gratuito suspende el contenedor por inactividad, así que la primera "
         "respuesta mide el arranque y no el sistema.")

decir("La segunda tabla es la capacidad actual: 884 productos, 4 501 unidades vendibles, 886 lotes "
      "y 119 rutas de API.")

cue("★ Esta es la página honesta del capítulo. Dila entera.")

decir("Y el apartado 23.2 dice lo que no se ha medido, que me parece la parte más importante. No "
      "se ha ejecutado una prueba de carga. Todas las cifras anteriores describen el tiempo de "
      "respuesta con un único usuario, no el comportamiento con varios simultáneos.")

decir("Afirmar que el sistema soporta una cantidad determinada de usuarios sin haberlo comprobado "
      "sería inventarlo. Queda comprometido como acción A-12.")

# =============================================================================
# CAPÍTULO XXIV
# =============================================================================
h("Páginas 112 y 113  ·  XXIV. Despliegue de la versión final", size=15)
reloj()
cue("En pantalla: Tabla 88, estado verificado el 23 de octubre.")

opcional("La versión final está desplegada sobre tres plataformas gestionadas, una por capa: la "
      "interfaz en Vercel, la API en un contenedor con PHP 8.4.27, y la base de datos "
      "PostgreSQL gestionada.")

decir("La frase que define este capítulo es la del apartado 24.1: el despliegue no se da por bueno "
      "porque la plataforma informe «deploy succeeded». Eso solo acredita que la construcción "
      "terminó, y es exactamente el error que dejó el servicio nueve días desactualizado.")

decir("La comprobación se hizo contra el sistema en ejecución el 23 de octubre, y abarcó las dos "
      "caras: lo que debe responder y lo que debe negarse a responder. Las rutas privadas "
      "devuelven 401 sin credenciales, y ese es el resultado correcto: es la única forma de "
      "verificar que el control de acceso viajó al entorno desplegado y no se quedó en el código "
      "local.")

h("Página 114  ·  Los datos que no viajaron a producción", size=15)
reloj()
cue("En pantalla: Figura 70, portal del cliente con datos reales.")

decir("Dos decisiones sobre los datos. La primera: la base desplegada contiene únicamente el "
      "catálogo de productos migrado del sistema anterior. Las cuentas, los pedidos y los "
      "comprobantes reales no se copiaron, porque son datos personales de clientes que no tienen "
      "por qué viajar a un entorno de curso.")

decir("La segunda: la vuelta atrás es de dos clases distintas. El código se revierte "
      "redesplegando el commit anterior desde la propia plataforma. Cualquier operación "
      "destructiva sobre los datos, en cambio, exige respaldo previo y autorización registrada, "
      "tal como quedó documentado en la solicitud SOL-006.")

opcional("Las Figuras 71 y 72 son las dos caras de la comprobación: el rastro de cada despliegue "
         "hasta el commit que lo originó, y la respuesta del propio servicio de salud confirmando "
         "su conexión con la base.")

# =============================================================================
# CAPÍTULO XXV
# =============================================================================
h("Páginas 116 y 117  ·  XXV. Retrospectiva del Sprint 6", size=15)
reloj()
cue("En pantalla: Tabla 89, qué mantener; y Tabla 90, qué dejar de hacer.")

decir("La retrospectiva. Cada fila de «qué mantener» lleva al lado qué evitó, y cada fila de «qué "
      "dejar de hacer» lleva qué costó. No son buenos propósitos: son consecuencias medidas.")

opcional("De lo que hay que mantener, destaco dos. Leer el código antes de afirmar qué le falta: dos "
      "de los cinco defectos atribuidos a la pantalla de acceso ya estaban resueltos, y una "
      "búsqueda por palabra clave no los encontró. Y trasladar la regla a un único sitio en lugar "
      "de repetirla, porque el defecto de propiedad de los comprobantes reapareció precisamente "
      "por haberlo corregido dentro de un método concreto.")

cue("★ La tabla de errores. Léela sin defenderte.")

decir("Y de lo que hay que dejar de hacer, los dos que más costaron. Silenciar la salida de un "
      "comando que puede fallar: treinta ediciones del tablero fallaron sin que nadie se enterara. "
      "Y declarar cifras que no se acaban de medir: el recuento de la suite llegó a aparecer con "
      "tres valores distintos en el mismo documento.")

opcional("Hay una tercera que es puramente conceptual y vale la pena: dar por protegido un punto de "
         "acceso porque su grupo exige sesión. Exigir identidad no es exigir propiedad, y por eso "
         "las descargas de comprobantes quedaron abiertas a cualquier cuenta autenticada.")

h("Página 118  ·  25.4. Compromisos para el siguiente Sprint", size=15)
reloj()
cue("En pantalla: Tabla 91, acciones A-08 a A-12.")

decir("Cinco compromisos, cada uno con su indicador de éxito, y los cinco se corresponden uno a "
      "uno con las observaciones que dejé abiertas en el capítulo veinte: recapturar las figuras "
      "con cifras superadas, regenerar el diagrama del modelo de datos, cargar las imágenes reales "
      "del catálogo, auditar las rutas sin prueba de autorización y ejecutar la prueba de carga.")

# =============================================================================
# CONCLUSIONES Y REFERENCIAS
# =============================================================================
h("Páginas 118 y 119  ·  XXVI. Conclusiones", size=15)
reloj()
cue("En pantalla: las conclusiones.")

decir("Son diez conclusiones. Resumo las tres que sostienen el avance.")

decir("La primera: el problema principal no es la antigüedad de la tecnología, sino la desconexión "
      "entre el sistema de gestión y el facturador electrónico, que obliga a digitar dos veces "
      "cada comprobante.")

opcional("La segunda: delimitar el alcance con un cuadro de exclusiones documentado fue determinante "
      "para que el proyecto fuera viable en dieciocho semanas con un equipo de una sola persona.")

decir("Y la décima, que es la que mejor resume el criterio con el que trabajé: los datos "
      "personales de terceros no viajan al entorno público aunque hacerlo sea trivial. El catálogo "
      "se copió porque no identifica a nadie; los usuarios y los pedidos reales se quedaron en "
      "local.")

opcional("La página 119 cierra con las referencias: la Ley 29459 de productos farmacéuticos, el "
         "manual de buenas prácticas de DIGEMID, la resolución de la SUNAT sobre emisión "
         "electrónica, el PMBOK, la Guía de Scrum y Pressman.")

# =============================================================================
# ANEXOS
# =============================================================================
h("Páginas 120 a 148  ·  Los cuatro anexos", size=15)
reloj()
cue("Desplazar con ritmo. Un par de frases por anexo, sin detenerse en figuras.")

decir("El informe cierra con cuatro anexos.")

decir("El Anexo A, desde la página 120, es la evidencia fotográfica del sistema actual: treinta "
      "figuras del sistema heredado, cada una vinculada al hallazgo que documenta. Es la prueba de "
      "que el diagnóstico del primer avance se levantó mirando el sistema real, no describiéndolo "
      "de oídas.")

opcional("El Anexo B, desde la 136, es evidencia del código fuente: control de acceso por rol, "
      "modelado del comprobante electrónico, la integración con la SUNAT y las cabeceras de "
      "seguridad, cada fragmento vinculado a su requerimiento.")

opcional("El Anexo C, desde la 139, son las salidas de verificación: migraciones aplicadas en "
      "producción, el recuento del catálogo, la suite de pruebas y la medición de contraste.")

decir("Y el Anexo D, desde la 142, son las capturas del sistema en funcionamiento: acceso, "
      "registro, punto de venta, cobro con vuelto, conteo por ciclos, tablero, pedidos del portal "
      "y facturación.")

# =============================================================================
# CIERRE
# =============================================================================
h("Cierre", size=16)
reloj()
cue("Volver al inicio del documento o quedarse en las conclusiones.")

decir("Para cerrar. El segundo avance demostró que el sistema funciona. Este tercero intenta "
      "demostrar algo distinto y, creo, más difícil: que se puede operar y modificar sin romperlo, "
      "y que cuando algo se rompe queda registro de qué pasó y de cómo se volvió atrás.")

decir("Por eso el informe incluye las cuatro observaciones que siguen abiertas, las 44 "
      "integraciones que fallaron, los cuatro cambios que no pasaron por revisión y la prueba de "
      "carga que no se ejecutó. Un informe que solo recogiera lo que salió bien no diría nada "
      "sobre el método que lo produjo.")

decir("Muchas gracias, profesora. Quedo atento a sus preguntas.")

# =============================================================================

# =============================================================================
# SEGUNDA PASADA: calcular los tiempos y escribir el guion
# =============================================================================
def _mmss(segundos):
    return f"{int(segundos) // 60}:{int(segundos) % 60:02d}"


# Se escribe en una sola pasada, resolviendo el reloj con los tramos ya contados
def _volcar():
    tramos = {}
    actual = None
    for i, (tipo, dato) in enumerate(_guion):
        if tipo == "reloj":
            actual = i
            tramos[i] = 0
        elif tipo in ("decir", "opcional") and actual is not None:
            tramos[actual] += len(dato.split())

    acumulado = 0.0
    for i, (tipo, dato) in enumerate(_guion):
        if tipo == "h":
            texto, color, size, antes = dato
            _h(texto, color=color, size=size, espacio_antes=antes)
        elif tipo == "decir":
            _decir(dato)
        elif tipo == "opcional":
            _opcional(dato)
        elif tipo == "cue":
            texto, color = dato
            _cue(texto, color=color)
        elif tipo == "nota":
            _nota(dato)
        elif tipo == "tabla":
            filas, estilo, anchos, size = dato
            _tabla(filas, estilo=estilo, anchos=anchos, size=size)
        elif tipo == "salto":
            doc.add_page_break()
        elif tipo == "reloj":
            dur = tramos[i] / PALABRAS_POR_MINUTO * 60
            _reloj(f"Tiempo  {_mmss(acumulado)} — {_mmss(acumulado + dur)}")
            acumulado += dur

    return acumulado


_TOTAL = _volcar()

# La duración no se estima: se cuenta sobre el propio texto hablado.
_hablado = sum(len(d.split()) for t, d in _guion if t == "decir")
_opc = sum(len(d.split()) for t, d in _guion if t == "opcional")
_min_todo = (_hablado + _opc) / PALABRAS_POR_MINUTO
_min_sin_opc = _hablado / PALABRAS_POR_MINUTO

_nota_duracion.runs[0].text = (
    f"DURACIÓN MEDIDA, NO ESTIMADA. El texto hablado son {_hablado + _opc} palabras: "
    f"unos {_min_todo:.0f} minutos leyéndolo entero a ritmo de sustentación "
    f"({PALABRAS_POR_MINUTO} palabras por minuto, que ya descuenta las pausas para desplazar "
    f"el documento). Saltando los [OPC] quedan {_hablado} palabras, unos {_min_sin_opc:.0f} "
    f"minutos. Los tramos de reloj de cada bloque están calculados con esa misma cuenta, de "
    f"modo que si te retrasas en uno sabes cuánto tienes que recortar en el siguiente."
)

# ANEXO DEL GUION: posibles preguntas
# =============================================================================
doc.add_page_break()
_h("Anexo — respuestas rápidas a preguntas probables", size=16, espacio_antes=0)
_nota("No se lee. Es para tenerlo a mano si la profesora pregunta.")

qa = [
    ("La Figura 43 dice 198 pruebas. ¿Es la cifra actual?",
     "No, y el informe lo declara como observación abierta O-B1. Esa captura se tomó cuando la "
     "suite tenía 198 pruebas y 603 aserciones. Desde entonces la suite creció. No reescribí el "
     "pie porque entonces el texto mentiría sobre su propia imagen: lo que corresponde es volver "
     "a tomar la captura, y es la acción A-08."),
    ("La Tabla 87 dice 46 migraciones y la Figura 50 dice 49. ¿Cuál es?",
     "Las 49 son las aplicadas sobre la base de producción, que es lo que mide esa figura. La "
     "Tabla 87 recoge el recuento del repositorio en el momento de escribir el capítulo. La "
     "diferencia es de fecha de medición, no de criterio, y la cifra vigente es la de "
     "producción."),
    ("¿Por qué cuatro de los cinco cambios no tienen Pull Request?",
     "Porque hasta el CAM-004 el trabajo entraba con commits directos a la rama de desarrollo. "
     "Está declarado en la Tabla 79 en lugar de dejar la celda vacía. A partir del CAM-005 el "
     "flujo es una rama por cambio, Pull Request y fusión solo con el pipeline en verde; ese "
     "mismo capítulo entró así, por el PR #20."),
    ("44 de 71 ejecuciones del pipeline fallaron. ¿No es un mal indicador?",
     "Depende de qué se mida. Hay que distinguir los fallos DEL pipeline, que son ruido y hay que "
     "arreglar, de los fallos detectados POR el pipeline, que son su razón de existir. Las 44 son "
     "del segundo tipo: cada una detuvo un cambio antes de producción. Si el número fuera cero yo "
     "sospecharía que el pipeline no comprueba nada."),
    ("¿El sistema ya factura electrónicamente ante la SUNAT?",
     "No. El comprobante se emite dentro del sistema con serie y número correlativo, pero el "
     "envío está en modo simulado a la espera del certificado digital y las credenciales del "
     "operador. El comprobante nace con estado pendiente y el informe lo dice."),
    ("¿Cuántos usuarios simultáneos soporta?",
     "No lo sé, y prefiero decirlo así. No se ejecutó prueba de carga. Las mediciones del "
     "capítulo XXIII son con un único usuario. Está declarado en el apartado 23.2 y comprometido "
     "como acción A-12."),
    ("¿Por qué el despliegue estuvo nueve días roto sin que se notara?",
     "Porque la plataforma mantiene en ejecución el contenedor anterior cuando la construcción de "
     "la imagen nueva falla. El servicio respondía 200 a toda petición; lo único observable desde "
     "fuera era que el código nuevo no aparecía. La causa fue una discrepancia de versiones: "
     "composer.lock fijó paquetes que exigen PHP 8.4.1 mientras la imagen base traía 8.3.35."),
    ("¿Cómo se corrigió esa discrepancia, y por qué no con --ignore-platform-req?",
     "La imagen base pasó a PHP 8.4 y composer.json declara config.platform.php en 8.4.1, de modo "
     "que Composer resuelve siempre contra la versión del despliegue y no contra la del equipo "
     "donde se ejecute el comando. La bandera se descartó a propósito: no corrige la "
     "incompatibilidad, silencia el aviso que la anuncia."),
    ("¿Por qué se rechazó la SOL-004 si el cliente solo quería comparar precios?",
     "Por mínimo privilegio. El módulo de reportes contiene márgenes, costos de compra y datos de "
     "otros clientes; no es una pantalla de precios. Se le ofreció lo que sí le corresponde: su "
     "historial de pedidos y sus comprobantes. Y la regla no depende del criterio del momento, "
     "está fijada con pruebas automatizadas."),
    ("¿Qué diferencia hay entre una solicitud, un incidente y un cambio?",
     "Una solicitud es una petición habitual ya prevista: información, un acceso, una acción "
     "estándar. Un incidente es algo que debería funcionar y está fallando. Un cambio modifica el "
     "servicio. Confundirlos cuesta: una caída atendida como petición espera turno, y una "
     "petición tratada como incidente dispara una urgencia que no existe."),
    ("¿Qué queda pendiente para el siguiente Sprint?",
     "Las cinco acciones de la Tabla 91, que se corresponden una a una con las observaciones "
     "abiertas: recapturar las figuras con cifras superadas, regenerar el diagrama del modelo de "
     "datos, cargar las imágenes reales del catálogo, auditar las rutas sin prueba de "
     "autorización y ejecutar la prueba de carga."),
]

for pregunta, respuesta in qa:
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(8)
    p.paragraph_format.space_after = Pt(2)
    p.paragraph_format.keep_with_next = True
    r = p.add_run(pregunta)
    r.bold = True
    r.font.size = Pt(11)
    r.font.color.rgb = AZUL
    pr = doc.add_paragraph(respuesta)
    pr.paragraph_format.left_indent = Inches(0.15)
    pr.paragraph_format.space_after = Pt(4)
    for run in pr.runs:
        run.font.size = Pt(10.5)

_h("Chuleta de cifras", size=14, espacio_antes=16)
_nota("Por si se te va una en el momento. Todas salen del propio informe.")

cifras = [
    ("Informe", "148 páginas · 27 capítulos · 4 anexos · capítulos nuevos: XX a XXVII"),
    ("Correcciones", "5 observaciones subsanadas (Tabla 63) · 4 todavía abiertas (Tabla 64)"),
    ("Solicitudes", "6 registradas: 2 información, 2 acceso, 2 estándar · 1 rechazada (SOL-004)"),
    ("Incidencias", "SOL-001 a SOL-006 = issues #14 a #19 del repositorio"),
    ("Cambios", "CAM-001 a CAM-005 · 1 de emergencia, 4 normales · solo CAM-005 con PR (#20)"),
    ("CVE del parche", "CVE-2026-109618 y CVE-2026-109619 · CVSS 9.2 · ejecución remota sin autenticar"),
    ("Integración continua", "71 ejecuciones: 27 en verde, 44 en rojo · 3 trabajos: secretos, pruebas, compilación"),
    ("Despliegue roto", "9 días (5 al 7 de octubre) · composer.lock exigía PHP 8.4.1, la imagen traía 8.3.35"),
    ("Rendimiento", "Catálogo: mediana 382 ms (umbral 2 000) · CLS 0.015 (umbral 0.1) · build 6.12 s"),
    ("Peso al navegador", "Carga inicial 189 kB · 71 kB comprimidos"),
    ("Capacidad", "884 productos · 4 501 unidades vendibles · 886 lotes · 119 rutas de API"),
    ("Versión final", "Verificada el 23/10/2026 · Vercel + contenedor PHP 8.4.27 + PostgreSQL gestionado"),
    ("Datos en producción", "Solo el catálogo. Cero usuarios, pedidos y comprobantes reales"),
    ("Compromisos", "A-08 a A-12 · uno por cada observación abierta"),
]

t2 = doc.add_table(rows=len(cifras), cols=2)
t2.style = "Light List Accent 1"
for i, (clave, valor) in enumerate(cifras):
    c0, c1 = t2.rows[i].cells
    c0.width = Inches(1.6)
    c1.width = Inches(5.2)
    c0.text = ""
    c1.text = ""
    r0 = c0.paragraphs[0].add_run(clave)
    r0.bold = True
    r0.font.size = Pt(9.5)
    r1 = c1.paragraphs[0].add_run(valor)
    r1.font.size = Pt(9.5)

doc.save("SPEECH_EXPOSICION_APF3.docx")
print("SPEECH_EXPOSICION_APF3.docx generado")
