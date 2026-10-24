# -*- coding: utf-8 -*-
"""
Desarrolla el ciclo de siete pasos para cada solicitud de servicio dentro del
informe .docx, y renumera las tablas posteriores.

POR QUE EXISTE ESTE SCRIPT

El informe traia el ciclo de atencion en abstracto (una tabla con los siete
pasos) y el catalogo en resumen (una fila por solicitud). El APF3 pide algo
mas exigente: que el ciclo se vea recorrido PASO POR PASO en cada una de las
seis solicitudes, con su ficha de seguimiento, su responsable, su resolucion y
su evidencia. Eso es lo que inserta este script como seccion 21.3.

COMO CONSERVA EL FORMATO

No se construyen parrafos ni tablas desde cero: se clonan los que el documento
ya usa en ese mismo capitulo (encabezado de nivel 3, etiqueta «Tabla NN»,
titulo en cursiva, nota al pie de tabla y la tabla de dos columnas
«Campo / Contenido» de la Tabla 69). Clonar trae consigo el pPr y el rPr
originales, que es lo unico que garantiza que el texto nuevo salga con la
misma tipografia, interlineado y alineacion que el resto del informe.

RENUMERACION

Las seis tablas nuevas ocupan los numeros 68 a 73, de modo que las que ya
existian del 68 al 75 se desplazan seis posiciones, al 74 a 81. Se comprobo
antes de escribir esto que ninguna de ellas tiene referencias cruzadas en el
texto, asi que basta con reescribir sus etiquetas.
"""

import copy
import re
import sys
from pathlib import Path

from docx import Document
from docx.oxml.ns import qn
from docx.table import Table
from docx.text.paragraph import Paragraph

RUTA = Path(sys.argv[1]) if len(sys.argv) > 1 else None
if RUTA is None or not RUTA.exists():
    raise SystemExit('Uso: python detallar-solicitudes-en-docx.py <ruta del .docx>')

CAMPOS = [
    'Código', 'Fecha', 'Solicitante', 'Tipo', 'Descripción', 'Prioridad',
    'Responsable de atención', '¿Requiere aprobación?', 'Aprobador',
    'Solución brindada', 'Evidencia', 'Estado final',
]

