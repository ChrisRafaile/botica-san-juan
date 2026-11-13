#!/usr/bin/env python3
"""
Ensambla el Proyecto Final a partir del APF3 y del maestro de la Seccion V.

    python docs/scripts/ensamblar-proyecto-final.py \
        --apf3   "<ruta del APF3.docx>" \
        --maestro docs/SECCION-V-OPERACION.md \
        --salida "<ruta de Proyecto_Final_Botica_San_Juan.docx>" \
        --raiz   .

POR QUE SE EDITA UNA COPIA Y NO SE GENERA UN DOCUMENTO NUEVO

La portada de la UTP, los estilos institucionales, los encabezados, los
colores corporativos y el campo TOC viven en ese archivo. Regenerarlo desde
cero produciria un documento que dice lo mismo y no se parece en nada.

DONDE SE INSERTA Y POR QUE AHI

El APF3 termina en XXV, y luego XXVI. CONCLUSIONES, XXVII. REFERENCIAS y los
anexos. Las secciones nuevas van ANTES de las conclusiones: unas conclusiones
seguidas de siete capitulos mas dejarian de ser conclusiones.

Las conclusiones originales NO se borran. Pasan a ser el apartado 34.0 de la
nueva XXXIV, con una linea que dice de donde vienen. Borrar lo que se penso en
la semana 15 para que no contradiga lo que se piensa en la 18 seria esconder
la unica parte interesante: que el criterio cambio.

LAS CUATRO SALVAGUARDAS

  1. NO SOBRESCRIBE. Escribe a un temporal y renombra al final. Si algo
     revienta a mitad, la salida anterior sigue en pie.
  2. ES IDEMPOTENTE. Si la salida ya contiene una seccion XXVIII, aborta.
     Ejecutarlo dos veces no duplica el bloque.
  3. VERIFICA EL ORIGEN POR HASH. El APF3 esta calificado y no se toca; el
     script comprueba su SHA-256 y aborta si cambio.
  4. NO ESCRIBE CREDENCIALES. Rechaza el ensamblado si el maestro contiene
     algo con forma de RUC de once digitos o de clave de un proveedor.

FORMATO

Cada parrafo clona el formato mayoritario de su clase en el documento, medido
sobre el propio APF3 y no inventado:

    cuerpo         spacing after=140 line=300 auto, jc=both
    pie de figura  igual, jc=center
    titulo tabla   «Tabla NN» en negrita, before=240
    nombre tabla   cursiva, after=90
    nota           «Nota.» en cursiva, 9 pt, before=60 after=200
    tabla          ancho 9070 dxa, bordes B7C4CE, cabecera 1F6E4C en blanco

Un parrafo insertado sin eso nace a interlineado simple y se lee pegado al
siguiente.
"""
from __future__ import annotations

import argparse
import hashlib
import re
import shutil
import sys
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Pt, Emu

# SHA-256 del APF3 entregado y calificado. Si no coincide, alguien lo toco.
HASH_APF3 = '898FD718EAFC0E7B7D92BD20DBFBDDF8EAB7D9D34316FE3AA6AAE49DF064F93A'

ANCHO_TABLA_DXA = 9070
BORDE = 'B7C4CE'
CABECERA = '1F6E4C'

# El maestro lleva al final un apendice que es para el script, no para el
# informe. Todo lo que venga desde aqui se descarta.
CORTE_DEL_APENDICE = '## Apéndice del maestro'

# Un RUC peruano son once digitos empezando por 10, 15, 17 o 20. El del
# emisor no puede acabar en un archivo versionado ni en el informe.
PATRON_RUC = re.compile(r'\b(?:10|15|17|20)\d{9}\b')
PATRON_CLAVE = re.compile(r'\b(?:re_[A-Za-z0-9_]{12,}|sk_live_\w+|phc_[A-Za-z0-9]{20,})\b')
# Los ficticios que el proyecto usa a proposito en ejemplos y pruebas.
RUC_PERMITIDOS = {'20000000001'}


# ---------------------------------------------------------------------------
# Lectura del maestro
# ---------------------------------------------------------------------------

