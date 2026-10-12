#!/usr/bin/env python3
"""Genera SPEECH_EXPOSICION_APF2.docx: recorrido página a página del informe."""
from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Pt, RGBColor, Inches
from docx.enum.table import WD_TABLE_ALIGNMENT

AZUL = RGBColor(0x1F, 0x3C, 0x6E)
GRIS = RGBColor(0x5A, 0x5A, 0x5A)
VERDE = RGBColor(0x1B, 0x6E, 0x4B)
AMBAR = RGBColor(0x8A, 0x5A, 0x00)

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
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(espacio_antes)
    p.paragraph_format.space_after = Pt(4)
    p.paragraph_format.keep_with_next = True
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


def opcional(texto):
    """Párrafo prescindible si vas justo de tiempo."""
    p = doc.add_paragraph()
    p.paragraph_format.left_indent = Inches(0.12)
    marca = p.add_run("[OPC] ")
    marca.bold = True
    marca.font.color.rgb = RGBColor(0x99, 0x99, 0x99)
    r = p.add_run(texto)
    r.font.color.rgb = RGBColor(0x55, 0x55, 0x55)
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
r = st.add_run("Recorrido página a página del informe\n"
               "Christopher Rafaile  ·  Curso Integrador II: Sistemas  ·  UTP")
r.font.size = Pt(11)
r.font.color.rgb = GRIS

d = doc.add_paragraph()
d.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = d.add_run("Uso personal. Exposición sin diapositivas: se comparte pantalla y se desplaza el informe.")
r.italic = True
r.font.size = Pt(10)
r.font.color.rgb = GRIS

nota("PAGINACIÓN VERIFICADA. Los números corresponden al documento con el índice ya actualizado "
     "(F9 en Word). Se comprobaron dos anclas contra tu propia lectura: el capítulo XIII abre en la "
     "página 44 y Referencias cierra en la 77. Si al actualizar el índice algo se desplaza una "
     "página, el título de cada bloque te sitúa igual.")

h("Cómo usar este guion", size=13, espacio_antes=14)

filas = [
    ("Marca", "Significado"),
    ("Página NN", "Dónde debes estar al decir lo que sigue."),
    ("[ ... ]", "Acotación: qué señalar o dónde hacer énfasis. No se lee."),
    ("[OPC]", "Párrafo prescindible. Sáltalo si vas justo de tiempo."),
    ("★", "No se salta. Es lo que distingue este avance de una maqueta."),
]
tabla = doc.add_table(rows=len(filas), cols=2)
tabla.style = "Light Grid Accent 1"
tabla.alignment = WD_TABLE_ALIGNMENT.CENTER
for i, fila in enumerate(filas):
    for j, valor in enumerate(fila):
        celda = tabla.rows[i].cells[j]
        celda.width = Inches(1.3) if j == 0 else Inches(5.0)
        celda.text = ""
        run = celda.paragraphs[0].add_run(valor)
        run.font.size = Pt(10)
        if i == 0:
            run.bold = True

nota("DURACIÓN REAL. Un recorrido página a página con contenido no baja de 14 minutos: son "
     "36 páginas. Saltando los [OPC] y desplazando con ritmo por las páginas 46 a 51 y 57 a "
     "62 —que son tablas y capturas de casos— quedan unos 11. Si la ventana es de 9, "
     "resume también las páginas 65 a 70 en una frase: «estas páginas son las "
     "comprobaciones del despliegue» y sigue hasta la 71.")

# =============================================================================
# APERTURA
# =============================================================================
h("Apertura  ·  antes de desplazar nada", size=16, espacio_antes=18)
reloj("Tiempo  0:00 — 0:50")
cue("Compartir pantalla con el informe abierto en la CARÁTULA.")

decir("Buenas noches, profesora. Mi nombre es Christopher Rafaile y voy a sustentar el segundo "
      "avance del Sistema Web de Gestión y Control para la Botica San Juan.")

