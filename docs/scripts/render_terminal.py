#!/usr/bin/env python3
"""
Dibuja una salida de consola como una ventana de terminal.

    python docs/scripts/render_terminal.py <guion.json> <salida.png>

POR QUE EXISTE

Las figuras de verificacion del informe muestran salidas de consola. Una
captura de pantalla real de la terminal de Windows sale con el ancho, la
fuente y el contraste que tenga la maquina ese dia, y en el documento se lee
mal. Esto compone la MISMA salida con un ancho y un contraste fijos.

REGLA QUE NO SE ROMPE

El texto que entra aqui es la salida LITERAL del comando, copiada del archivo
que la recogio. El renderizador solo decide tipografia y color; no redacta,
no resume y no corrige. Si una cifra de la imagen no coincide con la del
comando, el error esta en quien preparo el guion, no aqui.

FORMATO DEL GUION (JSON)

    {"titulo": "...", "lineas": [["tipo", "texto"], ...]}

    tipo: prompt | pass | fail | info | ok | tenue
"""
from __future__ import annotations

import json
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ANCHO = 2200
MARGEN = 46
RADIO = 26

FONDO = (10, 12, 16)
TARJETA = (19, 26, 31)
BARRA = (24, 32, 38)
LINEA_BARRA = (38, 48, 56)

TEXTO = (201, 209, 217)
TENUE = (139, 148, 158)
VERDE = (63, 185, 80)
ROJO = (248, 81, 73)
AMBAR = (210, 153, 34)

MONO = "/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf"
MONO_B = "/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf"

CUERPO = 30
INTERLINEA = 44


def _fuente(ruta: str, tam: int) -> ImageFont.FreeTypeFont:
    return ImageFont.truetype(ruta, tam)


def _segmentos(tipo: str, texto: str) -> list[tuple[str, tuple[int, int, int], bool]]:
    """Parte una linea en tramos con su color. Devuelve (texto, color, negrita)."""
    if tipo == "prompt":
        return [("> ", VERDE, True), (texto, VERDE, True)]

    if tipo in ("pass", "fail"):
        etiqueta = "PASS" if tipo == "pass" else "FAIL"
        color = VERDE if tipo == "pass" else ROJO
        return [("  ", TEXTO, False), (etiqueta, color, True), ("  " + texto, TEXTO, False)]

    if tipo == "ok":
        """Resalta en verde la palabra clave de la linea de totales, igual que
           hace el propio ejecutor de pruebas."""
        for clave in ("passed", "Ran", "OK"):
            if clave in texto:
                i = texto.index(clave)
                return [
                    (texto[:i], TEXTO, False),
                    (clave, VERDE, True),
                    (texto[i + len(clave):], TEXTO, False),
                ]
        return [(texto, VERDE, True)]

    if tipo == "tenue":
        return [(texto, TENUE, False)]

    if tipo == "aviso":
        return [(texto, AMBAR, False)]

    return [(texto, TEXTO, False)]


def dibujar(guion: dict, destino: Path) -> None:
    lineas = guion["lineas"]
    titulo = guion.get("titulo", "")

    alto_barra = 104
    alto_cuerpo = INTERLINEA * len(lineas) + 56
    alto_tarjeta = alto_barra + alto_cuerpo
    alto = alto_tarjeta + MARGEN * 2

    lienzo = Image.new("RGB", (ANCHO, alto), FONDO)
    d = ImageDraw.Draw(lienzo)

    x0, y0 = MARGEN, MARGEN
    x1, y1 = ANCHO - MARGEN, alto - MARGEN
    d.rounded_rectangle([x0, y0, x1, y1], RADIO, fill=TARJETA)
    d.rounded_rectangle([x0, y0, x1, y0 + alto_barra], RADIO, fill=BARRA)
    d.rectangle([x0, y0 + alto_barra - RADIO, x1, y0 + alto_barra], fill=BARRA)
    d.line([x0, y0 + alto_barra, x1, y0 + alto_barra], fill=LINEA_BARRA, width=2)

    cy = y0 + alto_barra // 2
    for i, color in enumerate([(255, 95, 86), (255, 189, 46), (39, 201, 63)]):
        cx = x0 + 44 + i * 42
        d.ellipse([cx - 13, cy - 13, cx + 13, cy + 13], fill=color)

    f_tit = _fuente(MONO, 32)
    d.text((x0 + 200, cy), titulo, font=f_tit, fill=TENUE, anchor="lm")

    f = _fuente(MONO, CUERPO)
    fb = _fuente(MONO_B, CUERPO)

    y = y0 + alto_barra + 34
    for tipo, texto in lineas:
        x = x0 + 56
        for tramo, color, negrita in _segmentos(tipo, texto):
            fuente = fb if negrita else f
            d.text((x, y), tramo, font=fuente, fill=color)
            x += d.textlength(tramo, font=fuente)
        y += INTERLINEA

    destino.parent.mkdir(parents=True, exist_ok=True)
    lienzo.save(destino, "PNG")
    print(f"{destino}  {lienzo.size[0]}x{lienzo.size[1]}")


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(__doc__)
        sys.exit(2)
    dibujar(json.loads(Path(sys.argv[1]).read_text(encoding="utf-8")), Path(sys.argv[2]))