def leer_maestro(ruta: Path) -> list[dict]:
    """Convierte el Markdown en una lista de bloques tipados."""
    texto = ruta.read_text(encoding='utf-8')

    if CORTE_DEL_APENDICE in texto:
        texto = texto.split(CORTE_DEL_APENDICE)[0]

    # El preambulo del maestro —lo anterior al primer `# XXVIII`— explica el
    # propio archivo y no forma parte del informe.
    inicio = texto.find('# XXVIII.')
    if inicio == -1:
        raise SystemExit('El maestro no contiene la seccion XXVIII.')
    texto = texto[inicio:]

    comprobar_que_no_hay_credenciales(texto, ruta)

    bloques: list[dict] = []
    lineas = texto.splitlines()
    i = 0
    parrafo: list[str] = []

    def cerrar_parrafo() -> None:
        if parrafo:
            bloques.append({'tipo': 'cuerpo', 'texto': ' '.join(parrafo).strip()})
            parrafo.clear()

    while i < len(lineas):
        linea = lineas[i]
        pelada = linea.strip()

        if pelada in ('', '---'):
            cerrar_parrafo()
            i += 1
            continue

        if pelada.startswith('# '):
            cerrar_parrafo()
            bloques.append({'tipo': 'h1', 'texto': pelada[2:].strip()})
            i += 1
            continue

        if pelada.startswith('## '):
            cerrar_parrafo()
            bloques.append({'tipo': 'h2', 'texto': pelada[3:].strip()})
            i += 1
            continue

        if pelada.startswith('### '):
            cerrar_parrafo()
            bloques.append({'tipo': 'h3', 'texto': pelada[4:].strip()})
            i += 1
            continue

        # **Tabla 93**
        m = re.fullmatch(r'\*\*(Tabla \d+)\*\*', pelada)
        if m:
            cerrar_parrafo()
            titulo = lineas[i + 1].strip().strip('*') if i + 1 < len(lineas) else ''
            bloques.append({'tipo': 'rotulo_tabla', 'texto': m.group(1), 'nombre': titulo})
            i += 2
            continue

        # **Figura 74.** Pie...
        m = re.match(r'\*\*(Figura \d+)\.\*\*\s*(.*)', pelada)
        if m:
            cerrar_parrafo()
            pie = m.group(2)
            j = i + 1
            while j < len(lineas) and lineas[j].strip() and not lineas[j].strip().startswith('*Fuente:'):
                pie += ' ' + lineas[j].strip()
                j += 1
            fuente = ''
            if j < len(lineas) and lineas[j].strip().startswith('*Fuente:'):
                fuente = lineas[j].strip()
                j += 1
            archivo = ''
            m2 = re.search(r'`([^`]+)`', fuente)
            if m2:
                archivo = m2.group(1)
            bloques.append({
                'tipo': 'figura',
                'etiqueta': m.group(1),
                'pie': pie.strip(),
                'archivo': archivo,
            })
            i = j
            continue

        # *Nota.* ...
        if pelada.startswith('*Nota.*'):
            cerrar_parrafo()
            bloques.append({'tipo': 'nota', 'texto': pelada.replace('*Nota.*', '').strip()})
            i += 1
            continue

        # Bloque de codigo
        if pelada.startswith('```'):
            cerrar_parrafo()
            j = i + 1
            codigo: list[str] = []
            while j < len(lineas) and not lineas[j].strip().startswith('```'):
                codigo.append(lineas[j])
                j += 1
            bloques.append({'tipo': 'codigo', 'lineas': codigo})
            i = j + 1
            continue

        # Cita
        if pelada.startswith('> '):
            cerrar_parrafo()
            cita = [pelada[2:]]
            j = i + 1
            while j < len(lineas) and lineas[j].strip().startswith('> '):
                cita.append(lineas[j].strip()[2:])
                j += 1
            bloques.append({'tipo': 'cita', 'texto': ' '.join(cita).strip()})
            i = j
            continue

        # Tabla Markdown
        if pelada.startswith('|'):
            cerrar_parrafo()
            filas: list[list[str]] = []
            j = i
            while j < len(lineas) and lineas[j].strip().startswith('|'):
                cruda = lineas[j].strip()
                if not re.fullmatch(r'\|[\s:\-|]+\|', cruda):
                    filas.append([c.strip() for c in cruda.strip('|').split('|')])
                j += 1
            bloques.append({'tipo': 'tabla', 'filas': filas})
            i = j
            continue

        # Lista
        if re.match(r'^(\d+\.|[-*])\s+', pelada):
            cerrar_parrafo()
            bloques.append({'tipo': 'lista', 'texto': re.sub(r'^(\d+\.|[-*])\s+', '', pelada)})
            i += 1
            continue

        parrafo.append(pelada)
        i += 1

    cerrar_parrafo()
    return bloques


