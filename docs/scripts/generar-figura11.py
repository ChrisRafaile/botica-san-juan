#!/usr/bin/env python3
"""
Figura 11 · Modelo de datos conceptual.
=============================================================================
POR QUE EXISTE ESTE SCRIPT

La Figura 11 del APF3 lleva rotulado «26 migraciones versionadas» DENTRO de la
imagen. Es un PNG: no hay forma de corregir esa cifra editando el documento, y
por eso la observacion O-B2 quedo abierta entregable tras entregable mientras
el numero envejecia (26 en la imagen, 49 en la fila de observaciones, 54 de
verdad).

El arreglo no es retocar el PNG. Es que la figura deje de ser un archivo
opaco y pase a generarse desde aqui, con la cifra LEIDA del repositorio y no
tecleada. A partir de ahora el numero no puede quedarse viejo sin que alguien
vuelva a ejecutar esto y lo vea.

QUE NO HACE

No toca el .docx del APF3. Ese documento ya se entrego y se califico: es un
registro historico y se deja byte a byte como esta. Lo que produce este script
es un archivo independiente para montar el informe final.

SOBRE EL ALCANCE DEL DIAGRAMA

Se conservan las MISMAS trece entidades del original, que son el nucleo del
MVP. El esquema de hoy tiene mas tablas —recetas, lotes, eventos de operacion,
conteos, comisiones de cobro— y meterlas todas convertiria un diagrama
conceptual en un plano ilegible. El subtitulo dice cuantas migraciones hay en
total; el dibujo muestra el nucleo. Las dos cosas son ciertas y no se
contradicen.

USO

    python docs/scripts/generar-figura11.py

Escribe docs/evidencias/figura11/Figura11-modelo-de-datos.png
"""
from __future__ import annotations

import sys
from pathlib import Path

import matplotlib

matplotlib.use("Agg")

import matplotlib.pyplot as plt
from matplotlib.patches import FancyArrowPatch, FancyBboxPatch

# --------------------------------------------------------------------------
# Paleta, muestreada del PNG original para que la figura nueva no desentone
# con las demas del informe.
# --------------------------------------------------------------------------
VERDE = "#2E9367"
MORADO = "#86188F"
NARANJA = "#B45309"
AZUL = "#1D4E89"
TEAL = "#0E7490"
ROJO = "#B91C1C"

TITULO = "#1F6E4C"
GRIS_TEXTO = "#6D7C92"
TINTA = "#1F2937"
FLECHA = "#8C9BB0"

