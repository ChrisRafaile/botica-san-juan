#!/usr/bin/env python3
"""
Segunda pasada: separa lo que es PRODUCCION de lo que es DESARROLLO.

    python docs/scripts/corregir-figuras-de-produccion.py <ruta del .docx>

QUE SE ESCAPO EN LA PRIMERA PASADA

El barrido anterior llevo todas las cifras de migraciones a 49, que es lo que
hay en la maquina de desarrollo. Pero las Figuras 50 y C.1 y varias celdas
hablan del ENTORNO DESPLEGADO, y ahi no hay 49 sino 48: la migracion del CDR
todavia no se ha desplegado, porque su PR no esta fusionado.

Poner 49 en un pie que dice «sobre la base de produccion» no es redondear: es
afirmar que se desplego algo que no se desplego. Y la captura que se le puso
venia de la base local, con lo cual el pie y la imagen decian produccion
mientras mostraban otra cosa.

DE DONDE SALEN LAS CIFRAS

    Consultado contra la base desplegada (Neon, proyecto botica-san-juan):

        select count(*) from migrations                -> 48
        select count(*) from productos                 -> 884
        select count(*) from lotes                     -> 886
        tablas del esquema public                      -> 34

    Y en la maquina de desarrollo: 49 migraciones, mismo catalogo.

La diferencia de una migracion entre los dos entornos es real y conviene que
el informe la diga, en lugar de taparla con un numero unico. Un documento que
afirma que desarrollo y produccion estan al mismo nivel cuando no lo estan es
precisamente el tipo de cosa que este barrido venia a corregir.
"""
from __future__ import annotations

import sys
from pathlib import Path

from docx import Document
from docx.oxml.ns import qn
from PIL import Image

TEXTOS = [
    # Pies de figura: vuelven a produccion, con su cifra real.
    ("las 49 figuran como ejecutadas y ninguna como pendiente",
     "las 48 figuran como ejecutadas y ninguna como pendiente",
     "Pie · Figura 50 (producción)"),

    ("las 49 aparecen como aplicadas y ninguna como pendiente",
     "las 48 aparecen como aplicadas y ninguna como pendiente",
     "Pie · Figura C.1 (producción)"),

    # Celdas que describen el entorno desplegado.
    ("46 migraciones aplicadas sobre Neon",
     "48 migraciones aplicadas sobre Neon",
     "Tabla 68 · despliegue"),

    ("46 migraciones aplicadas sobre PostgreSQL 16 gestionado, sin pendientes.",
     "48 migraciones aplicadas sobre PostgreSQL 16 gestionado, sin pendientes.",
     "Tabla 75 · base gestionada"),

    ("884 productos y 46 migraciones aplicadas",
     "884 productos y 48 migraciones aplicadas",
     "Tabla 101 · verificación de producción"),

    # Celdas que describen el REPOSITORIO: ahi si son 49.
    ("Eloquent ORM, 46 migraciones versionadas",
     "Eloquent ORM, 49 migraciones versionadas",
     "Tabla 32 · pila tecnológica"),

    ("Actualizadas a 20 controladores, 46 migraciones y 22 modelos.",
     "Actualizadas a 20 controladores, 49 migraciones y 22 modelos.",
     "Tabla 48 · inventario del código"),

    ("46 migraciones en el repositorio; cada cambio de tabla es un archivo",
     "49 migraciones en el repositorio; cada cambio de tabla es un archivo",
     "Tabla 54 · migraciones versionadas"),

    # Las dos que narran el acto de migrar quedan ancladas en su momento. No se
    # les cambia la cifra -seria falsear el hecho-, se les marca la fecha.
    ("se aplicaron las 46 migraciones sin errores y se cargaron 3 537 registros",
     "se aplicaron las 46 migraciones de entonces sin errores y se cargaron 3 537 registros",
     "Tabla 34 · migración ejecutada (histórico)"),

    ("PostgreSQL 16 con 46 migraciones versionadas y 3 537 registros migrados",
     "PostgreSQL 16 con las 46 migraciones de entonces y 3 537 registros migrados",
     "Tabla 37 · migración ejecutada (histórico)"),

    # El diagrama entidad-relacion dice 32 tablas; hoy son 34.
    ("Diagrama entidad-relación completo de la base, con sus 32 tablas",
     "Diagrama entidad-relación de la base, con las 32 tablas que tenía al generarse",
     "Pie · Figura 73 (histórico)"),
]

