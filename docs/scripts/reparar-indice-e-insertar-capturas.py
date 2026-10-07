#!/usr/bin/env python3
"""
Repara el índice del .docx y coloca las capturas del despliegue.

    python docs/scripts/reparar-indice-e-insertar-capturas.py <docx> <carpeta de capturas>

EL FALLO QUE REPARA, Y POR QUÉ OCURRIÓ

Los dos scripts de parcheo anteriores localizaban el punto de inserción con una
función que devolvía el PRIMER párrafo cuyo texto empezaba por lo buscado. Pero
el índice de este documento es texto escrito a mano, no un campo TOC, y lista
todos los títulos **antes** de los capítulos reales. Así que al buscar
"17.4. Decisiones del arranque" se encontraba la línea del índice, no el
apartado, y los 32 párrafos nuevos acabaron dentro del índice. Los capítulos
reales nunca recibieron el contenido.

No fue un problema de estilos: se comprobó que los 32 párrafos tienen estilo
`Normal` y ningún `outlineLvl`. Fue un problema de a qué párrafo apuntaba la
inserción.

LA CORRECCIÓN, EN TRES PASOS

 1. Se eliminan del bloque del índice los párrafos intrusos. La regla para
    distinguirlos es limpia y se verificó antes de aplicarla: las 94 entradas
    legítimas del índice contienen un tabulador —el que separa el título de su
    número de página— y los 32 intrusos no contienen ninguno.

 2. Se reinsertan en los capítulos reales. El buscador ahora ignora todo lo que
    esté antes del primer `Heading 1`, que es donde acaba el índice.

 3. Se añaden al índice las entradas de los apartados nuevos, con su tabulador,
    en la posición que les corresponde.

NUMERACIÓN DEL CAPÍTULO XVII

El capítulo ya tenía un 17.4 («Decisiones del arranque»). Los apartados nuevos
pasan a ser 17.5 y 17.6, en lugar de renumerar lo que ya estaba: mover la
numeración de un apartado existente obligaría a revisar cualquier referencia
cruzada que lo mencione.

CAPTURAS

Se insertan siete de las catorce disponibles. Se descarta deliberadamente la de
gestión de usuarios: muestra el nombre, el DNI, el correo y el teléfono de una
persona real registrada en el sitio desplegado, y este documento se entrega y se
archiva. También se descartan las que solo muestran un formulario vacío, que no
acreditan ningún funcionamiento, y la del editor de producto con la imagen rota,
que documenta un defecto pendiente y no una verificación.
"""
from __future__ import annotations

import sys
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Inches

cambios: list[str] = []
fallos: list[str] = []

# --------------------------------------------------------------------------
# Capturas seleccionadas. Cada una: archivo, título de figura y nota.
# La nota explica la decisión técnica o la verificación que acredita; una
# captura sin nota es una captura decorativa, que es lo que la guía descarta.
# --------------------------------------------------------------------------
FIGURAS_XVII = [
    (
        "Imagen3.png",
        "Catálogo en el entorno desplegado tras la fusión de duplicados: 884 productos. "
        "Fuente: Elaboración propia.",
        "Nota. Es la comprobación que acredita que el catálogo depurado está en producción y no "
        "solo en el entorno local. Antes del despliegue del 7 de octubre esta misma pantalla "
        "mostraba 3 361 productos, porque los 869 grupos duplicados seguían en la base de Neon. "
        "La fusión la ejecutó la propia migración durante el arranque del contenedor. "
        "Elaboración propia.",
    ),
    (
        "Imagen2.png",
        "Punto de venta operando contra la API desplegada, con el desglose del IGV. "
        "Fuente: Elaboración propia.",
        "Nota. El importe de S/ 10.00 se descompone en S/ 8.47 de valor de venta y S/ 1.53 de "
        "IGV. El impuesto se extrae del precio y no se suma sobre él, porque en el Perú el precio "
        "de mostrador ya lo incluye; el cálculo lo realiza DesgloseFiscalService, el mismo "
        "servicio que utiliza el carrito del portal, de modo que la web y la boleta no pueden "
        "arrojar cifras distintas. Elaboración propia.",
    ),
]

