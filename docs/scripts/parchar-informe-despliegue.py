#!/usr/bin/env python3
"""
Actualiza los capítulos de despliegue y evidencias del .docx del APF2.

    python docs/scripts/parchar-informe-despliegue.py <ruta del .docx>

Complementa a `parchar-informe-apf2.py`, que ya sincronizó el resto. Este sólo
toca lo que dependía de que la versión 1 estuviera realmente en producción, cosa
que hasta el 7 de octubre de 2026 no era cierta.

CIFRAS, TODAS MEDIDAS CONTRA LA NUBE EL 7/10/2026

  Catálogo desplegado   GET /api/productos?per_page=1   total = 884
  Facetas               GET /api/productos/facetas      total = 884
  Salud                 GET /api/salud                  ok y base = true
  Portal                https://botica-san-juan.vercel.app  HTTP 200
  Rutas de gestión      sin token                       401 en las cuatro
  Tiempo de respuesta   6 peticiones, se descarta la primera  mediana 365 ms
  Fusión en Neon        registro del contenedor         3361 -> 884 productos

El mismo mecanismo de reemplazo que el otro script: se reescribe el texto dentro
de los runs existentes para no perder el formato del párrafo.
"""
from __future__ import annotations

import sys
from pathlib import Path

from docx import Document

cambios: list[str] = []
fallos: list[str] = []


def reemplazar(parrafo, viejo: str, nuevo: str) -> bool:
    completo = "".join(r.text for r in parrafo.runs)
    if viejo not in completo:
        return False
    if parrafo.runs:
        parrafo.runs[0].text = completo.replace(viejo, nuevo)
        for r in parrafo.runs[1:]:
            r.text = ""
    return True


def en_documento(doc, viejo: str, nuevo: str, etiqueta: str) -> None:
    n = 0
    for p in doc.paragraphs:
        if reemplazar(p, viejo, nuevo):
            n += 1
    for t in doc.tables:
        for fila in t.rows:
            for celda_ in fila.cells:
                for p in celda_.paragraphs:
                    if reemplazar(p, viejo, nuevo):
                        n += 1
    (cambios if n else fallos).append(
        f"{etiqueta}: {n} sustitución(es)" if n else f"{etiqueta}: NO SE ENCONTRÓ «{viejo[:55]}»"
    )


def celda(doc, tabla_idx: int, fila: int, col: int, nuevo: str, etiqueta: str) -> None:
    try:
        c = doc.tables[tabla_idx].rows[fila].cells[col]
    except IndexError:
        fallos.append(f"{etiqueta}: celda inexistente")
        return
    p = c.paragraphs[0]
    if p.runs:
        p.runs[0].text = nuevo
        for r in p.runs[1:]:
            r.text = ""
    else:
        p.add_run(nuevo)
    cambios.append(f"{etiqueta}: «{nuevo[:55]}»")


def tabla_por_contenido(doc, *claves: str):
    for i, t in enumerate(doc.tables):
        cuerpo = " ".join(c.text for r in t.rows for c in r.cells)
        if all(k in cuerpo for k in claves):
            return i
    return None


def parrafo_por_texto(doc, inicio: str):
    for i, p in enumerate(doc.paragraphs):
        if p.text.strip().startswith(inicio):
            return i
    return None


def insertar_tras(doc, indice: int, textos: list[tuple[str, str]], etiqueta: str) -> None:
    ancla = doc.paragraphs[indice + 1] if indice + 1 < len(doc.paragraphs) else None
    if ancla is None:
        fallos.append(f"{etiqueta}: no hay dónde insertar")
        return
    for estilo, texto in textos:
        nuevo = ancla.insert_paragraph_before(texto)
        try:
            nuevo.style = doc.styles[estilo]
        except KeyError:
            nuevo.style = doc.styles["Normal"]
    cambios.append(f"{etiqueta}: {len(textos)} párrafo(s)")


