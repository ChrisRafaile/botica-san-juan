#!/usr/bin/env python3
"""
Ensambla el informe final (semana 18) a partir del APF3 entregado.

    python -X utf8 docs/scripts/ensamblar-informe-final-s18.py <APF3.docx> <salida.docx>

El APF3 es un registro historico: se lee y nunca se escribe. Cada sustitucion
comprueba el texto que espera encontrar y aborta si no esta, para que un APF3
distinto no produzca en silencio un informe a medio corregir.

El RUC y el titular que figuran en el APF3 se retiran del resultado, en el
texto y en las fotografias del anexo A donde el OCR los encontro, igual que el
numero de AnyDesk del equipo de la botica.
"""
from __future__ import annotations

import copy
import io
import re
import sys
from pathlib import Path

import docx
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor
from docx.table import Table
from docx.text.paragraph import Paragraph
from docx.text.run import Run
from PIL import Image, ImageDraw

RAIZ = Path(__file__).resolve().parents[2]
EVID = RAIZ / 'docs/evidencias/informe-final'
CORTES = RAIZ / 'docs/evidencias/cortes'
INFORME = RAIZ / 'docs/entregables/INFORME-FINAL-S18.md'
RUC = re.compile(r'\b[12]0\d{9}\b')

origen, salida = Path(sys.argv[1]), Path(sys.argv[2])
if origen.resolve() == salida.resolve():
    sys.exit('La salida no puede ser el propio APF3.')

d = docx.Document(str(origen))
B = list(d.element.body)  # indices del documento ORIGINAL
T = [e for e in B if e.tag == qn('w:tbl')]


def par(i: int) -> Paragraph:
    return Paragraph(B[i], d._body)


def fijar(p: Paragraph, texto: str) -> None:
    """Cambia el texto conservando el formato del primer tramo con texto."""
    con = [r for r in p.runs if r.text]
    if not con:
        p.add_run(texto)
        return
    con[0].text = texto
    for r in con[1:]:
        r.text = ''


def poner(i: int, espera: str, texto: str) -> None:
    p = par(i)
    assert espera in p.text, f'parrafo {i}: no contiene {espera!r}: {p.text[:80]!r}'
    fijar(p, texto)


def cambia(i: int, a: str, b: str) -> None:
    poner(i, a, par(i).text.replace(a, b))


def celda(t: int, f: int, c: int, espera: str, texto: str) -> None:
    cel = Table(T[t], d._body).rows[f].cells[c]
    assert espera in cel.text, f'tabla {t}[{f},{c}]: {cel.text[:80]!r}'
    fijar(cel.paragraphs[0], texto)
    for p in cel.paragraphs[1:]:
        p._p.getparent().remove(p._p)


# --------------------------------------------------------------- portada
poner(5, 'TERCER AVANCE', 'PROYECTO FINAL')
poner(6, 'Semana 13', 'Semana 18: Operación del Servicio · Sistema finalizado en v0.8.0')
for s in d.sections:
    for p in s.header.paragraphs:
        if 'APF3' in p.text:
            fijar(p, p.text.replace('APF3', 'Proyecto Final'))

# ------------------------------------------------------ datos reservados
for t in T:
    for row in Table(t, d._body).rows:
        for c in row.cells:
            for p in c.paragraphs:
                if RUC.search(p.text):
                    # Solo el dato: en el recuadro del anexo A va dentro de una
                    # frase que se conserva. En 1.1 el titular le sigue entre parentesis.
                    fijar(p, RUC.sub('[dato reservado]', re.sub(r'(' + RUC.pattern + r')\s*\([^)]*\)', lambda m: m[1], p.text)))
for e in B:
    if e.tag == qn('w:p'):
        p = Paragraph(e, d._body)
        if RUC.search(p.text):
            fijar(p, RUC.sub('[dato reservado]', p.text))

# Zonas con el RUC, el titular o el AnyDesk, en pixeles de la foto original. Se tapan con
# un rectangulo opaco: un desenfoque sobre digitos puede revertirse.
TAPAR = {
    'image74.jpg': [(0, 0, 280, 70)],
    'image76.jpg': [(0, 0, 280, 70)],
    'image85.jpg': [(0, 0, 220, 30)],
    'image82.jpg': [(0, 0, 290, 80), (865, 300, 1520, 368), (125, 495, 260, 960)],
    'image83.jpg': [(625, 285, 1262, 352)],
    'image84.jpg': [(845, 222, 1540, 262), (66, 445, 212, 880)],
}
tapadas = set()
for parte in d.part.package.iter_parts():
    nombre = str(parte.partname).rsplit('/', 1)[-1]
    if nombre in TAPAR:
        foto = Image.open(io.BytesIO(parte.blob)).convert('RGB')
        assert foto.size == (1600, 1204), f'{nombre}: no es la foto esperada'
        for caja in TAPAR[nombre]:
            ImageDraw.Draw(foto).rectangle(caja, fill=(70, 70, 70))
        buf = io.BytesIO()
        foto.save(buf, 'JPEG', quality=88)
        parte._blob = buf.getvalue()
        tapadas.add(nombre)