def comprobar_que_no_hay_credenciales(texto: str, ruta: Path) -> None:
    """Salvaguarda 4: el informe no lleva RUC reales ni claves."""
    encontrados = {r for r in PATRON_RUC.findall(texto) if r not in RUC_PERMITIDOS}
    if encontrados:
        raise SystemExit(
            f'ABORTADO: {ruta} contiene {len(encontrados)} cadena(s) con forma de RUC '
            'que no estan en la lista de ficticios. El informe no se ensambla.'
        )
    if PATRON_CLAVE.search(texto):
        raise SystemExit(f'ABORTADO: {ruta} contiene algo con forma de clave de proveedor.')


# ---------------------------------------------------------------------------
# Escritura con el formato del documento
# ---------------------------------------------------------------------------

def limpiar(texto: str) -> str:
    """Quita el marcado de enfasis que Word no entiende."""
    texto = re.sub(r'\*\*(.+?)\*\*', r'\1', texto)
    texto = re.sub(r'(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)', r'\1', texto)
    texto = texto.replace('`', '')
    return texto


def espaciado(parrafo, antes=None, despues=None, interlineado=None) -> None:
    pPr = parrafo._p.get_or_add_pPr()
    sp = OxmlElement('w:spacing')
    if antes is not None:
        sp.set(qn('w:before'), str(antes))
    if despues is not None:
        sp.set(qn('w:after'), str(despues))
    if interlineado is not None:
        sp.set(qn('w:line'), str(interlineado))
        sp.set(qn('w:lineRule'), 'auto')
    pPr.append(sp)


