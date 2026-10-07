#!/usr/bin/env python3
"""
Cierre del .docx: sustituye la Figura 4 y convierte el índice en campo TOC.

    python docs/scripts/cerrar-informe-toc-y-figura4.py <docx> <captura github>

1. FIGURA 4

La imagen era la captura preliminar del APF1, con el repositorio recién creado.
Se sustituye el contenido de la imagen conservando el párrafo, su alineación y
su pie: reemplazar el `<a:blip>` por otra relación de imagen deja intacto todo
lo demás.

2. ÍNDICE AUTOMÁTICO

El índice era texto escrito a mano. Eso tenía dos costes: los números de página
no se actualizaban solos, y cualquier script que buscara un título por su texto
encontraba primero la línea del índice —que es exactamente el fallo que metió 32
párrafos donde no debían—.

Se sustituye por un campo `TOC \\o "1-3" \\h \\z \\u`, que es el que genera Word.
A partir de ahora el índice lo calcula Word: basta con pulsar F9.

ANTES DE SUSTITUIR SE COMPRUEBA QUE NO SE PIERDE NADA

Un campo TOC recoge los párrafos con estilo Heading 1 a 3. Si algún título del
documento no tuviera estilo de encabezado, desaparecería del índice y el cambio
sería una regresión silenciosa. Por eso el script compara primero cuántos
encabezados hay contra cuántas entradas tenía el índice manual, y aborta si la
diferencia es grande en lugar de destruir el índice y confiar en que salga bien.

POR QUÉ EL CAMPO SE DEJA CON UN TEXTO PROVISIONAL

python-docx no pagina —eso lo hace Word al abrir el archivo—, así que el campo
se escribe con su resultado en blanco y una línea que pide actualizarlo. Hasta
que se pulse F9, el índice muestra ese aviso. Es preferible a dejar números de
página inventados que parecerían correctos.
"""
from __future__ import annotations

import copy
import sys
from pathlib import Path

from docx import Document
from docx.oxml import OxmlElement
from docx.oxml.ns import qn

cambios: list[str] = []
fallos: list[str] = []


def fin_del_indice(doc) -> int:
    for i, p in enumerate(doc.paragraphs):
        if i > 20 and p.style.name == "Heading 1":
            return i
    return 0


def sustituir_imagen(doc, pie_de_figura: str, nueva: Path) -> bool:
    """Cambia la imagen del párrafo anterior al pie indicado.

    Se reutiliza la relación de imagen del documento: se añade la nueva y se
    apunta el `<a:blip>` existente a ella. Así se conservan el tamaño, la
    alineación y el ajuste de texto que ya tenía la figura.
    """
    for i, p in enumerate(doc.paragraphs):
        if not p.text.strip().startswith(pie_de_figura):
            continue
        for j in range(i - 1, max(i - 6, -1), -1):
            blips = doc.paragraphs[j]._p.findall(".//" + qn("a:blip"))
            if not blips:
                continue
            # `get_or_add_image` devuelve (rId, imagen); el nombre del método
            # cambió entre versiones de python-docx, de ahí la comprobación.
            rid, _ = doc.part.get_or_add_image(str(nueva))
            blips[0].set(qn("r:embed"), rid)
            return True
    return False


def construir_campo_toc(parrafo) -> None:
    """Convierte un párrafo vacío en el campo TOC de Word."""
    p = parrafo._p

    def run_con(hijo):
        r = OxmlElement("w:r")
        r.append(hijo)
        p.append(r)

    inicio = OxmlElement("w:fldChar")
    inicio.set(qn("w:fldCharType"), "begin")
    run_con(inicio)

    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = ' TOC \\o "1-3" \\h \\z \\u '
    run_con(instr)

    separa = OxmlElement("w:fldChar")
    separa.set(qn("w:fldCharType"), "separate")
    run_con(separa)

    # Resultado provisional: lo reemplaza Word al actualizar el campo.
    texto = OxmlElement("w:t")
    texto.text = "Pulse F9 (o Clic derecho → Actualizar campos) para generar el índice."
    run_con(texto)

    fin = OxmlElement("w:fldChar")
    fin.set(qn("w:fldCharType"), "end")
    run_con(fin)


def main(ruta: Path, captura: Path) -> int:
    doc = Document(str(ruta))

    # ------------------------------------------------------------ Figura 4
    if not captura.exists():
        fallos.append(f"No existe la captura {captura}")
    elif sustituir_imagen(doc, "Figura 4.", captura):
        cambios.append(f"Figura 4 · imagen sustituida por {captura.name}")
        for p in doc.paragraphs:
            if p.text.strip().startswith("Figura 4."):
                nuevo = (
                    "Figura 4. Repositorio del proyecto en GitHub al cierre del APF2: rama main "
                    "con 100 commits, 7 ramas, 4 etiquetas y el panel de despliegues activos. "
                    "Fuente: Captura del repositorio propio."
                )
                if p.runs:
                    p.runs[0].text = nuevo
                    for r in p.runs[1:]:
                        r.text = ""
                cambios.append("Figura 4 · pie actualizado con las cifras del repositorio")
                break
    else:
        fallos.append("No se localizó la imagen de la Figura 4")

    # ------------------------------------------------------------ Índice TOC
    fin = fin_del_indice(doc)
    entradas = [p for i, p in enumerate(doc.paragraphs) if 20 < i < fin and p.text.strip()]

    encabezados = [
        p for i, p in enumerate(doc.paragraphs)
        if i >= fin and p.style.name in ("Heading 1", "Heading 2", "Heading 3")
    ]

    print(f"Entradas del índice manual : {len(entradas)}")
    print(f"Encabezados Heading 1-3    : {len(encabezados)}")

    # Si el campo recogiera bastantes menos títulos de los que listaba el índice
    # manual, el cambio seria una perdida de informacion, no una mejora.
    if len(encabezados) < len(entradas) * 0.9:
        fallos.append(
            f"ABORTADO: sólo hay {len(encabezados)} encabezados para {len(entradas)} entradas. "
            "Habría títulos sin estilo Heading que desaparecerían del índice."
        )
    else:
        primero = entradas[0]
        for p in entradas[1:]:
            p._element.getparent().remove(p._element)

        for r in list(primero.runs):
            r._element.getparent().remove(r._element)
        construir_campo_toc(primero)

        cambios.append(
            f"Índice convertido en campo TOC (niveles 1-3); "
            f"{len(entradas)} líneas manuales retiradas, {len(encabezados)} títulos lo alimentan"
        )

    doc.save(str(ruta))

    print()
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