assert tapadas == set(TAPAR), f'sin tapar: {set(TAPAR) - tapadas}'

# -------------------------------------------- estado temporal: III a V, IX
cambia(337, 'al cierre del Sprint 2', 'al cierre del proyecto (v0.8.0)')
for f, hu in ((9, 'HU07'), (10, 'HU10'), (11, 'HU11'), (12, 'HU12')):
    assert Table(T[22], d._body).rows[f].cells[1].text == hu
    celda(22, f, 6, 'Pendiente', 'Terminado')
poner(358, 'se encuentra en ejecución', 'El proyecto está cerrado en v0.8.0 con todos sus Sprints ejecutados. Se conserva el Sprint Backlog de los tres primeros como registro de la planificación; el estado de cada tarea es el final.')
poner(371, 'en ejecución', '4.3. Sprint 3')
celda(26, 5, 3, 'En proceso', 'Terminado')
for f in (6, 7):
    celda(26, f, 3, 'Pendiente', 'Terminado')
celda(28, 1, 3, 'En curso', 'Cerrada con el Sprint 3')
celda(28, 6, 3, 'En curso', 'Cerrada: se sustituyó por integración propia')
for f, e in ((1, 'En entrega'), (2, 'Planificado'), (3, 'Planificado')):
    celda(29, f, 3, e, 'Entregado')
celda(29, 4, 3, 'Planificado', 'Finalizado · v0.8.0')
poner(485, 'EN DESARROLLO', 'IX. SISTEMA CONSTRUIDO')
txt = par(486).text
poner(486, 'Se distingue de forma', txt[:txt.index('Se distingue de forma')] + 'Las capturas de este capítulo son las del momento en que se construyó cada módulo; las de la versión final están en el capítulo XXVI. El detalle por componente se resume en la Tabla 25.')
poner(505, 'a la fecha', 'Componentes de la versión final')
celda(37, 1, 1, '46 migraciones', 'PostgreSQL 16 con 61 migraciones versionadas y 3 537 registros migrados en origen')
celda(37, 7, 1, '198 pruebas', '500 pruebas de backend (1 678 aserciones) y 209 de frontend, ejecutadas en la integración continua')
celda(37, 8, 1, 'Cliente conmutable', 'XML firmado, empaquetado, sobre SOAP y CDR; el envío recorre el camino real en modo simulado, sin remisión productiva a la SUNAT')
celda(37, 8, 2, 'En desarrollo', 'Operativa en modo simulado')
celda(44, 4, 3, 'en curso', 'Cliente conmutable implementado; la remisión productiva exige credenciales del contribuyente')
for f, caso in ((9, 'Suites EmpaquetadoSunatTest y EnvioAsincronoSunatTest'), (10, 'IndicadoresTest y exportación CSV')):
    celda(64, f, 2, 'Sin caso', caso)
    celda(64, f, 3, 'Pendiente', 'Cubierto por la suite')
    celda(64, f, 4, 'Programado', 'Pruebas automatizadas')
for i in range(722, 736):
    if B[i].tag == qn('w:p') and 'se declaran pendientes' in par(i).text:
        fijar(par(i), 'Las dos últimas filas no tienen caso manual ejecutado: las cubre la suite automatizada de los Sprints 5 y 6.')
cambia(684, '198 pruebas', '500 pruebas')
poner(737, '198 pruebas', 'La suite reúne 500 pruebas con 1 678 aserciones en el servidor, sobre PostgreSQL 16, y 209 pruebas en el frontend: 709 en total. No es una cifra decorativa: tres de los defectos corregidos los detectó la suite y no una revisión visual.')
for t in T:
    for row in Table(t, d._body).rows:
        for c in row.cells:
            for p in c.paragraphs:
                if '198 pruebas' in p.text:
                    fijar(p, re.sub(r'198 pruebas(,| y| con) 603 aserciones(, 13\.31 s)?', '500 pruebas y 1 678 aserciones', p.text))