class Ensamblador:
    """Va dejando parrafos justo antes de un ancla del documento."""

    def __init__(self, documento: Document, ancla, raiz: Path):
        self.doc = documento
        self.ancla = ancla._p
        # Las rutas del maestro son relativas a la raiz del repositorio
        # (`docs/evidencias/...`), no a la carpeta de evidencias. Resolverlas
        # contra `docs/` daba `docs/docs/evidencias/...` y ninguna figura
        # existia; la salvaguarda lo detuvo en el primer intento.
        self.raiz = raiz
        self.figuras_puestas = 0
        self.tablas_puestas = 0
        self.figuras_sin_archivo: list[str] = []

    def _nuevo(self, estilo='Normal'):
        parrafo = self.doc.add_paragraph(style=estilo)
        self.ancla.addprevious(parrafo._p)
        return parrafo

    def titulo(self, texto: str, nivel: int):
        p = self._nuevo(f'Heading {nivel}')
        p.add_run(limpiar(texto))
        return p

    def cuerpo(self, texto: str, centrado=False, justificado=True):
        p = self._nuevo()
        p.add_run(limpiar(texto))
        espaciado(p, despues=140, interlineado=300)
        if centrado:
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        elif justificado:
            p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
        return p

    def lista(self, texto: str):
        p = self._nuevo('List Paragraph')
        p.add_run(limpiar(texto))
        espaciado(p, despues=80, interlineado=300)
        return p

    def cita(self, texto: str):
        p = self._nuevo()
        r = p.add_run(limpiar(texto))
        r.italic = True
        espaciado(p, antes=120, despues=160, interlineado=300)
        p.paragraph_format.left_indent = Emu(457200)
        return p

    def codigo(self, lineas: list[str]):
        for linea in lineas:
            p = self._nuevo()
            r = p.add_run(linea if linea.strip() else ' ')
            r.font.name = 'Consolas'
            r.font.size = Pt(8.5)
            espaciado(p, despues=0, interlineado=240)

    def rotulo_tabla(self, etiqueta: str, nombre: str):
        p = self._nuevo()
        p.add_run(etiqueta).bold = True
        espaciado(p, antes=240)

        q = self._nuevo()
        q.add_run(limpiar(nombre)).italic = True
        espaciado(q, despues=90)

    def nota(self, texto: str):
        p = self._nuevo()
        r = p.add_run('Nota. ')
        r.italic = True
        r.font.size = Pt(9)
        r2 = p.add_run(limpiar(texto))
        r2.font.size = Pt(9)
        espaciado(p, antes=60, despues=200)

    def tabla(self, filas: list[list[str]]):
        if not filas:
            return
        columnas = max(len(f) for f in filas)
        t = self.doc.add_table(rows=len(filas), cols=columnas)
        self._dar_formato_a_la_tabla(t)

        for i, fila in enumerate(filas):
            for j in range(columnas):
                celda = t.rows[i].cells[j]
                celda.text = ''
                p = celda.paragraphs[0]
                espaciado(p, antes=20, despues=20, interlineado=250)
                r = p.add_run(limpiar(fila[j]) if j < len(fila) else '')
                if i == 0:
                    r.bold = True
                    r.font.size = Pt(9)
                    r.font.color.rgb = None
                    self._pintar(celda, CABECERA)
                    self._blanco(r)
                    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
                else:
                    r.font.size = Pt(8.5)

        self.ancla.addprevious(t._tbl)
        self.tablas_puestas += 1

        # Word pega la tabla al parrafo siguiente si no hay nada en medio.
        hueco = self._nuevo()
        espaciado(hueco, despues=0)

    def _dar_formato_a_la_tabla(self, tabla) -> None:
        tblPr = tabla._tbl.tblPr

        ancho = OxmlElement('w:tblW')
        ancho.set(qn('w:w'), str(ANCHO_TABLA_DXA))
        ancho.set(qn('w:type'), 'dxa')
        tblPr.append(ancho)

        bordes = OxmlElement('w:tblBorders')
        for lado, grosor in (
            ('top', 4), ('left', 4), ('bottom', 4), ('right', 4),
            ('insideH', 3), ('insideV', 3),
        ):
            b = OxmlElement(f'w:{lado}')
            b.set(qn('w:val'), 'single')
            b.set(qn('w:sz'), str(grosor))
            b.set(qn('w:space'), '0')
            b.set(qn('w:color'), BORDE)
            bordes.append(b)
        tblPr.append(bordes)

    @staticmethod
    def _pintar(celda, color: str) -> None:
        shd = OxmlElement('w:shd')
        shd.set(qn('w:val'), 'clear')
        shd.set(qn('w:color'), 'auto')
        shd.set(qn('w:fill'), color)
        celda._tc.get_or_add_tcPr().append(shd)

    @staticmethod
    def _blanco(run) -> None:
        rPr = run._r.get_or_add_rPr()
        color = OxmlElement('w:color')
        color.set(qn('w:val'), 'FFFFFF')
        rPr.append(color)

    def figura(self, etiqueta: str, pie: str, archivo: str):
        ruta = self.raiz / archivo if archivo else None
        if ruta is None or not ruta.exists():
            self.figuras_sin_archivo.append(f'{etiqueta}: {archivo or "sin ruta"}')
            return

        p = self._nuevo()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        espaciado(p, antes=160, despues=60)
        p.add_run().add_picture(str(ruta), width=Emu(5486400))  # 6 pulgadas

        q = self._nuevo()
        q.alignment = WD_ALIGN_PARAGRAPH.CENTER
        espaciado(q, despues=140, interlineado=300)
        r = q.add_run(f'{etiqueta}. ')
        r.bold = True
        r.font.size = Pt(9)
        r2 = q.add_run(limpiar(pie))
        r2.font.size = Pt(9)

        self.figuras_puestas += 1


# ---------------------------------------------------------------------------
# Reubicacion de las conclusiones originales
# ---------------------------------------------------------------------------