# rId -> archivo. La C.1 recupera una captura del entorno desplegado.
IMAGENES = {"rId54": "evidencias/cortes/migraciones-produccion-48.png"}

# La imagen que la primera pasada inserto para la Figura 50 venia de la base
# local. Se sustituye por la de produccion, que es lo que el pie promete.
PIE_FIGURA_50 = "Figura 50. Migraciones aplicadas sobre la base de producción"
IMAGEN_FIGURA_50 = "evidencias/cortes/migraciones-produccion-48.png"

cambios: list[str] = []
fallos: list[str] = []


def recorrer(doc):
    for p in doc.paragraphs:
        yield p
    for t in doc.tables:
        for fila in t.rows:
            for celda in fila.cells:
                for p in celda.paragraphs:
                    yield p


def reemplazar(parrafo, viejo: str, nuevo: str) -> bool:
    completo = "".join(r.text for r in parrafo.runs)
    if viejo not in completo:
        return False
    parrafo.runs[0].text = completo.replace(viejo, nuevo)
    for r in parrafo.runs[1:]:
        r.text = ""
    return True


def _ajustar_alto(doc, rid: str, proporcion: float) -> None:
    for blip in doc.element.body.iter(qn("a:blip")):
        if blip.get(qn("r:embed")) != rid:
            continue
        # Se sube hasta el <w:drawing>: el tamanio vive en DOS sitios, el
        # <wp:extent> del marco y el <a:ext> del dibujo, y tienen que decir lo
        # mismo. Partiendo de un ancestro mas cercano solo se alcanza el
        # segundo, y Word dibuja la imagen a un tamanio y la recorta al otro.
        marco = blip
        while marco is not None and marco.tag != qn("w:drawing"):
            marco = marco.getparent()
        if marco is None:
            continue
        for ext in marco.iter():
            if ext.tag in (qn("wp:extent"), qn("a:ext")):
                cx = ext.get("cx")
                if cx:
                    ext.set("cy", str(int(int(cx) * proporcion)))


def sustituir_imagen(doc, rid: str, archivo: Path) -> bool:
    try:
        parte = doc.part.related_parts[rid]
    except KeyError:
        return False
    parte._blob = archivo.read_bytes()
    with Image.open(archivo) as im:
        ancho, alto = im.size
    _ajustar_alto(doc, rid, alto / ancho)
    return True


def imagen_del_pie(doc, inicio_pie: str) -> str | None:
    """Devuelve el rId de la imagen que precede a un pie de figura."""
    ps = doc.paragraphs
    for i, p in enumerate(ps):
        if not p.text.strip().startswith(inicio_pie):
            continue
        for j in range(i - 1, max(i - 4, -1), -1):
            rids = [b.get(qn("r:embed")) for b in ps[j]._p.iter(qn("a:blip"))]
            if rids:
                return rids[0]
    return None


def main(ruta: Path) -> int:
    raiz = Path(__file__).resolve().parents[1]
    doc = Document(str(ruta))

    for viejo, nuevo, etiqueta in TEXTOS:
        n = sum(1 for p in recorrer(doc) if reemplazar(p, viejo, nuevo))
        (cambios if n else fallos).append(
            f"{etiqueta}: {n} sustitución(es)" if n
            else f"{etiqueta}: NO SE ENCONTRÓ «{viejo[:55]}…»"
        )

    objetivos = dict(IMAGENES)
    rid50 = imagen_del_pie(doc, PIE_FIGURA_50)
    if rid50:
        objetivos[rid50] = IMAGEN_FIGURA_50
    else:
        fallos.append("Figura 50: no se localizó su imagen")

    for rid, relativo in objetivos.items():
        if sustituir_imagen(doc, rid, raiz / relativo):
            cambios.append(f"Imagen {rid} ← {relativo}")
        else:
            fallos.append(f"Imagen {rid}: relación inexistente")

    doc.save(str(ruta))

    print("CAMBIOS")
    for c in cambios:
        print("  OK  ", c)
    if fallos:
        print("\nNO APLICADO")
        for f in fallos:
            print("  !!  ", f)
    print(f"\nTotal: {len(cambios)} aplicados, {len(fallos)} sin aplicar.")
    return 1 if fallos else 0


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(2)
    sys.exit(main(Path(sys.argv[1])))