# ------------------------------------------------ figuras recapturadas
def reemplaza_imagen(i_pie: int, espera: str, png: Path, pie: str) -> None:
    poner(i_pie, espera, pie)
    i = i_pie - 1
    while not B[i].findall('.//' + qn('a:blip')):
        i -= 1
    rid, imagen = d.part.get_or_add_image(str(png))
    B[i].findall('.//' + qn('a:blip'))[0].set(qn('r:embed'), rid)
    for ext in B[i].findall('.//' + qn('wp:extent')) + B[i].findall('.//' + qn('a:ext')):
        if ext.get('cx'):
            cx = int(ext.get('cx'))
            cy = int(cx * imagen.px_height / imagen.px_width)
            if cy > int(Cm(17)):
                cx, cy = int(cx * int(Cm(17)) / cy), int(Cm(17))
            ext.set('cx', str(cx))
            ext.set('cy', str(cy))


S500 = CORTES / 'CRT-43-suite-500.png'
reemplaza_imagen(452, 'Figura 11.', RAIZ / 'docs/evidencias/figura11/Figura11-modelo-de-datos.png', 'Figura 11. Modelo de datos conceptual (61 migraciones, contadas por script). Fuente: Elaboración propia.')
reemplaza_imagen(509, 'Figura 23.', S500, 'Figura 23. Ejecución final de la suite del Backend: 500 pruebas y 1 678 aserciones. Fuente: Elaboración propia.')
reemplaza_imagen(750, 'Figura 43.', CORTES / 'CRT-44-suite-frontend-209.png', 'Figura 43. Suite automatizada del Frontend: 209 pruebas en verde. La del Backend, con 500 pruebas y 1 678 aserciones, está en la Figura 23. Fuente: Elaboración propia.')
reemplaza_imagen(1241, 'Figura C.3', S500, 'Figura C.3. Las 500 pruebas con 1 678 aserciones ejecutadas sobre PostgreSQL 16. Fuente: Elaboración propia.')

# ------------------------------------------------ XX: cierre de la Tabla 64
poner(852, 'siguen abiertas', 'Este capítulo recoge las observaciones detectadas sobre los avances anteriores y el estado en que queda cada una. Las cuatro que el APF3 dejó abiertas se cierran aquí con su evidencia.')
poner(858, 'siguen abiertas', '20.2. Observaciones del APF3 cerradas')
poner(860, 'pendientes', 'Cierre de las observaciones O-B1 a O-B4')
celda(77, 0, 2, 'Por qué', 'Cómo se cerró')
for f, e, txt in (
    (1, 'Son capturas', 'CERRADA. Figuras 23, 43 y C.3 recapturadas con la ejecución final: 500 pruebas de backend (1 678 aserciones) y 209 de frontend. Evidencia: CRT-39 a CRT-44'),
    (2, 'El dato', 'CERRADA. Diagrama regenerado por script con la cifra contada: 61 migraciones. El contrato publicado declara OpenAPI 0.8.0'),
    (3, 'Decisión', 'CERRADA. Texto homologado en Inicio, Estadísticas, Sobre Nosotros y pie: más de 8 años de servicio y +800 productos'),
    (4, 'Todas las', 'CERRADA. La imagen de respaldo responde 200 y 195 productos del catálogo tienen fotografía real (194 medidos en producción); el resto muestra el respaldo'),
):
    celda(77, f, 2, e, txt)
celda(76, 9, 3, 'Figuras 54 a 62', 'Capturas de este capítulo')

# ------------------------------------------------------------ constructores
MODELO = T[77]
FALTAN: list[str] = []


def escribe(p: Paragraph, texto: str) -> None:
    """Texto con **negrita** y `codigo`, heredando el formato del parrafo."""
    modelo = copy.deepcopy(p.runs[0]._r) if p.runs else OxmlElement('w:r')
    for r in list(p.runs):
        p._p.remove(r._r)
    for hijo in list(modelo):
        if hijo.tag != qn('w:rPr'):
            modelo.remove(hijo)
    for trozo in re.split(r'(\*\*.+?\*\*)', texto):
        if not trozo:
            continue
        negrita = trozo.startswith('**') and trozo.endswith('**')
        r = copy.deepcopy(modelo)
        t = OxmlElement('w:t')
        t.set(qn('xml:space'), 'preserve')
        t.text = (trozo[2:-2] if negrita else trozo).replace('`', '')
        r.append(t)
        p._p.append(r)
        if negrita:
            Run(r, p).bold = True


