#!/usr/bin/env python3
"""
Corrige el formato de los párrafos insertados y la numeración del capítulo XIV.

    python docs/scripts/corregir-seccion-14-y-formato.py <ruta del .docx>

QUÉ ESTABA MAL

1. TEXTOS PEGADOS — Los párrafos que los scripts anteriores insertaron con
   `insert_paragraph_before()` nacieron con el formato de párrafo VACÍO. El
   cuerpo del documento usa, en todos sus párrafos originales:

       <w:spacing w:after="140" w:line="300" w:lineRule="auto"/>
       <w:jc w:val="both"/>

   es decir, espacio inferior, interlineado de 1.5 y texto justificado. Los
   párrafos insertados no llevaban nada de eso: quedaban a interlineado simple,
   alineados a la izquierda y SIN separación con el siguiente. Visualmente es
   exactamente lo que se ve como «texto pegado»: dos párrafos distintos que
   parecen uno solo.

   No es un problema del texto —ninguna palabra está fundida con otra—, es un
   problema del XML de formato. De ahí que no se detectara leyendo el documento
   como texto plano.

   El arreglo no inventa un formato: CLONA el `w:pPr` de un párrafo original del
   mismo estilo, de modo que lo insertado queda indistinguible de lo redactado
   a mano. Se corrigen los 41 párrafos Normal y los 3 Heading 2 afectados, en
   todo el documento y no sólo en el capítulo XIV, porque el defecto es el
   mismo y tiene la misma causa.

2. SUBPUNTOS SIN CORRELACIÓN — El apartado «14.2. Endpoints principales»
   acumulaba cuatro asuntos distintos bajo un título que sólo anuncia uno:

       14.2  Tabla 43 + rutas de la API           <- sí son endpoints
             DesgloseFiscalService y el IGV        <- no es un endpoint
             OpenAPI, Scramble y /docs/api         <- tampoco
       14.3  Integración con el Front-End

   Los dos últimos párrafos tratan temas con entidad propia y quedaban
   escondidos dentro de un apartado que no los nombra: quien busque el cálculo
   del IGV en el índice no lo encuentra. Se les da su propio subtítulo y el
   apartado siguiente se renumera en consecuencia:

       14.1  Organización del Back-End             (sin cambios)
       14.2  Endpoints principales                 (sin cambios)
       14.3  Cálculo fiscal centralizado           NUEVO
       14.4  Documentación del contrato de la API  NUEVO
       14.5  Integración con el Front-End          antes 14.3

   El índice es un campo TOC automático, así que recoge los dos subtítulos
   nuevos al pulsar F9. No hay que tocarlo a mano.

3. CIFRA AMBIGUA — El párrafo que narra el error de los recuentos decía «Con
   3 361 productos, la pantalla de productos llegó a mostrar "2 en stock"». La
   cifra es correcta como dato histórico —el fallo ocurrió cuando el catálogo
   tenía ese tamaño—, pero leída junto al resto del documento, que habla de 884
   productos, parece una contradicción. Se precisa que era el catálogo DE
   ENTONCES, sin falsear el relato.

NO SE REGENERA EL DOCUMENTO. Se edita el .docx existente para no perder la
portada de la UTP, los estilos, las fuentes, los encabezados ni los colores
corporativos.
"""
from __future__ import annotations

import copy
import sys
from pathlib import Path

from docx import Document
from docx.oxml.ns import qn

cambios: list[str] = []
fallos: list[str] = []


def tiene_formato(parrafo) -> bool:
    pPr = parrafo._p.find(qn("w:pPr"))
    return pPr is not None and pPr.find(qn("w:spacing")) is not None


def referencia(doc, estilo: str):
    """Primer párrafo del estilo pedido que SÍ trae formato de cuerpo."""
    for p in doc.paragraphs:
        if p.style.name == estilo and p.text.strip() and tiene_formato(p):
            return p
    return None


def heredar_formato(parrafo, modelo) -> None:
    """Clona el w:pPr del modelo sobre el párrafo, conservando su contenido."""
    p = parrafo._p
    viejo = p.find(qn("w:pPr"))
    nuevo = copy.deepcopy(modelo._p.find(qn("w:pPr")))
    if viejo is not None:
        p.replace(viejo, nuevo)
    else:
        p.insert(0, nuevo)


