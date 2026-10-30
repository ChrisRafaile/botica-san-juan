# -*- coding: utf-8 -*-
"""
Añade al informe el expediente de cada cambio y la evidencia de despliegue.

QUE AÑADE

1. Un apartado 22.9 con el expediente completo de CAM-001 a CAM-005. Cada
   cambio recorre los seis pasos que exige la práctica —selección, solicitud,
   evaluación de impacto, calendario y vuelta atrás, trazabilidad en Git y
   validación del pipeline— en una sola tabla por cambio.

2. Dos figuras de evidencia de producción en el capítulo del despliegue: el
   panel de despliegues de Vercel y la respuesta del servicio de salud de la
   API.

POR QUE UNA TABLA POR CAMBIO Y NO SEIS BLOQUES SUELTOS

Los seis pasos de un mismo cambio se leen juntos o no se leen. Repartirlos en
seis apartados obligaría a saltar de página para saber si el cambio que se
aprobó es el que se desplegó, que es justo la pregunta que el expediente
existe para responder.

QUE NO INVENTA

CAM-001 a CAM-004 no tienen Pull Request, y la tabla lo dice en lugar de
dejar la celda vacía o rellenarla con un número plausible. Esa carencia es un
hallazgo del propio procedimiento y está explicada en el apartado 22.7.
"""

import copy
import re
import sys
from pathlib import Path

from docx import Document
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm
from docx.table import Table
from docx.text.paragraph import Paragraph

if len(sys.argv) < 3:
    raise SystemExit('Uso: python expediente-de-cambios-docx.py <docx> <carpeta de capturas>')

RUTA = Path(sys.argv[1])
CAPTURAS = Path(sys.argv[2])
ANCHO_IMAGEN = Cm(14)
ANCHO_TABLA = 9070  # twips = 16 cm

ES_PIE = re.compile(r'^Figura (\d+)\.')
ES_ETIQUETA = re.compile(r'^Tabla (\d+)$')

PASOS = ['1 · Selección', '2 · Solicitud', '3 · Impacto y riesgo',
         '4 · Calendario, prueba y vuelta atrás', '5 · Trazabilidad', '6 · Validación en el pipeline']

INTRO = (
    'Los seis pasos que exige el procedimiento se recorren aquí cambio por cambio. Cada expediente se '
    'presenta en una sola tabla y no repartido en seis apartados, porque los seis pasos de un mismo '
    'cambio se leen juntos o no se leen: separarlos obligaría a saltar de página para comprobar si el '
    'cambio que se aprobó es el que se desplegó, que es exactamente la pregunta que un expediente '
    'existe para responder.'
)

CIERRE = (
    'Conviene detenerse en lo que estas cinco fichas dejan a la vista. Los cuatro primeros cambios no '
    'tienen Pull Request, y la celda correspondiente lo dice en lugar de quedarse vacía o rellenarse '
    'con un número plausible. Esa carencia no es un descuido de la documentación: es el hallazgo que '
    'motivó el cambio de flujo descrito en el apartado 22.7, y sería absurdo registrarlo y a la vez '
    'disimularlo. Los dos últimos cambios sí entraron por rama, revisión y pipeline en verde, que es '
    'como entrarán los siguientes.'
)