def nueva_tabla(filas: list[list[str]], anchos: list[int]):
    """Clona el estilo de las tablas del informe."""
    tbl = OxmlElement('w:tbl')
    pr = copy.deepcopy(MODELO.find(qn('w:tblPr')))
    fijo = OxmlElement('w:tblLayout')
    fijo.set(qn('w:type'), 'fixed')
    pr.find(qn('w:tblBorders')).addnext(fijo)
    tbl.append(pr)
    grid = OxmlElement('w:tblGrid')
    for a in anchos:
        g = OxmlElement('w:gridCol')
        g.set(qn('w:w'), str(a))
        grid.append(g)
    tbl.append(grid)
    trs = MODELO.findall(qn('w:tr'))
    for k, fila in enumerate(filas):
        assert len(fila) == len(anchos), fila
        base = trs[min(k, 1)]
        tr = OxmlElement('w:tr')
        tr.append(copy.deepcopy(base.find(qn('w:trPr'))))
        for c, texto in enumerate(fila):
            origen_tc = base.findall(qn('w:tc'))[0 if k == 0 or c == 0 else 1]
            tc = copy.deepcopy(origen_tc)
            tc.find(qn('w:tcPr')).find(qn('w:tcW')).set(qn('w:w'), str(anchos[c]))
            ps = tc.findall(qn('w:p'))
            for extra in ps[1:]:
                tc.remove(extra)
            escribe(Paragraph(ps[0], d._body), texto)
            tr.append(tc)
        tbl.append(tr)
    return tbl


class Cursor:
    """Inserta elementos consecutivos delante de uno dado."""

    def __init__(self, antes):
        self.antes = antes

    def clon(self, modelo, texto: str) -> Paragraph:
        e = copy.deepcopy(modelo)
        for m in e.findall(qn('w:bookmarkStart')) + e.findall(qn('w:bookmarkEnd')):
            e.remove(m)
        self.antes.addprevious(e)
        p = Paragraph(e, d._body)
        escribe(p, texto)
        return p

    def h1(self, t):
        return self.clon(B[851], t)

    def h2(self, t):
        return self.clon(B[858], t)

    def h3(self, t):
        return self.clon(B[897], t)

    def normal(self, t):
        return self.clon(B[852], t)

    def vineta(self, t):
        # La lista del informe es numerada y continua: usarla aqui desplazaria
        # la numeracion de las conclusiones.
        p = self.clon(B[852], '• ' + t)
        p.paragraph_format.left_indent = Cm(0.8)
        return p

    def tabla(self, titulo, filas, anchos):
        self.clon(B[859], 'Tabla 0')
        self.clon(B[860], titulo)
        self.antes.addprevious(nueva_tabla(filas, anchos))
        self.clon(B[862], 'Nota. Elaboración propia.')

    def figura(self, png: Path, pie: str, hueco: str = ''):
        p = self.clon(B[862], ' ')
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.keep_with_next = True
        p.paragraph_format.line_spacing = 1.0
        if png.exists():
            p.add_run().add_picture(str(png), width=Cm(11.5 if png.name.startswith(('EF-', '1')) and not png.name.startswith(('16', '19')) else 15.5))
        else:
            r = p.add_run(f'[INSERTAR CAPTURA: {png.name} · {hueco}]')
            r.bold = True
            r.font.color.rgb = RGBColor(0xB0, 0x20, 0x20)
            r.font.size = Pt(11)
            FALTAN.append(png.name)
        self.clon(B[874], f'Figura 0. {pie} Fuente: Elaboración propia.')


def quitar(a: int, b: int) -> None:
    for e in B[a:b + 1]:
        e.getparent().remove(e)


def ficha(t: int) -> dict[str, str]:
    return {r.cells[0].text: r.cells[1].text for r in Table(T[t], d._body).rows}


def recorta(v: str, n: int = 1) -> str:
    return '. '.join(v.split('. ')[:n]).rstrip('.') + '.'


# ------------------------------------------------ XXI: solicitudes
fichas = [ficha(t) for t in range(80, 86)]
assert [f['Código'] for f in fichas] == [f'SOL-00{n}' for n in range(1, 7)]
poner(895, '21.3', '21.3. Resumen de las seis solicitudes y caso detallado')
poner(896, 'se recorre entero', 'Las seis solicitudes recorrieron los siete pasos del ciclo. Se resumen en una tabla y se detalla solo SOL-004, la única rechazada y la que mejor justifica el paso de autorización. Las fichas completas están en las issues #14 a #19 del repositorio.')
Cursor(B[897]).tabla(
    'Resumen de atención de las solicitudes',
    [['Código', 'Solicitante y prioridad', 'Requiere aprobación', 'Solución brindada', 'Estado']]
    + [[f['Código'], f"{f['Solicitante']} · {f['Prioridad']}", recorta(f['¿Requiere aprobación?']), recorta(f['Solución brindada']), f['Estado final'].split('. ')[0]] for f in fichas],
    [1000, 1900, 1500, 3470, 1200])
for i in (*range(897, 939), *range(953, 981)):
    B[i].getparent().remove(B[i])

# ------------------------------------------------ XXII: cambios
exp = [ficha(t) for t in range(94, 99)]