SOLICITUDES = [
    {
        'titulo': 'SOL-001 · Cómo generar el reporte de ventas del mes',
        'leyenda': 'Ficha de seguimiento de la solicitud SOL-001',
        'valores': [
            'SOL-001',
            '14/10/2026',
            'Vendedor de mostrador · turno mañana',
            'Solicitud de información',
            'Pide saber cómo obtener el total vendido del mes para cuadrarlo con el cuaderno de caja.',
            'Baja',
            'Administrador del sistema',
            'No. Es orientación sobre una función que su rol ya tiene.',
            'No aplica',
            'Se indica la ruta /admin/reportes, cómo fijar el rango de fechas y dónde queda el total. '
            'Se aclara que el reporte toma la fecha de la venta y no la de emisión del comprobante, '
            'que es lo que descuadraba su conteo.',
            'Issue cerrada con la respuesta y captura de la pantalla de reportes con el rango aplicado.',
            'Cerrada',
        ],
        'pasos': [
            'Registrar. La petición entra como issue #14 del repositorio el 14/10/2026 con el código SOL-001. La issue es el ticket.',
            'Clasificar. Se tipifica como solicitud de información y se etiqueta «tipo: informacion».',
            'Validar. Se comprueba que es orientación sobre una función que ya existe, no una falla del sistema ni un cambio.',
            'Autorizar. No requiere aprobación: la pantalla de reportes ya está dentro de los permisos del rol que la pide.',
            'Atender. El administrador del sistema indica la ruta, el filtro de fechas y el criterio de fecha que descuadraba el conteo.',
            'Verificar. Se adjunta la captura de la pantalla de reportes con el rango aplicado, no basta con responder.',
            'Cerrar. La issue se cierra con su evidencia adjunta. Estado final: cerrada.',
        ],
    },
    {
        'titulo': 'SOL-002 · El catálogo muestra menos unidades que el anaquel',
        'leyenda': 'Ficha de seguimiento de la solicitud SOL-002',
        'valores': [
            'SOL-002',
            '16/10/2026',
            'Propietario de la botica',
            'Solicitud de información',
            'Observa que un producto con existencias físicas aparece con menos unidades disponibles en el '
            'portal y pregunta si el sistema está equivocado.',
            'Media',
            'Administrador del sistema',
            'No',
            'No aplica',
            'No es un error: el sistema distingue stock contable, que es lo que hay en el anaquel, de stock '
            'vendible, que es lo que se puede prometer a un cliente. Los lotes vencidos cuentan en el primero '
            'y se excluyen del segundo, porque están físicamente ahí pero no se pueden dispensar. Se identificó '
            'el lote vencido responsable de la diferencia y se programó su retiro.',
            'Respuesta documentada en la issue y consulta sobre la tabla de lotes mostrando el lote vencido y su fecha.',
            'Cerrada',
        ],
        'pasos': [
            'Registrar. Issue #15 del repositorio, abierta el 16/10/2026 con el código SOL-002.',
            'Clasificar. Solicitud de información, etiquetada «tipo: informacion».',
            'Validar. Antes de responder se confirma que no es un incidente: el dato del portal es el correcto y la diferencia tiene explicación.',
            'Autorizar. No requiere aprobación; no se concede ningún permiso ni se altera dato alguno.',
            'Atender. Se explica la distinción entre stock contable y stock vendible y se localiza el lote vencido que produce la diferencia.',
            'Verificar. Consulta sobre la tabla de lotes que muestra el lote vencido y su fecha de vencimiento.',
            'Cerrar. Issue cerrada con la consulta adjunta y el retiro del lote programado. Estado final: cerrada.',
        ],
    },
    {
        'titulo': 'SOL-003 · Alta de cuenta para vendedor nuevo',
        'leyenda': 'Ficha de seguimiento de la solicitud SOL-003',
        'valores': [
            'SOL-003',
            '17/10/2026',
            'Propietario de la botica',
            'Solicitud de acceso',
            'Ingresa un vendedor al turno tarde y necesita cuenta para registrar ventas en el punto de venta.',
            'Media',
            'Administrador del sistema',
            'Sí. Toda alta de cuenta con acceso al punto de venta la autoriza el propietario.',
            'Propietario de la botica · aprueba',
            'Cuenta creada con rol vendedor, no administrador: puede registrar ventas y consultar inventario, '
            'y no accede a reportes, gestión de usuarios ni respaldos. La contraseña inicial se entregó en '
            'persona, de un solo uso, con cambio obligatorio en el primer ingreso.',
            'Issue cerrada y registro de la cuenta en la tabla de usuarios con su rol. La contraseña no se '
            'registra en el ticket ni en ningún archivo del repositorio.',
            'Cerrada',
        ],
        'pasos': [
            'Registrar. Issue #16 del repositorio, abierta el 17/10/2026 con el código SOL-003.',
            'Clasificar. Solicitud de acceso, etiquetada «tipo: acceso».',
            'Validar. Se comprueba que los datos del alta están completos: nombre, documento, turno y rol previsto.',
            'Autorizar. El propietario aprueba. Ningún acceso al punto de venta se crea sin esa autorización registrada.',
            'Atender. Se crea la cuenta con rol vendedor, por mínimo privilegio, y con cambio de contraseña obligatorio en el primer ingreso.',
            'Verificar. Registro de la cuenta en la tabla de usuarios con el rol efectivamente asignado.',
            'Cerrar. Issue cerrada con esa evidencia. Estado final: cerrada.',
        ],
    },
    {
        'titulo': 'SOL-004 · Cliente pide acceso al módulo de reportes',
        'leyenda': 'Ficha de seguimiento de la solicitud SOL-004',
        'valores': [
            'SOL-004',
            '18/10/2026',
            'Cliente registrado del portal',
            'Solicitud de acceso',
            'Pide acceso a los reportes de ventas «para comparar precios históricos».',
            'Baja',
            'Administrador del sistema',
            'Sí',
            'Propietario de la botica · deniega',
            'Rechazada por mínimo privilegio. Los reportes contienen márgenes, costos de compra y datos de '
            'otros clientes; el rol cliente no los contempla y conceder el permiso expondría información del '
            'negocio y de terceros. Se ofrece la alternativa que sí corresponde: su historial de pedidos y sus '
            'comprobantes, accesibles desde su propio panel.',
            'La API ya impide el acceso por diseño: un token de rol cliente contra las rutas de gestión '
            'responde 401/403, y hay pruebas automatizadas que fijan esa regla.',
            'Rechazada',
        ],
        'pasos': [
            'Registrar. Issue #17 del repositorio, abierta el 18/10/2026 con el código SOL-004.',
            'Clasificar. Solicitud de acceso, etiquetada «tipo: acceso».',
            'Validar. Se confirma que es una solicitud de acceso y no un incidente: el sistema está funcionando como debe al negarlo.',
            'Autorizar. El propietario deniega. Este es el paso que da sentido al procedimiento: existe precisamente para los casos en que la aprobación no corresponde.',
            'Atender. No se atiende lo denegado. Se comunica el motivo y se ofrece la alternativa que sí pertenece al rol cliente.',
            'Verificar. La evidencia no es una captura: es el propio sistema devolviendo 401/403 a un token de rol cliente, con pruebas automatizadas que fijan la regla.',
            'Cerrar. Issue cerrada como «not planned», con el motivo documentado. Estado final: rechazada.',
        ],
    },
    {
        'titulo': 'SOL-005 · Restablecer contraseña de vendedor',
        'leyenda': 'Ficha de seguimiento de la solicitud SOL-005',
        'valores': [
            'SOL-005',
            '19/10/2026',
            'Vendedor de mostrador · turno tarde',
            'Solicitud estándar, modelo «Restablecer contraseña»',
            'No puede iniciar sesión al comenzar su turno y el punto de venta queda sin atender.',
            'Alta · bloquea la atención en mostrador',
            'Administrador del sistema',
            'Sí. Se verifica la identidad de quien pide antes de restablecer nada: un restablecimiento '
            'concedido por teléfono a quien dice ser otra persona es un acceso regalado.',
            'Propietario de la botica · aprueba',
            'Identidad verificada en persona, contraseña restablecida con valor temporal y cambio obligatorio '
            'en el primer ingreso. Atendida dentro del objetivo de dos horas hábiles del modelo estándar.',
            'Issue cerrada y registro del acceso posterior de la cuenta. No se registra el valor de la '
            'contraseña en ninguna parte.',
            'Cerrada',
        ],
        'pasos': [
            'Registrar. Issue #18 del repositorio, abierta el 19/10/2026 con el código SOL-005.',
            'Clasificar. Solicitud estándar, etiquetada «tipo: estandar», atendida por el modelo «Restablecer contraseña».',
            'Validar. Queda pendiente verificar la identidad de quien pide; sin eso la solicitud no avanza.',
            'Autorizar. El propietario aprueba una vez verificada la identidad en persona.',
            'Atender. Restablecimiento con valor temporal y cambio obligatorio en el primer ingreso, dentro del objetivo de dos horas hábiles.',
            'Verificar. Registro del acceso posterior de la cuenta, que demuestra que el restablecimiento funcionó.',
            'Cerrar. Issue cerrada con esa evidencia y sin registrar en ningún sitio el valor de la contraseña. Estado final: cerrada.',
        ],
    },
    {
        'titulo': 'SOL-006 · Respaldo de la base antes de una carga masiva',
        'leyenda': 'Ficha de seguimiento de la solicitud SOL-006',
        'valores': [
            'SOL-006',
            '20/10/2026',
            'Propietario de la botica',
            'Solicitud estándar, modelo «Solicitar backup»',
            'Antes de cargar una lista nueva de productos por carga masiva, pide un respaldo para poder '
            'volver atrás si la carga sale mal.',
            'Alta',
            'Administrador del sistema',
            'Sí',
            'Propietario de la botica · aprueba',
            'Respaldo ejecutado antes de la carga y verificado por conteo de filas de las tablas principales. '
            'Es el mismo procedimiento que se aplicó antes de la fusión de productos duplicados, donde el '
            'respaldo previo fue lo que permitió autorizar una operación destructiva sobre 869 grupos de '
            'productos sin asumir el riesgo a ciegas.',
            'Archivo de respaldo con su fecha, registro del comando ejecutado y conteo de filas antes y después.',
            'Cerrada',
        ],
        'pasos': [
            'Registrar. Issue #19 del repositorio, abierta el 20/10/2026 con el código SOL-006.',
            'Clasificar. Solicitud estándar, etiquetada «tipo: estandar», atendida por el modelo «Solicitar backup».',
            'Validar. Se comprueban los datos que el modelo exige: motivo del respaldo y fecha objetivo.',
            'Autorizar. El propietario aprueba, por tratarse de una operación sobre la base de producción.',
            'Atender. Respaldo ejecutado antes de la carga, nunca después: un respaldo posterior a la carga no sirve para volver atrás.',
            'Verificar. Conteo de filas de las tablas principales antes y después, además del archivo de respaldo fechado.',
            'Cerrar. Issue cerrada con el archivo y los conteos adjuntos. Estado final: cerrada.',
        ],
    },
]