EXPEDIENTES = [
    ('22.9.1. CAM-001 · Parche del intérprete PHP', 'Expediente del cambio de emergencia CAM-001', [
        ('Paso', 'Qué se hizo y con qué evidencia'),
        (PASOS[0],
         'Aviso de seguridad de php.net sobre CVE-2026-109618 y CVE-2026-109619, CVSS 9.2, ejecución '
         'remota de código sin autenticar. No fue una mejora elegida: fue un riesgo activo sobre el '
         'servidor que atiende la API.'),
        (PASOS[1],
         'Motivo: cerrar una vulnerabilidad explotable sin credenciales. Alcance: imagen del '
         'contenedor, php.ini de producción y PHP local. Responsable: administrador del sistema. '
         'Prioridad: máxima. Tipo: emergencia.'),
        (PASOS[2],
         'Impacto en API: total, el fallo se dispara al parsear la petición, antes de que corra una '
         'línea de Laravel. Base de datos: ninguno. Front-End: ninguno. SUNAT: ninguno. Dependencias: '
         'imagen base del contenedor. Aprobación: propietario, con el riesgo explicado; por ser '
         'emergencia se ejecutó antes de documentar.'),
        (PASOS[3],
         'Ventana: inmediata, la única que admite una emergencia de seguridad. Prueba: servicio de '
         'salud en 200 y cabecera X-Powered-By leída del propio servidor. Vuelta atrás: reactivar el '
         'despliegue anterior desde Render, asumiendo que volvería a quedar expuesto, de modo que solo '
         'se usaría ante una caída del servicio.'),
        (PASOS[4], 'Rama main, por emergencia. Commit cf98072. Sin Pull Request: se ejecutó primero por urgencia.'),
        (PASOS[5],
         'Pipeline en verde sobre main tras el push. El comprobador de versión que dejó este cambio '
         'aborta además la construcción y el arranque sobre un intérprete vulnerable.'),
    ]),

    ('22.9.2. CAM-002 · Propiedad de los comprobantes', 'Expediente del cambio CAM-002', [
        ('Paso', 'Qué se hizo y con qué evidencia'),
        (PASOS[0],
         'Las rutas de descarga de XML y PDF no comprobaban de quién era el comprobante: cualquier '
         'cuenta autenticada podía descargar el de otra persona. Es la reaparición del defecto D-05 '
         'del avance anterior.'),
        (PASOS[1],
         'Motivo: datos personales de clientes expuestos entre cuentas. Alcance: política, rutas y '
         'controlador del portal. Responsable: administrador del sistema. Prioridad: alta. Tipo: '
         'normal, tratado con urgencia.'),
        (PASOS[2],
         'Impacto en API: alto. Base de datos: ninguno. Front-End: el portal del cliente consume la '
         'misma regla. SUNAT: ninguno. Riesgo del cambio: medio, una política mal escrita dejaría sin '
         'acceso al dueño legítimo. Aprobación: propietario.'),
        (PASOS[3],
         'Ventana ordinaria. Prueba: ocho casos en ComprobantesDelClienteTest, 200 para el titular y '
         '403 para otra cuenta. Vuelta atrás: revertir el commit, con la salvedad de que el estado '
         'anterior es el vulnerable.'),
        (PASOS[4], 'Rama develop. Commit 2315149. Sin Pull Request.'),
        (PASOS[5], 'Pipeline en verde; las ocho pruebas nuevas corren dentro del trabajo backend-tests.'),
    ]),

    ('22.9.3. CAM-003 · Contingencia offline del punto de venta', 'Expediente del cambio CAM-003', [
        ('Paso', 'Qué se hizo y con qué evidencia'),
        (PASOS[0],
         'La botica está en una zona donde el internet se cae. Sin red el punto de venta dejaba de '
         'funcionar y la caja se atendía en papel, que es el estado del que venía huyendo el proyecto.'),
        (PASOS[1],
         'Motivo: que cobrar no dependa de la conexión. Alcance: dos migraciones, endpoint de '
         'sincronización, almacén local y cola en la caja. Responsable: administrador del sistema. '
         'Prioridad: alta. Tipo: normal.'),
        (PASOS[2],
         'Impacto en base de datos: dos columnas nuevas y una tabla de bitácora. API: endpoint nuevo, '
         'aditivo. Front-End: toda la operación de caja. SUNAT: ninguno directo, pero condiciona el '
         'comprobante de contingencia. Riesgo: alto, duplicar ventas o comprobantes es una '
         'contingencia tributaria. Aprobación: propietario.'),
        (PASOS[3],
         'Ventana ordinaria; la migración sobre producción queda pendiente de ventana. Prueba: 14 '
         'casos de sincronización en el servidor y 24 en la caja, más el ensayo de continuidad del '
         '24/10 con corte y restablecimiento reales. Vuelta atrás: las migraciones tienen down() y el '
         'endpoint es aditivo, así que revertir no rompe lo que ya funcionaba.'),
        (PASOS[4], 'Rama develop. Commits 3174100, 067e333, 429bbae y 65a03e0. Sin Pull Request.'),
        (PASOS[5], 'Pipeline en verde en los cuatro push; el despliegue en Vercel registra los mismos hashes.'),
    ]),

    ('22.9.4. CAM-004 · Búsqueda de productos sin conexión', 'Expediente del cambio CAM-004', [
        ('Paso', 'Qué se hizo y con qué evidencia'),
        (PASOS[0],
         'El ensayo de continuidad demostró que la venta se guardaba bien, pero el vendedor no podía '
         'encontrar el producto para armarla: la búsqueda seguía llamando a la API. Guardar la venta '
         'sirve de poco si no se puede componer.'),
        (PASOS[1],
         'Motivo: cerrar el ciclo de venta sin conexión. Alcance: endpoint de catálogo y búsqueda '
         'híbrida en la caja. Responsable: administrador del sistema. Prioridad: alta. Tipo: normal.'),
        (PASOS[2],
         'Impacto en API: endpoint nuevo de solo lectura. Base de datos: ninguno. Front-End: pantalla '
         'de caja. SUNAT: ninguno. Riesgo: bajo, si el catálogo local falla la búsqueda queda como '
         'estaba, sin resultados. Aprobación: propietario.'),
        (PASOS[3],
         'Ventana ordinaria. Prueba: nueve casos de catálogo local y tres del endpoint; el stock se '
         'declara «sin verificar» en lugar de inventarse. Vuelta atrás: revertir el commit; la '
         'búsqueda vuelve a depender de la red.'),
        (PASOS[4], 'Rama develop. Commit 0002075. Sin Pull Request.'),
        (PASOS[5], 'Pipeline en verde sobre main; despliegue correspondiente registrado en Vercel.'),
    ]),

    ('22.9.5. CAM-005 · Plan de control de cambios e integración continua',
     'Expediente del cambio CAM-005', [
        ('Paso', 'Qué se hizo y con qué evidencia'),
        (PASOS[0],
         'El proyecto tenía control de cambios en la práctica pero no documentado como procedimiento, '
         'y sobre todo no vinculaba cada cambio con su rama, su commit y su revisión.'),
        (PASOS[1],
         'Motivo: dejar el procedimiento por escrito y con trazabilidad. Alcance: documentación del '
         'repositorio y capítulo del informe. Responsable: administrador del sistema. Prioridad: '
         'media. Tipo: normal.'),
        (PASOS[2],
         'Impacto en base de datos, API, Front-End y SUNAT: ninguno; el cambio solo toca '
         'documentación. Dependencias: ninguna. Aprobación: propietario.'),
        (PASOS[3],
         'Ventana ordinaria. Prueba: el pipeline debía pasar en verde sobre el propio Pull Request. '
         'Vuelta atrás: revertir el commit de fusión.'),
        (PASOS[4],
         'Rama docs/CAM-005-plan-control-cambios. Commits 6f83586 y fa4eddf. Pull Request #20, '
         'fusionado tras pasar las comprobaciones.'),
        (PASOS[5],
         'Pipeline disparado por el evento pull_request: escaneo de secretos, pruebas del servidor y '
         'compilación del Front-End, los tres en verde antes de fusionar. Es la evidencia de las '
         'figuras del apartado 22.6.'),
    ]),
]