def paso(e: dict[str, str], n: int) -> str:
    return next(v for k, v in e.items() if k.startswith(str(n)))


poner(1040, '22.9', '22.9. Resumen de los expedientes de cambio')
poner(1041, 'Los seis pasos', 'Los seis pasos del procedimiento se recorrieron en los cinco cambios. Se resumen aquí; el caso detallado es CAM-001, desarrollado en el apartado 22.2.')
PENDIENTE = 'la migración sobre producción queda pendiente de ventana.'
assert PENDIENTE in paso(exp[2], 4)
Cursor(B[1042]).tabla(
    'Resumen de los expedientes CAM-001 a CAM-005',
    [['Código', 'Qué motivó el cambio', 'Prueba y vuelta atrás', 'Trazabilidad']]
    + [[f'CAM-00{n}', recorta(paso(e, 1)), paso(e, 4).replace(PENDIENTE, 'la migración se aplicó con el despliegue posterior.'), paso(e, 5)] for n, e in enumerate(exp, 1)],
    [1000, 2500, 3270, 2300])
quitar(1042, 1066)
cambia(1067, 'estas cinco fichas dejan', 'este resumen deja')
poner(1033, '22.8', '22.8. Cambios propuestos: estado al cierre')
poner(1034, 'no implementados', 'Estos cambios se registraron y analizaron por separado para que el registro del apartado 22.4 siga diciendo la verdad sobre lo que se hizo. CR-P01 se ejecutó en v0.7.0 (PR #61); CR-P02 y CR-P03 quedan como propuestas no ejecutadas.')
poner(1036, 'pendientes', 'Cambios propuestos y su estado al cierre del proyecto')

# ------------------------------------------------ XXV: compromisos
poner(1113, '25.4', '25.4. Cierre de los compromisos')
poner(1115, 'Sprint 7', 'Estado final de las acciones comprometidas')
celda(104, 0, 2, 'Indicador', 'Estado final')
for f, txt in (
    (1, 'Cumplida: figuras 23, 43 y C.3 recapturadas'),
    (2, 'Cumplida: figura 11 regenerada con 61 migraciones'),
    (3, 'Cumplida en parte: 195 productos con fotografía real'),
    (4, 'Cumplida en parte: AutorizacionRolTest y AccesoDocumentosTributariosTest'),
    (5, 'No ejecutada: RNF03 queda pendiente de prueba de carga'),
):
    celda(104, f, 2, '', txt)

# ------------------------------------------------ XXVI: operacion (28 a 34)
poner(1118, 'XXVI.', 'XXVII. CONCLUSIONES')
poner(1129, 'XXVII.', 'XXVIII. REFERENCIAS')
cur = Cursor(B[1118])
cur.h1('XXVI. OPERACIÓN DEL SISTEMA')
cur.normal('Este capítulo cubre los puntos 28 a 34 de la rúbrica. El sistema está finalizado en v0.8.0, desplegado y operado como servicio.')

md = INFORME.read_text(encoding='utf8')
md = md[md.index('## 28.'):]
# La 33.5 repite las figuras que aqui se insertan una a una, y la 33.4 es
# anterior a la prueba real de correo: la sustituye correos().
md = re.sub(r'### 33\.4\..*?(?=\n## 34)', '@@CORREOS@@\n', md, flags=re.S)
md = md.replace('ver 29.4', 'ver 26.2.4').replace('en las secciones **29**, **32**, **33** y **34**', 'en los apartados 26.2, 26.5, 26.6 y 26.7')
# La tabla del punto 28 es la Tabla 64: no se repite.
md = re.sub(r'La \*\*Tabla 64\*\* de.*?(?=\n> \*\*Nota)', 'Las cuatro observaciones abiertas del APF3 (O-B1 a O-B4) se cierran en la Tabla 64, apartado 20.2. Además se unificó el estado del documento a proyecto finalizado en v0.8.0 y se compactaron las fichas de solicitudes y los expedientes de cambio.\n', md, flags=re.S)
lineas, i, dg, seccion, sub = md.split('\n'), 0, 0, '', ''

FIGURAS = {
    '29': [('EF-01-salud.png', 'Monitoreo activo: /api/salud responde 200 OK.')],
    '30': [('EF-02-bitacora.png', 'Bitácora de operación con los tres niveles de evento.'),
           ('EF-03-alertas-fefo.png', 'Alertas de vencimiento y control FEFO.')],
    '33': [('EF-07-catalogo.png', 'Catálogo público.'),
           ('EF-08-checkout.png', 'Checkout del portal del cliente.'),
           ('EF-09-pos.png', 'Punto de venta visual.'),
           ('EF-10-asistente-importador.png', 'Asistente con importador de Excel y CSV.'),
           ('EF-11-openapi.png', 'Documentación OpenAPI 0.8.0.')],
}
TITULOS = {'28': 'Correcciones al APF3', '29': 'Indicadores, umbrales y segmentación', '30': 'Eventos de operación',
           '31': 'Gestión de incidentes', '32': 'Continuidad y simulacro de recuperación',
           '33': 'Resultados y requerimientos', '34': 'Recomendaciones'}