decir("En el primer avance presenté el diagnóstico: la botica opera con un sistema heredado en "
      "Visual FoxPro desconectado del facturador de la SUNAT, con comprobantes cargados a mano y "
      "hasta catorce días de retraso. Propuse una arquitectura web desacoplada, planificada con "
      "Scrum.")

decir("Aquel avance era un plan. Este es un sistema funcionando y desplegado. Voy a recorrer los "
      "capítulos nuevos, del doce al diecinueve, mostrando las comprobaciones.")

cue("Desplazar hasta la página 43.")

# =============================================================================
# CAPÍTULO XII
# =============================================================================
h("Página 43  ·  XII. Correcciones del APF1", size=15)
reloj("Tiempo  0:50 — 1:15")
cue("En pantalla: el cuadro de observaciones y correcciones.")

decir("Esta página es el capítulo de correcciones. No recibí observaciones formales antes de la "
      "entrega, así que revisé el avance anterior contra el propio sistema. El cuadro recoge lo que "
      "no se sostenía y qué se hizo con cada punto.")

opcional("Entre otras cosas: el enlace de «Productos» del menú llevaba a una pantalla en blanco "
         "porque la ruta nunca se declaró, el icono del carrito a «página no encontrada», y el pie "
         "del portal publicaba un RUC que contenía el documento del propietario.")

# =============================================================================
# CAPÍTULO XIII
# =============================================================================
h("Página 44  ·  XIII. Base de datos — Volumen del esquema", size=15)
reloj("Tiempo  1:15 — 1:35")
cue("En pantalla: Tabla 36, métricas del esquema.")

decir("Entramos al capítulo de base de datos. Esta tabla es el volumen del esquema: treinta y dos "
      "tablas, treinta y una claves foráneas, setenta y nueve índices y cuarenta y cinco "
      "migraciones versionadas sobre PostgreSQL 16.")

decir("Esa última cifra es la que importa: el esquema se construye solo con migraciones, sin un "
      "cambio aplicado a mano, y por eso el entorno se levanta desde cero con un comando.")

h("Página 45  ·  Núcleo del modelo y decisiones de diseño", size=15)
reloj("Tiempo  1:35 — 1:55")
cue("En pantalla: Tabla 37 (tablas del núcleo) y Tabla 38 (decisiones).")

decir("Aquí separo las siete tablas que concentran las reglas del negocio, y debajo la tabla de "
      "decisiones de diseño: cada fila explica por qué el modelo es como es.")

decir("La fila que destaco es la del stock: el contable y el vendible no son lo mismo. El "
      "contable incluye los lotes vencidos, porque la mercancía sigue en el anaquel. Mostrarlo "
      "como disponible lleva a prometer unidades que el mostrador no puede entregar.")

h("Página 46  ·  Integridad referencial verificada", size=15)
reloj("Tiempo  1:55 — 2:10")

opcional("Esta página documenta que las treinta y una claves foráneas están declaradas en el motor, "
      "no solo respetadas por el código. La diferencia es que el motor rechaza la operación aunque "
      "el programa se equivoque.")

opcional("Se verificó al cargar el catálogo en producción: el motor rechazó insertar productos "
         "cuya categoría no existía, en lugar de dejarlos huérfanos.")

h("Página 47  ·  Figura 26 — El modelo de datos", size=15)
reloj("Tiempo  2:10 — 2:25")
cue("En pantalla: Figura 26, diagrama generado por pgAdmin.")

opcional("Esta figura es el modelo tal como lo genera pgAdmin a partir del esquema real. No es un "
      "dibujo hecho a mano: es la base leída por la herramienta.")

h("Página 48  ·  Figura 27 y paso al diseño físico", size=15)
reloj("Tiempo  2:25 — 2:40")
cue("En pantalla: Figura 27, recuento por tabla en producción.")

opcional("La figura veintisiete es el recuento por tabla en producción. Debajo empieza el paso del "
      "diseño lógico al físico.")

h("Páginas 49 y 50  ·  Definición física y restricciones", size=15)
reloj("Tiempo  2:40 — 3:00")
cue("Desplazar por las Figuras 28, 29 y 30 sin detenerte en cada una.")