FIGURAS_DESPLIEGUE = [
    ('DEP-01-vercel-deployments.jpg',
     'Panel de despliegues del portal en Vercel. Cada despliegue de producción queda asociado al '
     'commit que lo originó, de modo que los hashes del registro de cambios se pueden seguir hasta '
     'la versión publicada.'),
    ('DEP-02-api-salud-produccion.jpg',
     'Respuesta del servicio de salud de la API desplegada en Render: el servicio responde y '
     'confirma su conexión con la base de datos.'),
]

TEXTO_DESPLIEGUE = (
    'La comprobación de que el sistema está en servicio no se apoya en el panel de la plataforma, que '
    'solo acredita que la construcción terminó, sino en lo que responde el sistema desplegado. Las dos '
    'figuras siguientes recogen las dos caras de esa comprobación: el rastro de cada despliegue hasta '
    'el commit que lo originó, y la respuesta del propio servicio.'
)


def bloques(doc):
    salida = []
    for hijo in doc.element.body.iterchildren():
        if hijo.tag == qn('w:p'):
            salida.append(Paragraph(hijo, doc))
        elif hijo.tag == qn('w:tbl'):
            salida.append(Table(hijo, doc))
    return salida


def poner_texto(parrafo, texto):
    runs = parrafo.runs
    if not runs:
        parrafo.add_run(texto)
        return
    runs[0].text = texto
    for sobrante in runs[1:]:
        sobrante._element.getparent().remove(sobrante._element)


def celda_texto(celda, texto):
    for extra in celda.paragraphs[1:]:
        extra._element.getparent().remove(extra._element)
    poner_texto(celda.paragraphs[0], texto)