DIAGRAMAS = ['Monitoreo activo y pasivo.', 'Flujo de atención de incidentes en diez etapas.']


def limpia(t: str) -> str:
    t = re.sub(r'\[([^\]]+)\]\([^)]+\)', r'\1', t)
    return re.sub(r'(?<!\*)\*(?!\*)([^*]+?)\*(?!\*)', r'\1', t).strip()


def cierra_seccion() -> None:
    for png, pie in FIGURAS.get(seccion, []):
        cur.figura(EVID / png, pie)


def correos() -> None:
    cur.h3('26.6.4. Correos transaccionales verificados en producción')
    cur.normal('El 23 de noviembre de 2026 se ejecutaron contra producción los flujos de registro, recuperación de contraseña y compra. Resend informó como entregados los cuatro mensajes: confirmación de correo, bienvenida, recuperación y pedido registrado. Entregado no equivale a visible: Gmail dejó la bienvenida y el pedido en Recibidos y envió a Spam la confirmación y la recuperación, los dos mensajes cuyo enlace es imprescindible. Sin un dominio verificado, el remitente compartido onboarding@resend.dev solo entrega al titular de la cuenta; habilitar el envío a clientes exige verificar un dominio propio.')
    for png, pie, hueco in (
        ('12-resend-recuperacion-prod.png', 'Solicitud de recuperación de contraseña en producción.', ''),
        ('13-resend-bienvenida-prod.png', 'Alta de cuenta en producción, que dispara la bienvenida.', ''),
        ('14-resend-compra-prod.png', 'Pedido registrado en producción con su boleta.', ''),
        ('15-admin-eventos-resend-ok.png', 'Bitácora de producción: cuatro envíos registrados, sin advertencias ni errores.', '/admin/eventos en producción'),
        ('16-resend-dashboard-delivered.png', 'Panel de Resend con los cuatro envíos en estado Delivered.', 'https://resend.com/emails'),
        ('17-gmail-pedido.png', 'Correo de pedido registrado, abierto en Gmail.', 'correo del pedido'),
        ('18-gmail-bienvenida.png', 'Correo de bienvenida, abierto en Gmail.', 'correo de bienvenida'),
        ('19-gmail-recibidos.png', 'Bienvenida y pedido en la bandeja de Recibidos.', 'bandeja de Gmail'),
        ('20-gmail-spam-enlaces.png', 'Confirmación y recuperación clasificadas como Spam.', 'carpeta Spam'),
    ):
        cur.figura(EVID / png, pie, hueco)