# --------------------------------------------------------------------------
# Entidades: (clave, titulo, color, columna_izq, fila_sup, ancho, campos)
#
# El lienzo es de 100 x 70 unidades. Las coordenadas salen de medir el PNG
# original y reescalarlo, para que la disposicion sea reconocible.
# --------------------------------------------------------------------------
ENTIDADES = [
    ("digemid", "DIGEMID_CATALOGOS", VERDE, 4.0, 62.0, 19.0, [
        "PK id",
        "codigo_digemid (UQ)",
        "principio_activo",
        "requiere_receta",
    ]),
    ("categorias", "CATEGORIAS", MORADO, 27.0, 62.0, 19.0, [
        "PK id",
        "nombre",
        "slug (UQ)",
        "activo",
    ]),
    ("subcategorias", "SUBCATEGORIAS", MORADO, 49.5, 62.0, 19.5, [
        "PK id",
        "FK categoria_id",
        "nombre",
        "slug",
    ]),
    ("proveedores", "PROVEEDORES", NARANJA, 73.0, 62.0, 23.0, [
        "PK id",
        "ruc (UQ), razon_social",
        "contacto, telefono",
        "activo",
    ]),
    ("usuarios", "USUARIOS", AZUL, 4.0, 48.0, 19.0, [
        "PK id",
        "nombre, email (UQ)",
        "password (hash bcrypt)",
        "rol (admin / cliente)",
        "mfa_secret",
        "mfa_enabled",
    ]),
    ("productos", "PRODUCTOS", VERDE, 27.0, 48.0, 42.0, [
        "PK id  -  nombre  -  concentracion  -  presentacion  -  laboratorio",
        "FK categoria_id  -  FK subcategoria_id  -  codigo_barras (UQ)",
        "codigo_digemid  -  principio_activo  -  requiere_receta",
        "stock  -  stock_minimo  -  stock_reposicion",
        "unidad_base  -  venta_fraccionada  -  unidades_por_blister",
        "precio  -  precio_blister  -  precio_caja",
    ]),
    ("compras", "COMPRAS", NARANJA, 73.0, 48.0, 23.0, [
        "PK id",
        "FK proveedor_id",
        "numero_compra (UQ)",
        "fecha_compra",
        "estado",
        "total",
    ]),
    ("carrito", "CARRITO", TEAL, 4.0, 30.0, 19.0, [
        "PK id",
        "FK usuario_id",
        "FK producto_id",
        "cantidad",
    ]),
    ("pedidos", "PEDIDOS", TEAL, 27.0, 30.0, 19.0, [
        "PK id",
        "FK usuario_id",
        "fecha",
        "estado",
        "total",
    ]),
    ("comprobantes", "COMPROBANTES_ELECTRONICOS", ROJO, 49.5, 30.0, 21.0, [
        "PK id  -  FK pedido_id",
        "tipo (boleta / factura)",
        "serie + numero (UQ)",
        "estado_sunat",
        "hash_documento",
        "xml_path",
    ]),
    ("comisiones", "COMISIONES", ROJO, 73.0, 30.0, 23.0, [
        "PK id",
        "FK comprobante_id",
        "monto",
        "estado",
    ]),
    ("detalles", "PEDIDO_DETALLES", TEAL, 27.0, 14.0, 42.0, [
        "PK id  -  FK pedido_id  -  FK producto_id",
        "cantidad  -  precio_unitario  -  subtotal",
        "unidad_venta (unidad / blister / caja)",
        "numero_lote  -  fecha_vencimiento",
    ]),
]

# (origen, destino, lado) · lado: 'v' vertical, 'h' horizontal
RELACIONES = [
    ("categorias", "subcategorias", "h"),
    ("categorias", "productos", "v"),
    ("subcategorias", "productos", "v"),
    ("proveedores", "compras", "v"),
    ("digemid", "productos", "v"),
    ("usuarios", "carrito", "v"),
    ("usuarios", "pedidos", "v"),
    ("productos", "compras", "h"),
    ("productos", "detalles", "v"),
    ("pedidos", "comprobantes", "h"),
    ("pedidos", "detalles", "v"),
    ("comprobantes", "comisiones", "h"),
]

ALTO_TITULO = 2.6
ALTO_CAMPO = 2.0
PADDING = 1.0


def contar_migraciones(raiz: Path) -> int:
    """
    Cuenta los archivos de migracion del backend.

    Esta linea es el motivo de todo el script: la cifra del subtitulo sale de
    contar archivos, no de que alguien la escriba. Un numero tecleado dentro de
    una imagen es exactamente lo que dejo la figura mintiendo durante tres
    entregables.
    """
    carpeta = raiz / "botica-san-juan-backend" / "database" / "migrations"

    if not carpeta.is_dir():
        raise SystemExit(f"No encuentro las migraciones en {carpeta}")

    return len(sorted(carpeta.glob("*.php")))


def alto_de(entidad) -> float:
    return ALTO_TITULO + len(entidad[6]) * ALTO_CAMPO + PADDING