opcional("Estas dos páginas muestran la definición física consultada en pgAdmin: las columnas de la "
      "tabla de pedidos con sus tipos, y las restricciones declaradas en el motor.")

cue("AVISO: el pie de la Figura 30 dice «3 361 productos». Es correcto para esa captura, que se "
    "tomó antes de la depuración. Si la profesora lo nota, la frase de abajo lo explica.", AMBAR)

opcional("La consulta de la figura treinta se ejecutó sobre el catálogo anterior a la depuración; "
         "por eso habla de tres mil trescientos sesenta y uno. La cifra actual, ochocientos ochenta "
         "y cuatro, aparece en el capítulo diecisiete y en los anexos.")

h("Página 51  ·  De la historia de usuario a la tabla", size=15)
reloj("Tiempo  3:00 — 3:15")
cue("En pantalla: Tabla 40 y Tabla 41.")

opcional("Esta página cierra el capítulo con la trazabilidad —qué tabla responde a cada historia de "
      "usuario— y el manejo de credenciales: las claves nunca viajan en el repositorio.")

# =============================================================================
# CAPÍTULO XIV
# =============================================================================
h("Página 52  ·  XIV. Back-End — Organización y endpoints", size=15)
reloj("Tiempo  3:15 — 3:40")
cue("En pantalla: Tabla 42 (componentes) y Tabla 43 (endpoints).")

decir("Empieza el back-end. La primera tabla reparte las capas con una regla sencilla: si una "
      "operación puede dejar la base incoherente, vive en un servicio y dentro de una "
      "transacción. El controlador nunca toca la base directamente.")

decir("Debajo, los endpoints. En total la API expone ciento catorce rutas: noventa y cinco exigen "
      "token y ochenta de esas, además, rol de administrador. Diecinueve son públicas por "
      "decisión explícita.")

h("Página 53  ·  Integración Front-End / Back-End", size=15)
reloj("Tiempo  3:40 — 4:20")
cue("Esta página es texto. Dedícale tiempo: hay dos decisiones técnicas importantes.")

decir("Aquí quiero detenerme en dos piezas.")

decir("La primera, la cotización del carrito. El carrito vive en el navegador para que un "
      "visitante lo arme sin crear cuenta, pero allí solo se guarda qué producto y cuánto: ni "
      "precio ni stock. Esos los pone el servidor, porque lo del navegador lo edita cualquiera "
      "desde la consola. Pidiendo noventa y nueve unidades de un producto que tiene tres, "
      "devuelve tres; enviando un precio de un céntimo, cobra los veinticinco cincuenta reales.")

decir("La segunda, el impuesto, centralizado en un único servicio que usan el carrito y el "
      "mostrador. Lo extraje a propósito: repetir la aritmética habría separado el total de la "
      "web del de la boleta el día que cambie la tasa, y nadie lo habría notado hasta que un "
      "cliente los comparase.")

cue("ÉNFASIS. Si preguntan algo del capítulo, lo más probable es esto.", AMBAR)

decir("Y la regla no es la intuitiva: en el Perú el precio ya lleva el IGV dentro, así que se "
      "extrae, no se suma. Ciento dieciocho soles son cien de base más dieciocho de impuesto.")

opcional("El contrato completo está documentado en OpenAPI 3.1, generado desde el propio código "
         "con Scramble y navegable en la ruta /docs/api. Está restringido al entorno local: "
         "publicar el inventario de endpoints facilita el trabajo a quien busque una ruta mal "
         "protegida.")

# =============================================================================
# CAPÍTULO XV
# =============================================================================
h("Página 54  ·  Figuras 31 y 32, y entrada al capítulo XV", size=15)
reloj("Tiempo  4:20 — 4:40")
cue("En pantalla: Figura 31 (recorrido de una venta) y Figura 32 (punto de venta).")

opcional("La figura treinta y uno recorre una venta desde la tecla hasta el dato. La treinta y dos "
      "es el punto de venta real consumiendo la API, operable por teclado, que es como se trabaja "
      "en un mostrador.")