def mover_conclusiones_originales(doc: Document, ancla_xxxiv) -> int:
    """
    Convierte la XXVI del APF3 en el apartado 34.0 de la nueva XXXIV.

    No se borra. Lo que se penso en la semana 15 y lo que se piensa en la 18
    puestos uno al lado del otro son el dato; quedarse solo con el segundo
    esconde que el criterio cambio.
    """
    # SE TRABAJA SOBRE REFERENCIAS AL ELEMENTO, NO SOBRE INDICES.
    #
    # `doc.paragraphs` se recalcula en cada acceso. La XXXIV queda ANTES de la
    # XXVI, asi que cada parrafo que se mueve se inserta por delante y empuja
    # un puesto a todo lo que viene detras, incluido el propio titulo XXVI.
    # La primera version guardaba el indice al principio y al final borraba
    # `doc.paragraphs[inicio]`, que para entonces era otro parrafo: el titulo
    # sobrevivia y desaparecia un parrafo que nadie habia pedido borrar.
    parrafos = doc.paragraphs
    indices = [
        i for i, p in enumerate(parrafos)
        if p.style.name == 'Heading 1' and p.text.strip().startswith('XXVI. CONCLUSIONES')
    ]
    if not indices:
        return 0

    inicio = indices[0]
    fin = next(
        (i for i in range(inicio + 1, len(parrafos))
         if parrafos[i].style.name == 'Heading 1'),
        len(parrafos),
    )

    titular = parrafos[inicio]._p
    cuerpo = [parrafos[i]._p for i in range(inicio + 1, fin)]

    cabecera = doc.add_paragraph(style='Heading 2')
    cabecera.add_run('34.0. Las conclusiones del APF3, sin retocar')
    ancla_xxxiv._p.addprevious(cabecera._p)

    aclaracion = doc.add_paragraph()
    r = aclaracion.add_run(
        'Lo que sigue se escribio en la semana 15 y se reproduce tal cual. '
        'Los apartados 34.1 a 34.7 lo revisan a la luz de lo que ocurrio '
        'despues; conservar las dos versiones permite ver que cambio y por que, '
        'que es mas util que una sola version corregida.'
    )
    r.italic = True
    espaciado(aclaracion, despues=140, interlineado=300)
    ancla_xxxiv._p.addprevious(aclaracion._p)

    for elemento in cuerpo:
        ancla_xxxiv._p.addprevious(elemento)

    # El titulo XXVI ya no hace falta: su contenido es ahora 34.0.
    titular.getparent().remove(titular)

    return len(cuerpo)


def sanear_el_ruc_heredado(doc: Document) -> int:
    """
    Sustituye el RUC real del emisor por el ficticio.

    POR QUE HACE FALTA, SI EL MAESTRO YA SE COMPRUEBA

    La salvaguarda 4 mira el maestro. Pero el RUC no viene del maestro: viene
    DEL PROPIO APF3, que lo lleva en el anexo desde que se entrego. El APF3 es
    un documento calificado y no se toca, asi que ahi se queda.

    El Proyecto Final es un ARCHIVO NUEVO, y la regla del proyecto no admite
    el RUC del emisor en ningun informe. Que el defecto se herede no lo
    convierte en aceptable: lo convierte en algo que hay que limpiar en la
    copia, que es justo lo que una copia permite hacer sin tocar el original.

    Se sustituye por `20000000001`, el ficticio que el proyecto ya usa, en vez
    de borrarlo: una frase a la que le falta el dato se lee como un error de
    redaccion, y la regla dice que si hace falta un ejemplo, sea ficticio.
    """
    sustituidos = 0

    def limpiar_parrafo(parrafo) -> int:
        hechos = 0
        for run in parrafo.runs:
            encontrados = [r for r in PATRON_RUC.findall(run.text) if r not in RUC_PERMITIDOS]
            if encontrados:
                nuevo = run.text
                for r in encontrados:
                    nuevo = nuevo.replace(r, '20000000001')
                run.text = nuevo
                hechos += len(encontrados)
        return hechos

    for parrafo in doc.paragraphs:
        sustituidos += limpiar_parrafo(parrafo)

    for tabla in doc.tables:
        for fila in tabla.rows:
            for celda in fila.cells:
                for parrafo in celda.paragraphs:
                    sustituidos += limpiar_parrafo(parrafo)

    return sustituidos


def marcar_indice_para_recalcular(doc: Document) -> None:
    """Word regenera el TOC al abrir, en vez de dejarlo mintiendo."""
    ajustes = doc.settings.element
    if ajustes.find(qn('w:updateFields')) is None:
        campo = OxmlElement('w:updateFields')
        campo.set(qn('w:val'), 'true')
        ajustes.append(campo)


# ---------------------------------------------------------------------------