def main(ruta: Path) -> int:
    doc = Document(str(ruta))

    # ------------------------------------------- XVII. Estado del despliegue
    i = tabla_por_contenido(doc, "botica-san-juan-api.onrender.com")
    if i is not None:
        celda(
            doc, i, 1, 2,
            "44 migraciones aplicadas sobre Neon; 884 productos tras la fusión de los 869 "
            "grupos duplicados, ejecutada por la propia migración durante el arranque del "
            "contenedor (3 361 → 884 en el registro del despliegue).",
            "Despliegue · base de datos",
        )
    else:
        fallos.append("No se localizó la tabla de estado del despliegue")

    # Referencias al catálogo anterior a la fusión.
    en_documento(doc, "3 361 productos, 3 363 lotes y 17 086 unidad",
                 "884 productos, 886 lotes y 4 501 unidad",
                 "Despliegue · recuento del catálogo")
    en_documento(doc, "las 41 figuran como ejecutadas", "las 44 figuran como ejecutadas",
                 "Figura 48 · migraciones en producción")
    en_documento(doc, "las 41 aparecen como aplicadas", "las 44 aparecen como aplicadas",
                 "Anexo C.1 · migraciones en producción")

    # ------------------------------------------- XVII. Nuevo apartado: smoke test
    i = parrafo_por_texto(doc, "17.4. Decisiones del arranque")
    if i is not None:
        insertar_tras(doc, i - 1, [
            ("Heading 2", "17.4. Verificación de humo del entorno desplegado"),
            ("Normal",
             "La guía de la sesión 16 exige comprobar que la versión 1 se ejecuta fuera del "
             "equipo del desarrollador. La verificación se automatizó en "
             "scripts/smoke-test-nube.ps1 y arrojó catorce comprobaciones correctas sobre las "
             "URL públicas, sin emplear credenciales."),
            ("Normal",
             "Disponibilidad: el portal en Vercel responde 200, la API responde 200 en "
             "/api/salud y confirma que la base contesta. Catálogo: /api/productos devuelve "
             "884 productos y /api/productos/facetas la misma cifra, lo que acredita que el "
             "catálogo depurado es el que está en producción y no una copia local. Código "
             "desplegado: POST /api/carrito/cotizar responde 200 —antes devolvía 405, señal de "
             "que el contenedor era anterior— y GET /api/test devuelve 404 tras retirarse de "
             "producción. Control de acceso: las cuatro rutas de gestión comprobadas "
             "—pedidos, usuarios, tablero y reportes de ventas— devuelven 401 sin token, "
             "mientras el catálogo público permanece accesible. Rendimiento: la mediana de "
             "respuesta del catálogo es de 365 milisegundos contra un umbral de 2 000 "
             "establecido en el RNF03."),
            ("Normal",
             "El acceso con las cuentas de demostración se verifica de forma manual y queda "
             "deliberadamente fuera del script: automatizarlo dejaría la contraseña escrita "
             "tanto en el historial del terminal como dentro del propio archivo de evidencia."),
            ("Heading 2", "17.5. El despliegue estuvo roto nueve días sin que se notara"),
            ("Normal",
             "Entre el 5 y el 7 de octubre ningún despliegue llegó a producción, y el síntoma "
             "externo era inexistente: el servicio respondía 200 a toda petición. La "
             "construcción de la imagen fallaba en composer install, Render mantenía en "
             "ejecución el contenedor anterior, y desde fuera lo único observable era que el "
             "código nuevo no aparecía nunca."),
            ("Normal",
             "La causa fue una discrepancia de versiones de PHP. Una actualización de "
             "dependencias ejecutada en un equipo con PHP 8.4 fijó en composer.lock cinco "
             "paquetes de Symfony 8.1 que exigen php >= 8.4.1, mientras la imagen base del "
             "contenedor era dunglas/frankenphp:1-php8.3-alpine, con PHP 8.3.35. El "
             "manifiesto composer.json seguía declarando ^8.2, de modo que nada advertía de "
             "la incompatibilidad hasta que la construcción abortaba."),
            ("Normal",
             "La corrección fue doble. La imagen base pasó a PHP 8.4, y composer.json declara "
             "ahora config.platform.php con el valor 8.4.1, lo que obliga a Composer a "
             "resolver siempre contra la versión de PHP del despliegue y no contra la del "
             "equipo donde se ejecute el comando. Se descartó deliberadamente la alternativa "
             "de añadir --ignore-platform-req=php a la instalación: esa bandera no corrige la "
             "incompatibilidad, silencia el aviso que la detecta y traslada el fallo al tiempo "
             "de ejecución, donde resulta mucho más difícil de diagnosticar."),
            ("Normal",
             "El episodio dejó además una lección de verificación: un servicio que responde 200 "
             "no demuestra que el despliegue haya funcionado. La comprobación que sí lo "
             "demuestra es consultar un elemento que solo exista en la versión nueva, y por eso "
             "el script de humo incluye esa prueba explícitamente."),
        ], "XVII · smoke test y diagnóstico del despliegue")

    # ------------------------------------------- XIX. Evidencias
    i = parrafo_por_texto(doc, "XIX. EVIDENCIAS DEL AVANCE")
    if i is not None:
        insertar_tras(doc, i + 3, [
            ("Normal",
             "E10 recoge la verificación de humo del entorno desplegado, con sus catorce "
             "comprobaciones sobre las URL públicas. Se conserva además la ejecución previa, "
             "en la que cuatro de ellas fallaban: es la que permitió localizar que el "
             "despliegue llevaba nueve días sin completarse. Una evidencia que únicamente "
             "recogiera la ejecución correcta ocultaría precisamente el hallazgo más útil del "
             "avance."),
        ], "XIX · evidencia E10")

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