h("Páginas 54 y 55  ·  XV. Controles de seguridad", size=15)
reloj("Tiempo  4:40 — 5:05")
cue("En pantalla: Tablas 44, 45 y 46.")

decir("Debajo empieza seguridad. Las tres tablas separan los controles: autenticación y "
      "autorización, respuesta y transporte, e integridad del negocio.")

decir("La autenticación usa tokens de Sanctum y las contraseñas se guardan cifradas con bcrypt, "
      "sin aparecer nunca en una respuesta.")

opcional("Dos controles no estaban en el plan y aparecieron al medir. La pantalla de acceso "
         "permitía averiguar qué documentos estaban registrados: medí ciento sesenta milisegundos "
         "de diferencia entre un DNI existente y uno que no, y se corrigió con un hash señuelo que "
         "iguala los dos caminos. Y el portal escribía el token de sesión en la consola del "
         "navegador; se eliminó.")

h("Página 56  ·  Figuras 33 y 34", size=15)
reloj("Tiempo  5:05 — 5:20")
cue("En pantalla: Figura 33, la verificación de acceso por ruta.")

decir("Esta figura es la comprobación del control de acceso contra la API: cada ruta de gestión "
      "responde cuatrocientos uno sin token, cuatrocientos tres con rol cliente y doscientos con "
      "administrador. No es una afirmación del informe: es la respuesta del servidor.")

opcional("Y una decisión de privacidad que afecta a este documento: la base de producción contiene "
         "solo el catálogo. Los usuarios y pedidos reales no se copiaron porque llevan datos de "
         "personas.")

# =============================================================================
# CAPÍTULO XVI
# =============================================================================
h("Páginas 57 y 58  ·  XVI. Pruebas — Alcance y casos funcionales", size=15)
reloj("Tiempo  5:20 — 5:40")
cue("En pantalla: Tablas 47, 48 y 49.")

opcional("Empieza el capítulo de pruebas: el alcance, los requerimientos seleccionados y los casos "
      "funcionales, cada uno con su dato de entrada, resultado esperado y evidencia.")

h("Páginas 59 a 62  ·  Ejecución de los casos", size=15)
reloj("Tiempo  5:40 — 6:10")
cue("Desplazar con ritmo por las Figuras 35 a 42. No te detengas en cada una.")

decir("Estas páginas son la ejecución de cada caso, no su descripción. El caso dos muestra el "
      "stock pasando de tres a una unidad. El cuatro, que un encargo sin stock se rechaza entero. "
      "Y el no funcional dos, que tras forzar un fallo a mitad de la venta todo vuelve a su valor "
      "previo.")

opcional("Cierra con la matriz de trazabilidad, que liga cada requerimiento con su historia, "
      "pantalla, endpoint, tabla y caso de prueba.")

h("Página 63  ·  Defectos y despacho FEFO  ★", size=15)
reloj("Tiempo  6:10 — 6:40")
cue("En pantalla: Tabla 52 (defectos) y el apartado 16.8. AQUÍ TE DETIENES.")

decir("Arriba, el registro de defectos: seis encontrados y corregidos. Los incluyo porque un "
      "informe que solo lista aciertos no dice nada del método.")

decir("Y abajo, el apartado que más me interesa: el despacho por lotes.")

cue("ÉNFASIS. Despacio: es FEFO sobre datos reales, no un ejemplo inventado.", AMBAR)

decir("En el pedido ciento ochenta y seis se vendieron nueve unidades de un producto que tenía "
      "doce, repartidas en dos lotes. El sistema tomó siete del lote que vence en marzo de dos mil "
      "veintisiete, agotándolo, y las dos restantes del que vence en enero de dos mil veintiocho. "
      "Ese es el orden FEFO: sale primero lo que antes vence.")

decir("Y cada unidad quedó ligada a su lote, que es lo que permite responder a quién se le "
      "vendió un lote ante un retiro del mercado.")

h("Página 64  ·  Atomicidad y salto de maquetación  ★", size=15)
reloj("Tiempo  6:40 — 7:15")
cue("En pantalla: apartados 16.9 y 16.10.")

