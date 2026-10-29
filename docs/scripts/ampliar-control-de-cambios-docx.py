# -*- coding: utf-8 -*-
"""
Amplía el capítulo de control de cambios del informe con lo que pide el
material de la Semana 11 (sesiones 21-22).

QUE AÑADE

El capítulo traía la clasificación de cambios y el caso CAM-001. Faltaba lo
que el material pide de forma explícita: la plantilla con la que se registra
una solicitud de cambio, el registro completo, el calendario de ventanas, la
vinculación de cada cambio con su rama y su Pull Request, y la evidencia de
integración continua.

COMO CONSERVA EL FORMATO

Clona los párrafos y tablas que el propio capítulo ya usa, igual que el
script de las solicitudes de servicio. Clonar arrastra el pPr y el rPr
originales, que es lo único que garantiza que el texto nuevo salga con la
misma tipografía y el mismo interlineado que el resto del informe.

RENUMERACION

Al terminar renumera TODAS las etiquetas de tabla y TODOS los pies de figura
por orden de aparición, y reescribe las referencias cruzadas del texto con el
mismo mapa, en una sola pasada para que dos reemplazos no se pisen.
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
    raise SystemExit('Uso: python ampliar-control-de-cambios-docx.py <docx> <carpeta de capturas>')

RUTA = Path(sys.argv[1])
CAPTURAS = Path(sys.argv[2])
ANCHO = Cm(14)

# 9070 twips = 16 cm, el ancho util de la caja de texto del informe.
ANCHO_TABLA = 9070

ES_PIE = re.compile(r'^Figura (\d+)\.')
ES_ETIQUETA = re.compile(r'^Tabla (\d+)$')


# ---------------------------------------------------------------- contenido

INTRO_CR = (
    'Una solicitud de cambio no es un recado: es el documento que permite responder, antes de tocar '
    'nada, qué cambia, por qué, quién lo aprueba, cómo se prueba y cómo se deshace. La regla que '
    'gobierna el procedimiento se enuncia mejor por su contrapositivo: un cambio que no se puede '
    'describir ni revertir no pasa a producción.'
)

PLANTILLA_CR = [
    ('Campo', 'Qué tiene que responder'),
    ('Código', 'Identificador CAM-xxx con el que se sigue el cambio'),
    ('Cambio solicitado', 'Qué se modifica, en una frase'),
    ('Motivo', 'Por qué hace falta'),
    ('Tipo', 'Estándar, normal o de emergencia'),
    ('Componentes afectados', 'Front-End, API, base de datos, infraestructura o documentación'),
    ('Impacto', 'Qué usuarios, módulos o datos se ven afectados'),
    ('Riesgo', 'Qué puede salir mal y cuán grave sería'),
    ('Dependencias', 'Si exige base de datos, proveedor u otro equipo'),
    ('Autorización', 'Quién aprueba'),
    ('Prueba', 'Cómo se comprueba que el cambio funcionó'),
    ('Vuelta atrás', 'Cómo se deshace si falla'),
    ('Fecha propuesta', 'Ventana del calendario de cambios'),
    ('Rama, commit y PR', 'Dónde está el trabajo real'),
    ('Cierre', 'Evidencia adjunta'),
]

INTRO_REGISTRO = (
    'El registro recoge los cambios efectivamente ejecutados sobre el servicio. Se mantiene separado '
    'del listado de cambios propuestos del apartado 22.8 a propósito: un plan de control de cambios '
    'que mezcla lo hecho con lo deseado deja de servir para rendir cuentas.'
)

REGISTRO = [
    ('Código', 'Tipo', 'Cambio', 'Riesgo asumido', 'Cierre'),
    ('CAM-001', 'Emergencia', 'Parche del intérprete PHP ante CVE-2026-109618 y 109619',
     'Bajo frente al de no actuar: el fallo permitía ejecución remota sin autenticar',
     'Despliegue en estado «live»; cabecera PHP/8.4.27 leída del servidor'),
    ('CAM-002', 'Normal, con urgencia', 'Propiedad de los comprobantes en descargas y portal',
     'Medio: una política mal escrita dejaría sin acceso al dueño legítimo',
     'Ocho pruebas: 200 para el titular, 403 para otra cuenta'),
    ('CAM-003', 'Normal', 'Contingencia offline del punto de venta',
     'Alto: duplicar ventas o comprobantes es una contingencia tributaria',
     'Ensayo de continuidad con corte y restablecimiento reales'),
    ('CAM-004', 'Normal', 'Búsqueda de productos en la caja sin conexión',
     'Bajo: sin catálogo local la búsqueda queda como estaba, sin resultados',
     'Doce pruebas nuevas; el stock se declara sin verificar en lugar de inventarse'),
    ('CAM-005', 'Normal', 'Plan de control de cambios e integración continua',
     'Bajo: solo toca documentación',
     'Fusionado mediante Pull Request con el pipeline en verde'),
]

INTRO_CALENDARIO = (
    'El calendario existe para que dos modificaciones críticas no compitan por la misma ventana, y '
    'para que ninguna entre cuando no hay nadie que pueda comprobar el resultado. La regla se dedujo '
    'de un incidente propio: el despliegue que estuvo roto nueve días lo estuvo porque nadie verificó '
    'después de desplegar.'
)

CALENDARIO = [
    ('Ventana', 'Qué entra', 'Por qué'),
    ('Lunes a jueves, 09:00 a 17:00', 'Cambios normales y estándar',
     'Hay alguien delante para ver si algo se rompe'),
    ('Viernes', 'Nada que toque base de datos ni despliegue',
     'Un fallo del viernes se descubre el lunes'),
    ('Fuera del horario de atención', 'Migraciones que bloquean tablas',
     'La botica no está vendiendo'),
    ('Inmediata', 'Solo emergencias de seguridad',
     'Es la vía por la que entró CAM-001'),
]

INTRO_CI = (
    'La integración continua es lo que convierte la verificación en un paso obligatorio en lugar de '
    'una buena intención. El flujo vive en el propio repositorio, en «.github/workflows/ci-cd.yml», y '
    'se dispara en cada subida a la rama principal y en cada Pull Request. Son tres trabajos '
    'encadenados, y el orden entre ellos no es casual.'
)

TRABAJOS_CI = [
    ('Trabajo', 'Qué comprueba', 'Por qué está donde está'),
    ('secrets-scan', 'Que no se haya colado una credencial en el código',
     'Va primero y bloquea a los demás: si hay un secreto en el commit, lo último que interesa es gastar cinco minutos corriendo pruebas'),
    ('backend-tests', 'Las pruebas del servidor sobre PostgreSQL 16, el mismo motor que producción',
     'SQLite no sirve: no entiende GREATEST, FILTER (WHERE …) ni ILIKE, que es justo lo que usan las consultas del proyecto'),
    ('frontend-build', 'Verificación de tipos y compilación real de producción',
     'Un error de tipos que solo aparece al compilar no debe descubrirse en el despliegue'),
]

CI_RESULTADOS = (
    'El historial del repositorio recoge 71 ejecuciones: 27 terminaron en verde y 44 en rojo. Esas 44 '
    'no son un dato que convenga esconder, son el argumento a favor de tener integración continua, '
    'porque cada una detuvo un cambio antes de que llegara a producción. Un caso con nombre: una '
    'ejecución de octubre superó el escaneo de secretos y las pruebas del servidor y falló en la '
    'compilación del Front-End; es decir, el código del servidor estaba bien y lo que se habría '
    'desplegado era una interfaz que no compilaba. Conviene además distinguir dos cosas que se '
    'confunden al mirar la estadística: los fallos DEL pipeline son ruido que hay que arreglar, y los '
    'fallos DETECTADOS POR el pipeline son su trabajo.'
)

INTRO_VINCULACION = (
    'El material pide vincular cada cambio con la rama, el commit y el Pull Request donde vive el '
    'trabajo real. Hacerlo obliga a reconocer algo que el informe no debería maquillar.'
)

VINCULACION = [
    ('Cambio', 'Rama', 'Commits', 'Pull Request'),
    ('CAM-001', 'main, por emergencia', 'cf98072', 'No: se ejecutó primero por urgencia'),
    ('CAM-002', 'develop', '2315149', 'No'),
    ('CAM-003', 'develop', '3174100, 067e333, 429bbae, 65a03e0', 'No'),
    ('CAM-004', 'develop', '0002075', 'No'),
    ('CAM-005', 'docs/CAM-005-plan-control-cambios', 'Ver Pull Request', 'PR_NUMERO'),
]

VINCULACION_TEXTO = (
    'Hasta CAM-004 la práctica se cumplía a medias. Existía un Pull Request real, el del Sprint 3-4, '
    'pero el resto del trabajo entraba con commits directos a la rama de desarrollo y una fusión '
    'directa a la principal. En un proyecto de un solo autor, un Pull Request consigo mismo parece un '
    'trámite; el procedimiento, sin embargo, no está escrito para el caso cómodo. Sirve para que el '
    'pipeline se pronuncie antes de que el cambio toque la rama que despliega, y para que exista un '
    'sitio donde quede escrito qué se revisó. A partir de CAM-005 el flujo cambia: una rama por '
    'cambio, Pull Request, y fusión solo con el pipeline en verde. Este mismo apartado entró por ese '
    'camino, de modo que su propia evidencia demuestra el procedimiento que describe.'
)

INTRO_PROPUESTOS = (
    'Estos cambios están registrados y analizados, pero no implementados. Forman el embudo de entrada '
    'del procedimiento y se listan aparte para que el registro del apartado 22.4 siga diciendo la '
    'verdad sobre lo que se hizo.'
)

PROPUESTOS = [
    ('Código', 'Cambio', 'Riesgo', 'Vuelta atrás'),
    ('CR-P01', 'Indicador de vencimiento FEFO en los resultados del punto de venta',
     'Medio: puede penalizar la búsqueda si se calcula por fila', 'Revertir el commit de la rama'),
    ('CR-P02', 'Aviso automático de stock crítico por correo, como tarea programada',
     'Bajo: corre en segundo plano y no toca la venta', 'Desactivar la tarea y revertir'),
    ('CR-P03', 'Índice GIN sobre el principio activo para acelerar la búsqueda',
     'Medio: crear un índice puede bloquear la tabla', 'Revertir la migración, con respaldo previo'),
]

PROPUESTOS_TEXTO = (
    'CR-P03 merece una nota porque ilustra para qué sirve el paso de análisis. En PostgreSQL, crear un '
    'índice bloquea la tabla salvo que se use la variante concurrente, que a su vez no puede ejecutarse '
    'dentro de una transacción y, por tanto, no encaja en una migración corriente. Es exactamente el '
    'tipo de detalle que hay que descubrir antes de la ventana de cambio y no durante.'
)


# ------------------------------------------------------------------ utiles

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


def main():
    numero_pr = sys.argv[3] if len(sys.argv) > 3 else 'pendiente'
    doc = Document(str(RUTA))
    items = bloques(doc)

    # --- modelos -----------------------------------------------------------
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

    destino = items[i_sig]._p

    def insertar(modelo, texto=None):
        nuevo = copy.deepcopy(modelo._p if isinstance(modelo, Paragraph) else modelo._tbl)
        destino.addprevious(nuevo)
        if isinstance(modelo, Paragraph):
            p = Paragraph(nuevo, modelo._parent)
            if texto is not None:
                poner_texto(p, texto)
            return p
        return Table(nuevo, modelo._parent)

    anchos_pendientes = []

    def insertar_tabla(leyenda, filas, proporciones):
        insertar(modelo_etiqueta, 'Tabla 9000')
        insertar(modelo_leyenda, leyenda)

        tabla = insertar(modelo_tabla)
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
                clon = copy.deepcopy(fila._tr.tc_lst[-1])
                fila._tr.append(clon)
            rejilla = tabla._tbl.find(qn('w:tblGrid'))
            if rejilla is not None:
                rejilla.append(copy.deepcopy(rejilla[-1]))

        while len(tabla.rows) > len(filas):
            ultima = tabla.rows[-1]._tr
            ultima.getparent().remove(ultima)
        while len(tabla.rows) < len(filas):
            clon = copy.deepcopy(tabla.rows[-1]._tr)
            tabla.rows[-1]._tr.addnext(clon)

        for fila_tabla, valores in zip(tabla.rows, filas):
            for celda, valor in zip(fila_tabla.cells, valores):
                celda_texto(celda, valor)

        insertar(modelo_nota, 'Nota. Elaboración propia.')
        anchos_pendientes.append((tabla, proporciones))

    # --- 22.3 plantilla ----------------------------------------------------
    insertar(modelo_h2, '22.3. Cómo se registra una solicitud de cambio')
    insertar(modelo_cuerpo, INTRO_CR)
    insertar_tabla('Plantilla de solicitud de cambio', PLANTILLA_CR, [30, 70])

    # --- 22.4 registro -----------------------------------------------------
    insertar(modelo_h2, '22.4. Registro de cambios del proyecto')
    insertar(modelo_cuerpo, INTRO_REGISTRO)
    insertar_tabla('Cambios ejecutados sobre el servicio', REGISTRO, [10, 14, 26, 26, 24])

    # --- 22.5 calendario ---------------------------------------------------
    insertar(modelo_h2, '22.5. Calendario de cambios')
    insertar(modelo_cuerpo, INTRO_CALENDARIO)
    insertar_tabla('Ventanas del calendario de cambios', CALENDARIO, [26, 30, 44])

    # --- 22.6 integración continua -----------------------------------------
    insertar(modelo_h2, '22.6. Integración continua')
    insertar(modelo_cuerpo, INTRO_CI)
    insertar_tabla('Trabajos del flujo de integración continua', TRABAJOS_CI, [18, 34, 48])
    insertar(modelo_cuerpo, CI_RESULTADOS)

    for archivo, leyenda in [
        ('CI-01-historial-actions.jpg',
         'Historial reciente del flujo de integración continua en GitHub Actions. La ejecución '
         'superior corresponde al Pull Request del cambio CAM-005.'),
        ('CI-02-pull-request-checks.jpg',
         'Detalle de la ejecución disparada por el Pull Request: escaneo de secretos, pruebas del '
         'servidor y compilación del Front-End, los tres en verde.'),
        ('CI-03-ejecuciones-fallidas.jpg',
         'Historial filtrado por ejecuciones fallidas: 44 integraciones detenidas por el pipeline '
         'antes de llegar a producción.'),
    ]:
        ruta = CAPTURAS / archivo
        if not ruta.exists():
            print(f'   aviso: falta {ruta}, se omite la figura')
            continue

        parrafo_img = doc.add_paragraph()
        marcador = copy.deepcopy(modelo_imagen._p)
        parrafo_img._p.addprevious(marcador)
        parrafo_img.add_run().add_picture(str(ruta), width=ANCHO)

        pPr = marcador.find(qn('w:pPr'))
        if pPr is not None:
            viejo = parrafo_img._p.find(qn('w:pPr'))
            if viejo is not None:
                parrafo_img._p.remove(viejo)
            parrafo_img._p.insert(0, copy.deepcopy(pPr))
        marcador.getparent().remove(marcador)

        destino.addprevious(parrafo_img._p)
        pie = insertar(modelo_pie)
        poner_texto(pie, f'Figura 9000. {leyenda} Fuente: Elaboración propia.')

    # --- 22.7 vinculación --------------------------------------------------
    insertar(modelo_h2, '22.7. Vinculación del cambio con rama, commit y Pull Request')
    insertar(modelo_cuerpo, INTRO_VINCULACION)
    vinculacion = [tuple(v.replace('PR_NUMERO', f'PR #{numero_pr}') for v in fila)
                   for fila in VINCULACION]
    insertar_tabla('Trazabilidad de cada cambio hasta su código', vinculacion, [13, 27, 34, 26])
    insertar(modelo_cuerpo, VINCULACION_TEXTO)

    # --- 22.8 propuestos ---------------------------------------------------
    insertar(modelo_h2, '22.8. Cambios registrados y todavía no ejecutados')
    insertar(modelo_cuerpo, INTRO_PROPUESTOS)
    insertar_tabla('Cambios propuestos pendientes de ejecución', PROPUESTOS, [11, 36, 30, 23])
    insertar(modelo_cuerpo, PROPUESTOS_TEXTO)

    # --- anchos de las tablas nuevas ---------------------------------------
    # El modelo que se clona tiene cuatro columnas. Al añadir o quitar
    # columnas, las nuevas heredan el ancho de la última y la tabla deja de
    # cuadrar con la caja de texto: unas se quedan estrechas y otras se salen
    # del margen. Se reparte a mano, por el contenido de cada columna y no a
    # partes iguales: una columna con un código de cuatro caracteres no
    # necesita lo mismo que una con una frase.
    for tabla, proporciones in anchos_pendientes:
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

    # --- renumeración ------------------------------------------------------
    etiquetas = [p for p in bloques(doc)
                 if isinstance(p, Paragraph) and ES_ETIQUETA.match(p.text.strip())]
    mapa_tablas = {}
    for posicion, p in enumerate(etiquetas, start=1):
        antes = int(ES_ETIQUETA.match(p.text.strip()).group(1))
        if antes < 9000:
            mapa_tablas[antes] = posicion
    for posicion, p in enumerate(etiquetas, start=1):
        poner_texto(p, f'Tabla {posicion}')

    pies = [p for p in bloques(doc)
            if isinstance(p, Paragraph) and ES_PIE.match(p.text.strip())]
    mapa_figuras = {}
    for posicion, p in enumerate(pies, start=1):
        antes = int(ES_PIE.match(p.text.strip()).group(1))
        if antes < 9000:
            mapa_figuras[antes] = posicion
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
        nuevo = re.sub(r'Tabla (\d+)', lambda m: f'Tabla {mapa_tablas.get(int(m.group(1)), int(m.group(1)))}', texto)
        nuevo = re.sub(r'Figura (\d+)', lambda m: f'Figura {mapa_figuras.get(int(m.group(1)), int(m.group(1)))}', nuevo)
        if nuevo != texto:
            poner_texto(p, nuevo)
            refs += 1

    # --- comprobación ------------------------------------------------------
    n_tablas = [int(ES_ETIQUETA.match(p.text.strip()).group(1)) for p in bloques(doc)
                if isinstance(p, Paragraph) and ES_ETIQUETA.match(p.text.strip())]
    n_figuras = [int(ES_PIE.match(p.text.strip()).group(1)) for p in bloques(doc)
                 if isinstance(p, Paragraph) and ES_PIE.match(p.text.strip())]

    if n_tablas != list(range(1, len(n_tablas) + 1)):
        raise SystemExit(f'Las tablas no quedaron correlativas: {n_tablas}')
    if n_figuras != list(range(1, len(n_figuras) + 1)):
        raise SystemExit(f'Las figuras no quedaron correlativas: {n_figuras}')

    doc.save(str(RUTA))
    print(f'Guardado. Tablas: {len(n_tablas)} · Figuras: {len(n_figuras)} · '
          f'Referencias actualizadas: {refs}')


if __name__ == '__main__':
    main()