def dibujar(migraciones: int, destino: Path) -> None:
    fig, ax = plt.subplots(figsize=(21.18, 14.90), dpi=100)
    ax.set_xlim(0, 100)
    ax.set_ylim(0, 70)
    ax.axis("off")
    fig.patch.set_facecolor("white")

    cajas: dict[str, tuple[float, float, float, float]] = {}

    for entidad in ENTIDADES:
        clave, titulo, color, x, y_sup, ancho, campos = entidad
        alto = alto_de(entidad)
        y_inf = y_sup - alto

        cajas[clave] = (x, y_inf, ancho, alto)

        # Franja del titulo.
        ax.add_patch(FancyBboxPatch(
            (x, y_sup - ALTO_TITULO), ancho, ALTO_TITULO,
            boxstyle="square,pad=0", facecolor=color, edgecolor=color, zorder=3,
        ))
        ax.text(
            x + ancho / 2, y_sup - ALTO_TITULO / 2, titulo,
            ha="center", va="center", color="white",
            # Un titulo largo —COMPROBANTES_ELECTRONICOS— se sale de su caja
            # con el tamano comun. Se encoge en lugar de ensanchar la caja,
            # que descuadraria la columna entera.
            fontsize=13.5 if len(titulo) <= 20 else 12.0,
            fontweight="bold", zorder=4,
        )

        # Cuerpo.
        ax.add_patch(FancyBboxPatch(
            (x, y_inf), ancho, alto - ALTO_TITULO,
            boxstyle="square,pad=0", facecolor="white",
            edgecolor=color, linewidth=1.6, zorder=3,
        ))

        for i, campo in enumerate(campos):
            ax.text(
                x + 0.8, y_sup - ALTO_TITULO - 0.6 - i * ALTO_CAMPO - ALTO_CAMPO / 2 + 0.6,
                campo, ha="left", va="center", color=TINTA, fontsize=11.5, zorder=4,
            )

    for origen, destino_rel, lado in RELACIONES:
        ox, oy, oa, oalto = cajas[origen]
        dx, dy, da, dalto = cajas[destino_rel]

        curva = 0.0

        if lado == "h":
            punto_a = (ox + oa, oy + oalto / 2)
            punto_b = (dx, dy + dalto / 2)
        else:
            # Las dos cajas rara vez tienen el mismo ancho ni el mismo centro.
            # Si la flecha se traza de centro a centro sale en diagonal y
            # cruza por encima de otras cajas: fue lo que pasó con
            # PEDIDOS -> PEDIDO_DETALLES en la primera version.
            #
            # Se usa el centro de la FRANJA COMUN de las dos, de modo que la
            # flecha cae recta siempre que haya solape horizontal, que es el
            # caso normal.
            izq = max(ox, dx)
            der = min(ox + oa, dx + da)

            if der > izq:
                centro = (izq + der) / 2
                punto_a = (centro, oy)
                punto_b = (centro, dy + dalto)
            else:
                # Sin solape no hay recta posible: se curva, como en el
                # diagrama original, en lugar de cortar en diagonal.
                punto_a = (ox + oa / 2, oy)
                punto_b = (dx + da / 2, dy + dalto)
                curva = -0.2

        ax.add_patch(FancyArrowPatch(
            punto_a, punto_b,
            arrowstyle="-|>", mutation_scale=22, linewidth=2.0,
            color=FLECHA, connectionstyle=f"arc3,rad={curva}",
            shrinkA=2, shrinkB=2, zorder=2,
        ))

    ax.text(
        50, 68.4, "Modelo de datos conceptual de la Botica San Juan (MVP)",
        ha="center", va="center", color=TITULO, fontsize=23, fontweight="bold",
    )
    ax.text(
        50, 66.4,
        "Entidades principales y relaciones - PostgreSQL 16 - "
        f"{migraciones} migraciones versionadas",
        ha="center", va="center", color=GRIS_TEXTO, fontsize=14,
    )
    ax.text(
        4, 1.6,
        "Leyenda:  PK = clave primaria  |  FK = clave foranea  |  UQ = restriccion unica  |  "
        "Integridad referencial con ON DELETE RESTRICT / CASCADE segun criticidad del dato.",
        ha="left", va="center", color=GRIS_TEXTO, fontsize=11.5,
    )

    destino.parent.mkdir(parents=True, exist_ok=True)
    fig.savefig(destino, dpi=100, facecolor="white", bbox_inches="tight", pad_inches=0.25)
    plt.close(fig)


def main() -> int:
    raiz = Path(__file__).resolve().parents[2]
    migraciones = contar_migraciones(raiz)
    destino = raiz / "docs" / "evidencias" / "figura11" / "Figura11-modelo-de-datos.png"

    dibujar(migraciones, destino)

    print(f"Migraciones contadas en el repositorio: {migraciones}")
    print(f"Figura escrita en: {destino}")
    print("El .docx del APF3 NO se ha tocado.")

    return 0


if __name__ == "__main__":
    sys.exit(main())