def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--apf3', required=True, type=Path)
    ap.add_argument('--maestro', required=True, type=Path)
    ap.add_argument('--salida', required=True, type=Path)
    ap.add_argument('--raiz', required=True, type=Path,
                    help='Raiz del repositorio: las rutas de figura del maestro '
                         'se resuelven contra ella.')
    ap.add_argument('--sin-hash', action='store_true',
                    help='Omite la salvaguarda 3. Solo para probar el script.')
    args = ap.parse_args()

    # --- Salvaguarda 3: el origen no cambio -------------------------------
    real = hashlib.sha256(args.apf3.read_bytes()).hexdigest().upper()
    if real != HASH_APF3 and not args.sin_hash:
        print(f'ABORTADO: el APF3 no es el calificado.\n  esperado {HASH_APF3}\n  real     {real}')
        return 1
    print(f'APF3 verificado por hash: {real[:16]}...')

    # --- Salvaguarda 2: idempotencia --------------------------------------
    if args.salida.exists():
        previo = Document(str(args.salida))
        if any(p.text.strip().startswith('XXVIII.') and p.style.name == 'Heading 1'
               for p in previo.paragraphs):
            print('ABORTADO: la salida ya contiene la seccion XXVIII. '
                  'Borra el archivo si quieres rehacerlo.')
            return 1

    bloques = leer_maestro(args.maestro)
    print(f'Maestro leido: {len(bloques)} bloques.')

    # --- Salvaguarda 1: se trabaja sobre un temporal ----------------------
    temporal = args.salida.with_suffix('.docx.parcial')
    shutil.copy2(args.apf3, temporal)
    doc = Document(str(temporal))
    antes = len(doc.paragraphs)

    ancla = next(
        p for p in doc.paragraphs
        if p.style.name == 'Heading 1' and p.text.strip().startswith('XXVI. CONCLUSIONES')
    )

    e = Ensamblador(doc, ancla, args.raiz)
    ancla_xxxiv = None

    for b in bloques:
        if b['tipo'] == 'h1':
            p = e.titulo(b['texto'], 1)
            if b['texto'].startswith('XXXIV.'):
                ancla_xxxiv = p
        elif b['tipo'] == 'h2':
            e.titulo(b['texto'], 2)
        elif b['tipo'] == 'h3':
            e.titulo(b['texto'], 3)
        elif b['tipo'] == 'cuerpo':
            e.cuerpo(b['texto'])
        elif b['tipo'] == 'lista':
            e.lista(b['texto'])
        elif b['tipo'] == 'cita':
            e.cita(b['texto'])
        elif b['tipo'] == 'codigo':
            e.codigo(b['lineas'])
        elif b['tipo'] == 'rotulo_tabla':
            e.rotulo_tabla(b['texto'], b['nombre'])
        elif b['tipo'] == 'nota':
            e.nota(b['texto'])
        elif b['tipo'] == 'tabla':
            e.tabla(b['filas'])
        elif b['tipo'] == 'figura':
            e.figura(b['etiqueta'], b['pie'], b['archivo'])

    movidos = mover_conclusiones_originales(doc, ancla_xxxiv) if ancla_xxxiv else 0
    saneados = sanear_el_ruc_heredado(doc)
    marcar_indice_para_recalcular(doc)

    # Se comprueba ANTES de publicar. En la primera version este bloque estaba
    # despues del `replace`, asi que una corrida con figuras rotas devolvia 1 y
    # aun asi dejaba el documento publicado. Un error que no impide el efecto
    # que denuncia no sirve de nada.
    if e.figuras_sin_archivo:
        print('ABORTADO: faltan archivos de figura. No se publica nada.')
        for f in e.figuras_sin_archivo:
            print(f'    {f}')
        temporal.unlink(missing_ok=True)
        return 1

    doc.save(str(temporal))
    temporal.replace(args.salida)

    final = Document(str(args.salida))
    print('--- ensamblado ---')
    print(f'  parrafos    {antes} -> {len(final.paragraphs)}')
    print(f'  tablas      {len(final.tables)} (insertadas {e.tablas_puestas})')
    print(f'  figuras     insertadas {e.figuras_puestas}')
    print(f'  conclusiones del APF3 movidas a 34.0: {movidos} parrafos')
    print(f'  RUC del emisor heredado del APF3 y sustituido: {saneados} ocurrencia(s)')

    restante = [
        r for p in final.paragraphs for r in PATRON_RUC.findall(p.text)
        if r not in RUC_PERMITIDOS
    ] + [
        r for t in final.tables for f in t.rows for c in f.cells
        for r in PATRON_RUC.findall(c.text) if r not in RUC_PERMITIDOS
    ]
    if restante:
        print(f'  AVISO: quedan {len(restante)} cadena(s) con forma de RUC sin sanear.')
        return 1

    print(f'ENSAMBLADO_OK  {args.salida}')
    return 0


if __name__ == '__main__':
    sys.exit(main())