FIGURAS_ANEXO_C = [
    (
        "Imagen1.png",
        "C.6",
        "Tablero del panel administrativo contra la base desplegada. Fuente: Elaboración propia.",
        "Nota. El contador de productos muestra 884, que es la cifra posterior a la fusión. El "
        "propio tablero advierte de dos limitaciones conocidas en lugar de ocultarlas: solo 2 de "
        "883 lotes tienen fecha de vencimiento registrada —deuda del inventario heredado que el "
        "conteo por ciclos va saldando— y casi todo el catálogo aparece bajo el mínimo porque el "
        "umbral por defecto no se corresponde con el inventario real. Elaboración propia.",
    ),
    (
        "Imagen9.png",
        "C.7",
        "Gestión de inventario en producción, con el stock vendible por producto. "
        "Fuente: Elaboración propia.",
        "Nota. La cifra de 884 productos listados coincide con la del catálogo y con la que "
        "devuelve la API pública, lo que confirma que las tres vistas leen la misma base. El "
        "estado crítico o bajo se calcula contra el mínimo de cada producto, no contra un umbral "
        "global. Elaboración propia.",
    ),
]

FIGURAS_ANEXO_D = [
    (
        "Imagen10.png",
        "D.9",
        "Conteo por ciclos en el entorno desplegado. Fuente: Elaboración propia.",
        "Nota. La pantalla propone qué conviene contar cada día y, en primer lugar, los productos "
        "sin fecha de vencimiento registrada. Es la herramienta con la que se salda la deuda del "
        "inventario heredado sin detener la botica: entre 20 y 30 productos diarios bastan para "
        "recorrer el catálogo varias veces al año. Elaboración propia.",
    ),
    (
        "Imagen12.png",
        "D.10",
        "Módulo de facturación electrónica en producción. Fuente: Elaboración propia.",
        "Nota. Los contadores en cero reflejan el estado real: el comprobante se emite dentro del "
        "sistema, con serie y número correlativo, pero el envío a la SUNAT está simulado a la "
        "espera del certificado digital y de las credenciales del operador. Presentar esta "
        "pantalla con documentos aceptados exigiría datos que todavía no existen. "
        "Elaboración propia.",
    ),
    (
        "Imagen7.png",
        "D.11",
        "Gestión de categorías sobre el catálogo depurado. Fuente: Elaboración propia.",
        "Nota. Las seis categorías reparten los 884 productos, de los que 829 corresponden a "
        "medicamentos. El recuento por categoría lo calcula el servidor sobre la tabla completa y "
        "no sobre la página cargada, que es el error corregido en cuatro pantallas durante este "
        "avance. Elaboración propia.",
    ),
]

# Entradas que faltan en el índice manual, con el apartado tras el que van.
ENTRADAS_INDICE = [
    ("16.7. Pruebas automatizadas de regresión", "16.8. Despacho FEFO verificado sobre el catálogo real"),
    ("16.8. Despacho FEFO verificado sobre el catálogo real", "16.9. Atomicidad de las operaciones de inventario (RNF06)"),
    ("16.9. Atomicidad de las operaciones de inventario (RNF06)", "16.10. Salto de maquetación del catálogo (CLS)"),
    ("17.4. Decisiones del arranque", "17.5. Verificación de humo del entorno desplegado"),
    ("17.5. Verificación de humo del entorno desplegado", "17.6. El despliegue estuvo roto nueve días sin que se notara"),
]


def fin_del_indice(doc) -> int:
    """Índice del primer `Heading 1`: todo lo anterior es el índice manual."""
    for i, p in enumerate(doc.paragraphs):
        if i > 20 and p.style.name == "Heading 1":
            return i
    return 0


def buscar(doc, inicio: str, desde: int) -> int | None:
    """Primer párrafo que empieza por `inicio`, buscando SOLO a partir de `desde`.

    El parámetro `desde` es lo que faltaba en la versión anterior: sin él, la
    búsqueda encontraba la línea del índice en lugar del apartado real.
    """
    for i, p in enumerate(doc.paragraphs):
        if i < desde:
            continue
        if p.text.strip().startswith(inicio):
            return i
    return None


def borrar(parrafo) -> None:
    parrafo._element.getparent().remove(parrafo._element)


def insertar_tras(doc, indice: int, bloques: list[tuple[str, str]], etiqueta: str) -> None:
    ancla = doc.paragraphs[indice + 1] if indice + 1 < len(doc.paragraphs) else None
    if ancla is None:
        fallos.append(f"{etiqueta}: no hay dónde insertar")
        return
    for estilo, texto in bloques:
        nuevo = ancla.insert_paragraph_before(texto)
        try:
            nuevo.style = doc.styles[estilo]
        except KeyError:
            nuevo.style = doc.styles["Normal"]
    cambios.append(f"{etiqueta}: {len(bloques)} párrafo(s)")