decir("El apartado nueve es la atomicidad, el requisito no funcional seis. Esa orden corre por "
      "omisión en simulación: crea el pedido completo, con líneas, movimientos y descuento de "
      "stock, y después revierte. Los pedidos ciento ochenta y cuatro y ciento ochenta y cinco se "
      "crearon así y no existen en la base.")

decir("El apartado diez es una medición que me desmintió. Yo daba el catálogo por libre de saltos "
      "de maquetación. Medido, el valor real era cero coma cuarenta y cuatro: casi el doble del "
      "umbral de «pobre».")

decir("No eran las imágenes: era el pie de página, que se pintaba a mitad de pantalla con la "
      "rejilla vacía y se iba fuera al llegar los productos. Con un esqueleto de carga bajó a "
      "cero coma cero quince.")

cue("AVISO: el pie de la Figura 43, en esta misma página, dice «98 pruebas». Es de cuando se tomó "
    "la captura. La cifra actual es 139.", AMBAR)

decir("Lo presento con la cifra mala delante porque la conclusión es la útil: compilar sin "
      "errores no es evidencia de que la pantalla funcione. Hoy la suite son ciento treinta y "
      "nueve pruebas, todas en verde.")

# =============================================================================
# CAPÍTULO XVII
# =============================================================================
h("Páginas 65 y 66  ·  XVII. Despliegue — Arquitectura y datos", size=15)
reloj("Tiempo  7:15 — 7:35")
cue("En pantalla: Tablas 54, 55 y 56.")

decir("Empieza el despliegue: la versión uno está en producción sobre Vercel, Render y Neon.")

decir("La tabla siguiente es la que destacaría: qué datos se llevaron a producción y cuáles no. "
      "Se llevó el catálogo; no los usuarios ni los pedidos reales, porque llevan datos de "
      "personas.")

h("Páginas 67 a 69  ·  Comprobaciones del despliegue", size=15)
reloj("Tiempo  7:35 — 7:50")
cue("Desplazar por las Figuras 44 a 47.")

opcional("Estas páginas son las comprobaciones: las rutas del portal desplegado, el historial de "
      "compilaciones en Vercel, la API respondiendo, y el servicio marcado como «Live» en Render.")

h("Página 70  ·  Decisiones del arranque", size=15)
reloj("Tiempo  7:50 — 8:00")

opcional("Esta página recoge las decisiones del arranque del contenedor: qué se ejecuta y en qué "
         "orden al levantar el servicio, y por qué el proceso muere si una migración falla en lugar "
         "de seguir adelante.")

h("Página 71  ·  Verificación de humo  ★", size=15)
reloj("Tiempo  8:00 — 8:25")
cue("En pantalla: apartado 17.5 y Figura 52. SEÑALAR EL 884.")

decir("Esta es la verificación de humo que pide la sesión dieciséis: catorce comprobaciones "
      "correctas de catorce.")

decir("Y la figura cincuenta y dos acredita lo más importante: el catálogo en producción devuelve "
      "ochocientos ochenta y cuatro productos. Antes del despliegue mostraba tres mil trescientos "
      "sesenta y uno. Esto demuestra que el catálogo depurado está en producción y no solo en mi "
      "equipo.")

opcional("Las rutas de gestión devuelven cuatrocientos uno sin token, y la mediana de respuesta es "
         "de trescientos sesenta y cinco milisegundos contra un umbral de dos segundos.")

h("Página 72  ·  El despliegue roto nueve días  ★", size=15)
reloj("Tiempo  8:25 — 9:05")
cue("En pantalla: Figura 53 y el apartado 17.6. ESTE ES EL MOMENTO FUERTE.")

decir("Arriba, el punto de venta contra la API desplegada: diez soles se descomponen en ocho "
      "cuarenta y siete de valor de venta y uno cincuenta y tres de IGV.")

decir("Y debajo está lo que de verdad enseñó este capítulo: un fallo.")

cue("Pausa breve antes de dar la causa.", AMBAR)