def ajustar_columnas(tabla, proporciones):
    anchos = [round(ANCHO_TABLA * x / 100) for x in proporciones]
    anchos[-1] += ANCHO_TABLA - sum(anchos)

    rejilla = tabla._tbl.find(qn('w:tblGrid'))
    for columna in list(rejilla):
        rejilla.remove(columna)
    for ancho in anchos:
        col = OxmlElement('w:gridCol')
        col.set(qn('w:w'), str(ancho))
        rejilla.append(col)

    for fila in tabla.rows:
        for celda, ancho in zip(fila.cells, anchos):
            tcPr = celda._tc.get_or_add_tcPr()
            for viejo in tcPr.findall(qn('w:tcW')):
                tcPr.remove(viejo)
            tcW = OxmlElement('w:tcW')
            tcW.set(qn('w:w'), str(ancho))
            tcW.set(qn('w:type'), 'dxa')
            tcPr.append(tcW)


def main():
    doc = Document(str(RUTA))
    items = bloques(doc)

    i_cap = next(i for i, it in enumerate(items)
                 if isinstance(it, Paragraph) and it.style.name.startswith('Heading 1')
                 and it.text.strip().startswith('XXII.'))
    i_sig = next(i for i, it in enumerate(items)
                 if isinstance(it, Paragraph) and it.style.name.startswith('Heading 1')
                 and it.text.strip().startswith('XXIII.'))

    modelo_h2 = next(it for it in items[i_cap:i_sig]
                     if isinstance(it, Paragraph) and it.style.name.startswith('Heading 2'))
    modelo_etiqueta = next(it for it in items[i_cap:i_sig]
                           if isinstance(it, Paragraph) and ES_ETIQUETA.match(it.text.strip()))
    i_eti = items.index(modelo_etiqueta)
    modelo_leyenda = items[i_eti + 1]
    modelo_tabla = items[i_eti + 2]
    modelo_nota = items[i_eti + 3]
    modelo_cuerpo = next(it for it in items[i_cap:i_sig]
                         if isinstance(it, Paragraph) and it.style.name == 'Normal'
                         and len(it.text.strip()) > 150 and it.runs)
    modelo_pie = next(it for it in items
                      if isinstance(it, Paragraph) and ES_PIE.match(it.text.strip()))
    modelo_imagen = items[items.index(modelo_pie) - 1]

    def insertar(destino, modelo, texto=None):
        nuevo = copy.deepcopy(modelo._p if isinstance(modelo, Paragraph) else modelo._tbl)
        destino.addprevious(nuevo)
        if isinstance(modelo, Paragraph):
            p = Paragraph(nuevo, modelo._parent)
            if texto is not None:
                poner_texto(p, texto)
            return p
        return Table(nuevo, modelo._parent)

    def insertar_tabla(destino, leyenda, filas, proporciones):
        insertar(destino, modelo_etiqueta, 'Tabla 9000')
        insertar(destino, modelo_leyenda, leyenda)

        tabla = insertar(destino, modelo_tabla)
        columnas = len(filas[0])

        while len(tabla.columns) > columnas:
            for fila in tabla.rows:
                celda = fila._tr.tc_lst[-1]
                celda.getparent().remove(celda)
            rejilla = tabla._tbl.find(qn('w:tblGrid'))
            if rejilla is not None and len(rejilla) > columnas:
                rejilla.remove(rejilla[-1])
        while len(tabla.columns) < columnas:
            for fila in tabla.rows:
                fila._tr.append(copy.deepcopy(fila._tr.tc_lst[-1]))
            rejilla = tabla._tbl.find(qn('w:tblGrid'))
            if rejilla is not None:
                rejilla.append(copy.deepcopy(rejilla[-1]))

        while len(tabla.rows) > len(filas):
            ultima = tabla.rows[-1]._tr
            ultima.getparent().remove(ultima)
        while len(tabla.rows) < len(filas):
            tabla.rows[-1]._tr.addnext(copy.deepcopy(tabla.rows[-1]._tr))

        for fila_tabla, valores in zip(tabla.rows, filas):
            for celda, valor in zip(fila_tabla.cells, valores):
                celda_texto(celda, valor)

        insertar(destino, modelo_nota, 'Nota. Elaboración propia.')
        ajustar_columnas(tabla, proporciones)

    # --- 22.9, al final del capítulo XXII ---------------------------------
    destino = items[i_sig]._p
    insertar(destino, modelo_h2, '22.9. Expediente de cada cambio, paso a paso')
    insertar(destino, modelo_cuerpo, INTRO)

    for titulo, leyenda, filas in EXPEDIENTES:
        insertar(destino, modelo_h2, titulo)
        insertar_tabla(destino, leyenda, filas, [22, 78])

    insertar(destino, modelo_cuerpo, CIERRE)

    # --- figuras de despliegue, en el capítulo XXIV ------------------------
    items = bloques(doc)
    i_xxv = next(i for i, it in enumerate(items)
                 if isinstance(it, Paragraph) and it.style.name.startswith('Heading 1')
                 and it.text.strip().startswith('XXV.'))
    destino_dep = items[i_xxv]._p

    insertar(destino_dep, modelo_cuerpo, TEXTO_DESPLIEGUE)

    for archivo, leyenda in FIGURAS_DESPLIEGUE:
        ruta = CAPTURAS / archivo
        if not ruta.exists():
            print(f'   aviso: falta {ruta}, se omite la figura')
            continue

        parrafo_img = doc.add_paragraph()
        marcador = copy.deepcopy(modelo_imagen._p)
        parrafo_img._p.addprevious(marcador)
        parrafo_img.add_run().add_picture(str(ruta), width=ANCHO_IMAGEN)

        pPr = marcador.find(qn('w:pPr'))
        if pPr is not None:
            viejo = parrafo_img._p.find(qn('w:pPr'))
            if viejo is not None:
                parrafo_img._p.remove(viejo)
            parrafo_img._p.insert(0, copy.deepcopy(pPr))
        marcador.getparent().remove(marcador)

        destino_dep.addprevious(parrafo_img._p)
        pie = insertar(destino_dep, modelo_pie)
        poner_texto(pie, f'Figura 9000. {leyenda} Fuente: Elaboración propia.')

    # --- renumeración ------------------------------------------------------
    etiquetas = [p for p in bloques(doc)
                 if isinstance(p, Paragraph) and ES_ETIQUETA.match(p.text.strip())]
    mapa_tablas = {int(ES_ETIQUETA.match(p.text.strip()).group(1)): i
                   for i, p in enumerate(etiquetas, start=1)
                   if int(ES_ETIQUETA.match(p.text.strip()).group(1)) < 9000}
    for posicion, p in enumerate(etiquetas, start=1):
        poner_texto(p, f'Tabla {posicion}')

    pies = [p for p in bloques(doc)
            if isinstance(p, Paragraph) and ES_PIE.match(p.text.strip())]
    mapa_figuras = {int(ES_PIE.match(p.text.strip()).group(1)): i
                    for i, p in enumerate(pies, start=1)
                    if int(ES_PIE.match(p.text.strip()).group(1)) < 9000}
    for posicion, p in enumerate(pies, start=1):
        poner_texto(p, ES_PIE.sub(f'Figura {posicion}.', p.text.strip(), count=1))

    def recorrer(doc):
        for p in doc.paragraphs:
            yield p
        for t in doc.tables:
            for fila in t.rows:
                for celda in fila.cells:
                    for p in celda.paragraphs:
                        yield p

    refs = 0
    for p in recorrer(doc):
        texto = p.text
        if ES_PIE.match(texto.strip()) or ES_ETIQUETA.match(texto.strip()):
            continue
        nuevo = re.sub(r'Tabla (\d+)',
                       lambda m: f'Tabla {mapa_tablas.get(int(m.group(1)), int(m.group(1)))}', texto)
        nuevo = re.sub(r'Figura (\d+)',
                       lambda m: f'Figura {mapa_figuras.get(int(m.group(1)), int(m.group(1)))}', nuevo)
        if nuevo != texto:
            poner_texto(p, nuevo)
            refs += 1

    n_tablas = [int(ES_ETIQUETA.match(p.text.strip()).group(1)) for p in bloques(doc)
                if isinstance(p, Paragraph) and ES_ETIQUETA.match(p.text.strip())]
    n_figuras = [int(ES_PIE.match(p.text.strip()).group(1)) for p in bloques(doc)
                 if isinstance(p, Paragraph) and ES_PIE.match(p.text.strip())]

    if n_tablas != list(range(1, len(n_tablas) + 1)):
        raise SystemExit(f'Tablas no correlativas: {n_tablas}')
    if n_figuras != list(range(1, len(n_figuras) + 1)):
        raise SystemExit(f'Figuras no correlativas: {n_figuras}')

    doc.save(str(RUTA))
    print(f'Guardado. Tablas: {len(n_tablas)} · Figuras: {len(n_figuras)} · Referencias: {refs}')


if __name__ == '__main__':
    main()