def insertar_figura(doc, indice: int, imagen: Path, titulo: str, nota: str, etiqueta: str) -> None:
    """Imagen centrada, con su pie y su nota, siguiendo el formato del documento."""
    ancla = doc.paragraphs[indice + 1] if indice + 1 < len(doc.paragraphs) else None
    if ancla is None or not imagen.exists():
        fallos.append(f"{etiqueta}: falta el ancla o el archivo {imagen.name}")
        return

    p_img = ancla.insert_paragraph_before()
    p_img.alignment = WD_ALIGN_PARAGRAPH.CENTER
    # 5.9 pulgadas entra en el ancho útil de una página A4 con los márgenes de
    # este documento sin forzar un salto de página.
    p_img.add_run().add_picture(str(imagen), width=Inches(5.9))

    p_pie = ancla.insert_paragraph_before(titulo)
    p_pie.style = doc.styles["Normal"]

    p_nota = ancla.insert_paragraph_before(nota)
    p_nota.style = doc.styles["Normal"]

    cambios.append(f"{etiqueta}: {imagen.name}")


def main(ruta: Path, capturas: Path) -> int:
    doc = Document(str(ruta))

    # ---------------------------------------------------------------- PASO 1
    # Sacar del índice los párrafos intrusos y quedarse con su contenido.
    fin = fin_del_indice(doc)
    intrusos = [
        p for i, p in enumerate(doc.paragraphs)
        if 20 < i < fin and p.text.strip() and "\t" not in p.text
    ]

    rescatado = [(p.style.name, p.text) for p in intrusos]
    for p in intrusos:
        borrar(p)
    cambios.append(f"Índice saneado: {len(intrusos)} párrafos intrusos retirados")

    # ---------------------------------------------------------------- PASO 2
    # Reinsertar en los capítulos REALES. El índice ya no estorba, pero se
    # recalcula su final porque al borrar han cambiado los índices.
    fin = fin_del_indice(doc)

    def prosa(desde: int, hasta: int) -> list[tuple[str, str]]:
        return [("Normal", t) for _, t in rescatado[desde:hasta]]

    # XIV — los cinco párrafos de endpoints, antes de 14.3
    i = buscar(doc, "14.3. Integración con el Front-End", fin)
    if i is not None:
        insertar_tras(doc, i - 1, prosa(0, 5), "XIV.2 · endpoints y desglose fiscal")
    else:
        fallos.append("No se encontró el apartado real 14.3")

    # XVI — un párrafo en 16.7 y los tres apartados nuevos
    i = buscar(doc, "16.7. Pruebas automatizadas de regresión", fin)
    if i is not None:
        bloques: list[tuple[str, str]] = [("Normal", rescatado[5][1])]
        bloques += [("Heading 2", rescatado[6][1])]
        bloques += prosa(7, 10)
        bloques += [("Heading 2", rescatado[10][1])]
        bloques += prosa(11, 12)
        bloques += [("Heading 2", rescatado[12][1])]
        bloques += prosa(13, 16)
        insertar_tras(doc, i, bloques, "XVI · 16.7 ampliado y apartados 16.8 a 16.10")
    else:
        fallos.append("No se encontró el apartado real 16.7")

    # XVII — apartados nuevos DESPUÉS del 17.4 existente, numerados 17.5 y 17.6
    i = buscar(doc, "17.4. Decisiones del arranque", fin)
    if i is not None:
        siguiente = buscar(doc, "XVIII. RETROSPECTIVA", i)
        destino = (siguiente - 1) if siguiente else i
        bloques = [("Heading 2", "17.5. Verificación de humo del entorno desplegado")]
        bloques += prosa(17, 20)
        bloques += [("Heading 2", "17.6. El despliegue estuvo roto nueve días sin que se notara")]
        bloques += prosa(21, 25)
        insertar_tras(doc, destino - 1, bloques, "XVII · apartados 17.5 y 17.6")
    else:
        fallos.append("No se encontró el apartado real 17.4")

    # XVIII — los tres párrafos de retrospectiva
    i = buscar(doc, "18.3. Qué se empezará a hacer", fin)
    if i is not None:
        insertar_tras(doc, i, prosa(25, 28), "XVIII · retrospectiva del Sprint 4")
    else:
        fallos.append("No se encontró el apartado real 18.3")

    # XIX — los cuatro párrafos de evidencias
    i = buscar(doc, "XIX. EVIDENCIAS DEL AVANCE", fin)
    if i is not None:
        insertar_tras(doc, i, prosa(28, 32), "XIX · mapa de evidencias")
    else:
        fallos.append("No se encontró el capítulo real XIX")

    # ---------------------------------------------------------------- PASO 3
    # Entradas que faltaban en el índice manual.
    for anterior, nueva in ENTRADAS_INDICE:
        colocada = False
        for i, p in enumerate(doc.paragraphs):
            if i >= fin_del_indice(doc):
                break
            if p.text.strip().startswith(anterior):
                pagina = p.text.split("\t")[-1].strip()
                siguiente = doc.paragraphs[i + 1]
                nuevo = siguiente.insert_paragraph_before(f"{nueva}\t{pagina}")
                nuevo.style = p.style
                nuevo.paragraph_format.tab_stops  # conserva los tabuladores del estilo
                colocada = True
                break
        if colocada:
            cambios.append(f"Índice · entrada añadida: {nueva[:46]}")
        else:
            fallos.append(f"Índice · no se pudo colocar: {nueva[:46]}")

    # ---------------------------------------------------------------- PASO 4
    # Capturas del despliegue.
    fin = fin_del_indice(doc)
    figura = 52  # las existentes llegan hasta la 51

    i = buscar(doc, "17.5. Verificación de humo del entorno desplegado", fin)
    if i is not None:
        destino = buscar(doc, "17.6. El despliegue estuvo roto", i) or i
        for archivo, titulo, nota in FIGURAS_XVII:
            insertar_figura(doc, destino - 1, capturas / archivo,
                            f"Figura {figura}. {titulo}", nota,
                            f"XVII · Figura {figura}")
            figura += 1
            destino += 3
    else:
        fallos.append("No se encontró el apartado 17.5 para las figuras")

    i = buscar(doc, "C.5. Contraste de la pantalla de acceso", fin)
    if i is not None:
        destino = buscar(doc, "ANEXO D.", i) or (i + 3)
        for archivo, clave, titulo, nota in FIGURAS_ANEXO_C:
            ancla = doc.paragraphs[destino - 1]
            enc = ancla.insert_paragraph_before(
                f"{clave}. {titulo.split('.')[0]}"
            )
            enc.style = doc.styles["Heading 3"]
            insertar_figura(doc, destino - 1, capturas / archivo,
                            f"Figura {clave}. {titulo}", nota,
                            f"Anexo C · Figura {clave}")
            destino += 4
    else:
        fallos.append("No se encontró C.5 para las figuras del Anexo C")

    i = buscar(doc, "D.8. Ventas de mostrador", fin)
    if i is not None:
        destino = len(doc.paragraphs)
        for archivo, clave, titulo, nota in FIGURAS_ANEXO_D:
            enc = doc.add_paragraph(f"{clave}. {titulo.split(':')[0].split('.')[0]}")
            enc.style = doc.styles["Heading 3"]
            p_img = doc.add_paragraph()
            p_img.alignment = WD_ALIGN_PARAGRAPH.CENTER
            ruta_img = capturas / archivo
            if ruta_img.exists():
                p_img.add_run().add_picture(str(ruta_img), width=Inches(5.9))
                doc.add_paragraph(f"Figura {clave}. {titulo}")
                doc.add_paragraph(nota)
                cambios.append(f"Anexo D · Figura {clave}: {archivo}")
            else:
                fallos.append(f"Anexo D · falta {archivo}")
    else:
        fallos.append("No se encontró D.8 para las figuras del Anexo D")

    doc.save(str(ruta))

    print("CAMBIOS APLICADOS")
    for c in cambios:
        print("  OK  ", c)
    if fallos:
        print("\nNO APLICADOS")
        for f in fallos:
            print("  !!  ", f)
    print(f"\nTotal: {len(cambios)} cambios, {len(fallos)} sin aplicar.")
    return 1 if fallos else 0


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(__doc__)
        sys.exit(2)
    sys.exit(main(Path(sys.argv[1]), Path(sys.argv[2])))