decir("Durante nueve días ningún despliegue llegó a producción, y el síntoma externo era "
      "inexistente: el servicio respondía doscientos a todo. La construcción fallaba, Render "
      "mantenía vivo el contenedor anterior, y desde fuera lo único observable era que el código "
      "nuevo no aparecía nunca.")

decir("La causa: una actualización de dependencias resuelta en un equipo con PHP ocho punto "
      "cuatro fijó paquetes que lo exigen, mientras el contenedor llevaba ocho punto tres. Y el "
      "manifiesto declaraba que admitía ocho punto dos, así que nada advertía hasta que la "
      "construcción abortaba.")

decir("Lo corregí en dos niveles: subir la imagen, y declarar en el manifiesto la plataforma de "
      "resolución, para que las dependencias se resuelvan siempre contra la versión del "
      "despliegue.")

decir("La lección quedó escrita: un servicio que responde doscientos no demuestra que el "
      "despliegue haya funcionado. Lo demuestra consultar algo que solo exista en la versión "
      "nueva, y por eso el script de verificación lo incluye.")

cue("La Figura 48, en esta misma página, muestra las 45 migraciones aplicadas en producción.")

# =============================================================================
# CAPÍTULO XVIII
# =============================================================================
h("Páginas 73 a 75  ·  XVIII. Retrospectiva del Sprint 4", size=15)
reloj("Tiempo  9:05 — 9:35")
cue("En pantalla: Tablas 59, 60 y 61, y las Figuras 49 y 50.")

decir("La retrospectiva se organiza en qué continuar, qué dejar y qué empezar. Destaco un "
      "aprendizaje que no es de programación.")

decir("Auditar los datos y no solo el código. El modelo era correcto y aun así el catálogo tenía "
      "ochocientos sesenta y nueve grupos duplicados por haberse importado cuatro veces. Ninguna "
      "revisión del esquema lo habría encontrado: lo encontró contar. Al resolverlo se "
      "descartaron doce mil quinientas sesenta y dos unidades de stock fantasma.")

opcional("El segundo es un obstáculo de entorno que costó un día: la ruta de este proyecto tiene "
         "ciento veinte caracteres y Windows trunca nombres de archivo al extraer los paquetes, sin "
         "dar ningún error. La suite abortaba señalando un problema de versiones inexistente. "
         "Documenté la solución y también los cinco intentos que no funcionaron.")

decir("Las figuras son el tablero de GitHub Projects al cierre del sprint y el pull request ya "
      "fusionado.")

# =============================================================================
# CAPÍTULO XIX Y CIERRE
# =============================================================================
h("Páginas 75 y 76  ·  XIX. Evidencias y XX. Conclusiones", size=15)
reloj("Tiempo  9:35 — 10:00")
cue("En pantalla: Tabla 62, el mapa de evidencias.")

decir("Esta tabla mapea cada evidencia con lo que demuestra: once artefactos, todos salida "
      "literal de una ejecución real y con la orden exacta que los reproduce.")

decir("Incluí las mediciones desfavorables junto a las favorables: la verificación de humo "
      "conserva la ejecución en la que cuatro comprobaciones fallaban, que es la que destapó los "
      "nueve días de despliegue roto.")

h("Páginas 77 y 78  ·  Referencias y diagrama completo", size=15)
reloj("Tiempo  10:00 — 10:10")
cue("Desplazar hasta el final. En la 78 está el diagrama entidad-relación completo.")

opcional("Cierran las referencias y el diagrama entidad-relación completo con las treinta y dos "
      "tablas. Después vienen los anexos, con el sistema capturado en la nube.")

opcional("Si quieren, puedo mostrarles los anexos C y D, que tienen el tablero, el inventario, el "
         "conteo por ciclos y la facturación capturados contra la base de producción.")

# =============================================================================
# CIERRE
# =============================================================================
h("Cierre", size=16)
reloj("Tiempo  10:10 — 10:40")
cue("Volver a la página 71 y dejar la Figura 52 en pantalla.")