INTRO = (
    'El ciclo descrito en el apartado anterior no se enuncia y se deja en abstracto: se recorre entero en '
    'cada una de las seis solicitudes registradas. Lo que sigue desarrolla paso por paso los siete puntos '
    'del ciclo sobre cada solicitud, con la ficha de seguimiento que se mantiene en GitHub Issues y en el '
    'tablero de GitHub Projects. El contenido de cada ficha coincide con el publicado en la issue '
    'correspondiente, de modo que el informe y el repositorio dicen lo mismo.'
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
    """Escribe el texto conservando el formato del primer run del modelo."""
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
    doc = Document(str(RUTA))

    items = bloques(doc)

    # --- modelos a clonar -------------------------------------------------
    modelo_h3 = next(p for p in doc.paragraphs
                     if p.style.name.startswith('Heading 3') and p.runs)
    i_etiqueta = next(i for i, it in enumerate(items)
                      if isinstance(it, Paragraph) and it.text.strip() == 'Tabla 67')
    modelo_etiqueta = items[i_etiqueta]
    modelo_leyenda = items[i_etiqueta + 1]
    modelo_tabla = items[i_etiqueta + 2]
    modelo_nota = items[i_etiqueta + 3]
    modelo_cuerpo = next(it for it in items
                         if isinstance(it, Paragraph) and it.style.name == 'Normal'
                         and len(it.text.strip()) > 120 and it.runs)

    # Tabla de dos columnas «Campo / Contenido»: se busca por su forma, no por
    # su número, porque la renumeración anterior ya le cambió la etiqueta.
    modelo_ficha = next(it for it in items
                        if isinstance(it, Table) and len(it.columns) == 2
                        and len(it.rows) >= 10
                        and it.rows[0].cells[0].text.strip().lower() == 'campo')

    # --- punto de inserción: justo antes del capítulo XXII ----------------
    ancla = next(it for it in items
                 if isinstance(it, Paragraph)
                 and it.style.name.startswith('Heading 1')
                 and it.text.strip().startswith('XXII.'))
    destino = ancla._p

    def insertar(modelo, texto=None):
        nuevo = copy.deepcopy(modelo._p if isinstance(modelo, Paragraph) else modelo._tbl)
        destino.addprevious(nuevo)
        if isinstance(modelo, Paragraph):
            p = Paragraph(nuevo, modelo._parent)
            if texto is not None:
                poner_texto(p, texto)
            return p
        return Table(nuevo, modelo._parent)

    # --- 21.3 --------------------------------------------------------------
    cabecera = copy.deepcopy(items[i_etiqueta - 1]._p)  # Heading 2 «21.2 …»
    destino.addprevious(cabecera)
    poner_texto(Paragraph(cabecera, doc), '21.3. El ciclo aplicado a cada solicitud')
    insertar(modelo_cuerpo, INTRO)

    numero = 68
    for sol in SOLICITUDES:
        insertar(modelo_h3, sol['titulo'])
        insertar(modelo_etiqueta, f'Tabla {numero}')
        insertar(modelo_leyenda, sol['leyenda'])

        tabla = insertar(modelo_ficha)
        filas_necesarias = len(CAMPOS) + 1
        while len(tabla.rows) > filas_necesarias:
            ultima = tabla.rows[-1]._tr
            ultima.getparent().remove(ultima)
        while len(tabla.rows) < filas_necesarias:
            clon = copy.deepcopy(tabla.rows[-1]._tr)
            tabla.rows[-1]._tr.addnext(clon)
        celda_texto(tabla.rows[0].cells[0], 'Campo')
        celda_texto(tabla.rows[0].cells[1], 'Contenido')
        for fila, campo, valor in zip(tabla.rows[1:], CAMPOS, sol['valores']):
            celda_texto(fila.cells[0], campo)
            celda_texto(fila.cells[1], valor)

        insertar(modelo_nota, 'Nota. Elaboración propia.')
        for n, paso in enumerate(sol['pasos'], start=1):
            insertar(modelo_cuerpo, f'{n}. {paso}')
        numero += 1

    # --- renumeración global por orden de aparición ------------------------
    # Se reasignan todas las etiquetas siguiendo el orden en que aparecen en
    # el documento. Esto absorbe las seis tablas nuevas y, de paso, corrige
    # una herencia del avance anterior: la tabla del Anexo B conservaba el
    # número 63 aunque físicamente va después de los capítulos del APF3.
    # Es seguro porque las referencias cruzadas del texto apuntan solo a
    # tablas del 1 al 62, cuyo orden relativo no cambia.
    etiquetas = [p for p in bloques(doc)
                 if isinstance(p, Paragraph) and re.fullmatch(r'Tabla \d+', p.text.strip())]
    cambios = []
    for posicion, p in enumerate(etiquetas, start=1):
        antes = int(p.text.strip().split()[1])
        if antes != posicion:
            cambios.append((antes, posicion))
            poner_texto(p, f'Tabla {posicion}')
    print(f'Etiquetas renumeradas: {len(cambios)} de {len(etiquetas)}')
    if cambios:
        print('  primera:', cambios[0], '· última:', cambios[-1])

    numeros = [int(p.text.strip().split()[1]) for p in bloques(doc)
               if isinstance(p, Paragraph) and re.fullmatch(r'Tabla \d+', p.text.strip())]
    if numeros != list(range(1, len(numeros) + 1)):
        raise SystemExit(f'Numeración inconsistente tras renumerar: {numeros}')

    doc.save(str(RUTA))
    print(f'Insertadas {len(SOLICITUDES)} fichas (Tablas 68 a {numero - 1}).')


if __name__ == '__main__':
    main()