while i < len(lineas):
    l = lineas[i]
    if l.startswith('## '):
        cierra_seccion()
        seccion, sub = l[3:5], ''
        cur.h2(f'26.{int(seccion) - 27}. ' + limpia(re.sub(r'^\d+\. ', '', l[3:])).replace('`', '') + f' (punto {seccion})')
    elif l.startswith('###'):
        sub = re.sub(r'^\d\d\.\d\. ', '', limpia(l.lstrip('# '))).replace('`', '')
        cur.h3(re.sub(r'^(\d\d)\.(\d)\.', lambda m: f'26.{int(m[1]) - 27}.{m[2]}.', limpia(l.lstrip('# '))))
    elif l.startswith('@@CORREOS@@'):
        correos()
    elif l.startswith('```mermaid'):
        while not lineas[i + 1].startswith('```'):
            i += 1
        i += 1
        dg += 1
        cur.figura(EVID / f'DG-0{dg}.png', DIAGRAMAS[dg - 1])
    elif l.startswith('|'):
        filas = []
        while i < len(lineas) and lineas[i].startswith('|'):
            c = [limpia(x) for x in re.split(r'(?<!\\)\|', lineas[i].strip())[1:-1]]
            if not all(re.fullmatch(r':?-+:?', x) for x in c):
                filas.append(c)
            i += 1
        i -= 1
        largo = [max(18, min(40, max(len(f[k]) for f in filas))) for k in range(len(filas[0]))]
        cur.tabla(sub or TITULOS[seccion], filas, [9070 * x // sum(largo) for x in largo])
    elif l.startswith('!['):
        pass  # las figuras se insertan al cerrar cada seccion
    elif re.match(r'^(- |\d+\. )', l):
        cur.vineta(limpia(re.sub(r'^(- |\d+\. )', '', l)))
    elif l.strip() and not l.startswith('---'):
        cur.normal(limpia(l.lstrip('> ')))
    i += 1
cierra_seccion()

# ------------------------------------------------ anexos
# Anexo A: una foto por hallazgo. La Tabla 1 remite a ellas por numero, asi que
# se numeran en el orden de los hallazgos. (hallazgo -> indice de la foto)
REPRESENTA = [1194, 1186, 1146, 1161, 1179, 1207, 1144, 1167]
ancla = B[1210]
assert not par(1209).text.strip()
B[1209].getparent().remove(B[1209])  # salto de pagina que precedia al anexo B
for i in range(1143, 1209):
    if i not in REPRESENTA and i - 1 not in REPRESENTA:
        B[i].getparent().remove(B[i])
for n, i in enumerate(REPRESENTA, 1):
    h = f'H-0{n}'
    pie = par(i + 1).text
    assert h in pie and B[i].findall('.//' + qn('a:blip')), f'{h}: {pie[:60]}'
    texto = re.sub(r'^Figura A\.\d+\.\s*', '', pie).split('Fuente:')[0].strip().rstrip('.')
    fijar(par(i + 1), f'Figura A.{n}. {texto}. Fuente: Registro fotográfico propio. Hallazgo: {h}.')
    ancla.addprevious(B[i])
    ancla.addprevious(B[i + 1])
    for k in (i, i + 1):
        par(k).paragraph_format.page_break_before = False
    par(i).paragraph_format.keep_with_next = True
    par(i + 1).paragraph_format.keep_with_next = False
    celda(1, n, 2, 'A.', f'Figura A.{n}')
    assert Table(T[1], d._body).rows[n].cells[0].text == h
poner(1141, 'Registro fotográfico', par(1141).text + ' Se conserva una fotografía representativa por hallazgo (H-01 a H-08).')

# Anexo C: C.3 repite la Figura 23 y C.6 y C.7 se ven en el capitulo XXVI.
for i in (*range(1239, 1242), *range(1247, 1255)):
    B[i].getparent().remove(B[i])
B[1246].addnext(B[1255])  # el pie de C.5 estaba separado de su imagen
fijar(par(1242), par(1242).text.replace('C.4', 'C.3'))
fijar(par(1244), par(1244).text.replace('C.4', 'C.3'))
fijar(par(1245), par(1245).text.replace('C.5', 'C.4'))
fijar(par(1255), par(1255).text.replace('C.5', 'C.4'))
celda(77, 1, 2, 'Figuras 23, 43 y C.3', 'CERRADA. Figuras 23 y 43 recapturadas con la ejecución final: 500 pruebas de backend (1 678 aserciones) y 209 de frontend; la C.3, duplicada, se retiró. Evidencia: CRT-39 a CRT-44')
celda(104, 1, 2, 'Cumplida', 'Cumplida: figuras 23 y 43 recapturadas')
for tabla in d.tables:
    for fila in tabla.rows:
        for c in fila.cells:
            for q in c.paragraphs:
                if 'Figura 43 · Anexo C' in q.text:
                    fijar(q, q.text.replace('Figura 43 · Anexo C', 'Figuras 23 y 43'))

# Anexo D: solo lo que no muestra ya el capitulo XXVI.
QUEDAN = [(1267, 1269), (1270, 1272), (1273, 1275), (1286, 1289)]
guardar = {i for a, b in QUEDAN for i in range(a, b + 1)}
for i in range(1258, 1294):
    if i not in guardar:
        B[i].getparent().remove(B[i])
for n, (a, b) in enumerate(QUEDAN, 1):
    fijar(par(a), re.sub(r'^D\.\d+', f'D.{n}', par(a).text))
    for i in range(a, b + 1):
        if par(i).text.startswith('Figura D.'):
            fijar(par(i), re.sub(r'^Figura D\.\d+', f'Figura D.{n}', par(i).text))
poner(1257, 'Capturas tomadas', 'Cuatro vistas que completan las del capítulo XXVI, donde están las de la versión final v0.8.0: catálogo, checkout, punto de venta, bitácora, alertas, asistente y OpenAPI. Se tomaron durante el desarrollo sobre la base real. El resto vive en docs/evidencias/ del repositorio.')

# ------------------------------------------------ maquetacion compacta
TOPE, ANCHO = int(Cm(6.9)), int(Cm(13))
for e in d.element.body.iter(qn('w:drawing')):
    ext = e.findall('.//' + qn('wp:extent'))
    if not ext:
        continue
    cx, cy = int(ext[0].get('cx')), int(ext[0].get('cy'))
    k = min(1, TOPE / cy, ANCHO / cx)
    if k < 1:
        for x in ext + e.findall('.//' + qn('a:ext')):
            if x.get('cx'):
                x.set('cx', str(int(cx * k)))
                x.set('cy', str(int(cy * k)))
PORTADA = set(B[:20])
for e in list(d.element.body):
    if e.tag != qn('w:p') or e in PORTADA:
        continue
    if e.findall('.//' + qn('a:blip')):
        # Al encoger, las que ocupaban todo el ancho quedaban a la izquierda.
        Paragraph(e, d._body).alignment = WD_ALIGN_PARAGRAPH.CENTER
        Paragraph(e, d._body).paragraph_format.first_line_indent = Cm(0)
        Paragraph(e, d._body).paragraph_format.left_indent = Cm(0)
    sp = e.find(qn('w:pPr') + '/' + qn('w:spacing'))
    if sp is None:
        continue
    if (sp.get(qn('w:line')) or '').isdigit() and int(sp.get(qn('w:line'))) > 250:
        sp.set(qn('w:line'), '250')
    for lado, tope in (('w:after', 60), ('w:before', 140)):
        if int(sp.get(qn(lado)) or 0) > tope and Paragraph(e, d._body).style.name != 'Heading 1':
            sp.set(qn(lado), str(tope))

# Indice a dos niveles: el tercero ocupaba dos paginas.
for it in d.element.body.iter(qn('w:instrText')):
    if 'TOC' in (it.text or ''):
        it.text = re.sub(r'"1-\d"', '"1-2"', it.text)
# Las celdas heredan un margen vertical amplio que alarga todas las tablas.
for mar in d.element.body.iter(qn('w:tcMar')):
    for lado in ('w:top', 'w:bottom'):
        m = mar.find(qn(lado))
        if m is not None and int(m.get(qn('w:w'))) > 30:
            m.set(qn('w:w'), '30')
for sp in d.element.body.iter(qn('w:spacing')):
    if sp.getparent().getparent().getparent().tag == qn('w:tc') and (sp.get(qn('w:line')) or '').isdigit() and int(sp.get(qn('w:line'))) > 240:
        sp.set(qn('w:line'), '240')

# ------------------------------------------------ renumeracion
# Las referencias cruzadas del APF3 apuntan solo a tablas <= 62 y figuras <= 60,
# anteriores a todo lo retirado: basta renumerar los rotulos.
nt = nf = 0
for e in list(d.element.body):
    if e.tag != qn('w:p'):
        continue
    p = Paragraph(e, d._body)
    if re.fullmatch(r'Tabla \d+', p.text.strip()):
        nt += 1
        fijar(p, f'Tabla {nt}')
        p.paragraph_format.keep_with_next = True
        sig = e.getnext()
        if sig is not None and sig.tag == qn('w:p'):
            Paragraph(sig, d._body).paragraph_format.keep_with_next = True
    elif re.match(r'Figura \d+\. ', p.text.strip()):
        nf += 1
        if not p.text.strip().startswith(f'Figura {nf}.'):
            fijar(p, re.sub(r'^\s*Figura \d+', f'Figura {nf}', p.text))

# Las imagenes añadidas reciben de python-docx un id que puede repetirse, y Word
# da por dañado un documento con dos docPr iguales.
for n, e in enumerate(d.element.body.iter(qn('wp:docPr')), 1):
    e.set('id', str(n))

d.core_properties.title = 'Proyecto Final - Sistema Web de Gestión y Control para la Botica San Juan'
d.core_properties.comments = 'Curso Integrador II: Sistemas - UTP'

# ------------------------------------------------ comprobaciones finales
textos = [p.text for p in d.paragraphs if not (p.style.name or '').startswith('toc')]
textos += [c.text for t in d.tables for r in t.rows for c in r.cells]
for t in textos:
    assert not RUC.search(t), 'queda un RUC en el texto'
    for frase in ('se encuentra en ejecución', '(en ejecución)', 'todavía en desarrollo', '198 pruebas', 'En entrega', '{{', 'Planificado'):
        assert frase not in t, f'queda {frase!r}: {t[:90]}'

# Lo retirado sale tambien del paquete: python-docx conserva la imagen aunque ya
# nadie la muestre, y seguiria dentro del archivo con los datos sin tapar.
xml = d.element.xml
for rid, rel in list(d.part.rels.items()):
    if rel.reltype.endswith('/image') and f'"{rid}"' not in xml:
        del d.part.rels[rid]

d.save(str(salida))
print(f'{salida.name}: {nt} tablas, {nf} figuras; capturas pendientes: {FALTAN or "ninguna"}')