decir("Para cerrar. El primer avance entregaba un plan; este entrega un sistema que se puede "
      "abrir ahora mismo en un navegador: vende, descuenta el stock del lote correcto y calcula "
      "el impuesto como lo calcula una boleta.")

decir("Y lo que me parece más importante no son las ciento treinta y nueve pruebas, sino que cada "
      "cifra de este informe lleva debajo el comando que la reproduce. Incluidas las que me "
      "desmintieron.")

cue("ÉNFASIS. Frase de cierre. Sin prisa, mirando a cámara.", AMBAR)

decir("Muchas gracias, profesora. Quedo atento a sus preguntas.")

# =============================================================================
# ANEXO DEL GUION: posibles preguntas
# =============================================================================
doc.add_page_break()
h("Anexo — respuestas rápidas a preguntas probables", size=16, espacio_antes=0)
nota("No se lee. Es para tenerlo a mano si la profesora pregunta.")

qa = [
    ("¿Por qué no se sumaron los stocks duplicados?",
     "Porque en 858 de los 869 grupos el stock era idéntico en todas las copias: misma mercancía "
     "reimportada, no entregas distintas. Sumarlos habría inflado el inventario de 4 434 a 16 975 "
     "unidades y el punto de venta habría prometido existencias inexistentes."),
    ("¿Cómo se eligió qué fila conservar al fusionar?",
     "Por actividad, no por identificador. Si exactamente una fila del grupo tenía movimientos o "
     "ventas, esa se conserva, tenga el id que tenga. Se midió: en los 11 grupos con stock "
     "discordante la fila viva era siempre la de menos stock, y en uno de ellos no era la de id "
     "menor."),
    ("¿El sistema ya factura electrónicamente ante SUNAT?",
     "No, y el informe lo dice. El comprobante se emite dentro del sistema con serie y número "
     "correlativo —boleta B001 o factura F001 según el documento—, pero el envío está simulado a "
     "la espera del certificado digital y las credenciales del operador. Nace con estado "
     "«pendiente»."),
    ("¿Por qué el aviso de receta médica no aparece en todos los productos?",
     "Porque es un dato regulatorio que sale del catálogo DIGEMID, que hoy tiene cinco filas de "
     "demostración. La función está implementada y probada; se marcaron 34 productos de cinco "
     "familias de antibióticos y corticoides para poder demostrarla, y queda declarado en el "
     "informe que es una marcación de demostración."),
    ("En la Figura 30 dice 3 361 productos y en la 52 dice 884. ¿Cuál es?",
     "884. La Figura 30 es una consulta ejecutada antes de la depuración del catálogo y se "
     "conserva como estaba, porque la captura muestra ese número. La cifra vigente, 884, aparece "
     "en el capítulo XVII y en los anexos, medida contra la base de producción."),
    ("La Figura 43 dice 98 pruebas. ¿No eran 139?",
     "Son 139 con 421 aserciones. Esa captura se tomó al cerrar el acceso de gestión, cuando la "
     "suite tenía 98. Desde entonces se añadieron las de trazabilidad de lotes, carrito y "
     "checkout. La cifra actual está en el apartado 16.7."),
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

h("Chuleta de cifras", size=14, espacio_antes=16)
nota("Por si se te va una en el momento.")

cifras = [
    ("Catálogo", "3 361 → 884 productos · 869 grupos duplicados · 12 562 unidades fantasma descartadas"),
    ("Base de datos", "45 migraciones · PostgreSQL 16 · 32 tablas · 31 claves foráneas · 79 índices"),
    ("API", "114 rutas: 95 protegidas (80 solo admin) · 19 públicas"),
    ("Pruebas", "139 pruebas · 421 aserciones · todas en verde"),
    ("FEFO", "Pedido 186: 9 unidades = 7 del lote que vence 31/03/2027 + 2 del de 15/01/2028"),
    ("Atomicidad", "Pedidos 184 y 185 creados en simulación: no existen tras revertir"),
    ("CLS", "0.44382 → 0.01524 (−96.6 %) · el pie de página aportaba 0.4237"),
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