def reemplazar(parrafo, viejo: str, nuevo: str) -> bool:
    completo = "".join(r.text for r in parrafo.runs)
    if viejo not in completo:
        return False
    parrafo.runs[0].text = completo.replace(viejo, nuevo)
    for r in parrafo.runs[1:]:
        r.text = ""
    return True


def indice_de(doc, inicio: str, desde: int = 0) -> int | None:
    for i, p in enumerate(doc.paragraphs):
        if i >= desde and p.text.strip().startswith(inicio):
            return i
    return None


def main(ruta: Path) -> int:
    doc = Document(str(ruta))

    # ------------------------------------------------------------------ 1
    modelos = {
        estilo: referencia(doc, estilo)
        for estilo in ("Normal", "Heading 2")
    }
    for estilo, modelo in modelos.items():
        if modelo is None:
            fallos.append(f"No hay párrafo de referencia con estilo {estilo}")

    arreglados: dict[str, list[int]] = {"Normal": [], "Heading 2": []}
    for i, p in enumerate(doc.paragraphs):
        estilo = p.style.name
        if estilo not in modelos or modelos[estilo] is None:
            continue
        if not p.text.strip() or tiene_formato(p):
            continue
        heredar_formato(p, modelos[estilo])
        arreglados[estilo].append(i)

    for estilo, lista in arreglados.items():
        if lista:
            cambios.append(
                f"Formato de cuerpo restituido en {len(lista)} párrafo(s) «{estilo}»: "
                f"{lista}"
            )

    # ------------------------------------------------------------------ 2
    # Los subtítulos nuevos se insertan de abajo arriba para que los índices
    # calculados antes de insertar sigan siendo válidos.
    i_front = indice_de(doc, "14.3. Integración con el Front-End")
    i_docs = indice_de(doc, "El contrato completo de la API")
    i_fiscal = indice_de(doc, "El cálculo del impuesto vive en un único servicio")

    if None in (i_front, i_docs, i_fiscal):
        fallos.append(
            "No se localizaron los tres anclajes del capítulo XIV "
            f"(front={i_front}, docs={i_docs}, fiscal={i_fiscal})"
        )
    else:
        p_front = doc.paragraphs[i_front]
        if reemplazar(p_front, "14.3. Integración", "14.5. Integración"):
            cambios.append("Renumerado «14.3. Integración con el Front-End» → 14.5")
        else:
            fallos.append("No se pudo renumerar el apartado de integración")

        for indice, titulo in (
            (i_docs, "14.4. Documentación del contrato de la API"),
            (i_fiscal, "14.3. Cálculo fiscal centralizado"),
        ):
            nuevo = doc.paragraphs[indice].insert_paragraph_before(titulo)
            nuevo.style = doc.styles["Heading 2"]
            if modelos["Heading 2"] is not None:
                heredar_formato(nuevo, modelos["Heading 2"])
            cambios.append(f"Subtítulo nuevo: «{titulo}»")

    # ------------------------------------------------------------------ 3
    i = indice_de(doc, "Del lado del Front-End se aplicó una regla")
    if i is None:
        fallos.append("No se localizó el párrafo del recuento del catálogo")
    elif reemplazar(
        doc.paragraphs[i],
        "Con 3 361 productos, la pantalla",
        "Con el catálogo de 3 361 productos de entonces, la pantalla",
    ):
        cambios.append("Precisada la cifra histórica del catálogo (3 361 «de entonces»)")
    else:
        fallos.append("No se encontró el texto «Con 3 361 productos, la pantalla»")

    doc.save(str(ruta))

    print("CAMBIOS APLICADOS")
    for c in cambios:
        print("  OK  ", c)
    if fallos:
        print("\nNO APLICADOS (revisar a mano)")
        for f in fallos:
            print("  !!  ", f)
    print(f"\nTotal: {len(cambios)} cambios, {len(fallos)} sin aplicar.")
    return 1 if fallos else 0


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(2)
    sys.exit(main(Path(sys.argv[1])))
