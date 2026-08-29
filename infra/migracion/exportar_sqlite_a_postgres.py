#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Exporta los datos de negocio de la base SQLite del proyecto a un archivo .sql
listo para cargarse en PostgreSQL 16.

Uso:
    python infra/migracion/exportar_sqlite_a_postgres.py

Requisitos previos en PostgreSQL:
    1. Crear la base:            CREATE DATABASE botica_san_juan;
    2. Crear el esquema:         php artisan migrate --force
    3. Cargar este archivo:      psql -U postgres -d botica_san_juan -f datos_postgres.sql

El script NO migra las tablas de infraestructura de Laravel (migrations, sessions,
cache, jobs, personal_access_tokens): esas las crea y gestiona el propio framework.
Los tokens de acceso se regeneran al iniciar sesión.
"""
from __future__ import annotations

import os
import sqlite3
import sys
from datetime import datetime

RAIZ = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SQLITE = os.path.join(RAIZ, "botica-san-juan-backend", "database", "database.sqlite")
SALIDA = os.path.join(os.path.dirname(os.path.abspath(__file__)), "datos_postgres.sql")

# Orden de inserción respetando las claves foráneas.
TABLAS = [
    "usuarios",
    "categorias",
    "subcategorias",
    "proveedores",
    "digemid_catalogos",
    "productos",
    "compras",
    "pedidos",
    "pedido_detalles",
    "comprobantes_electronicos",
    "comisiones",
    "carrito",
    "contacto",
]

# Columnas booleanas: en SQLite viajan como 0/1, en PostgreSQL deben ser false/true.
BOOLEANOS = {"tinyint(1)", "boolean", "bool"}
ENTEROS = {"integer", "int", "bigint", "smallint"}
DECIMALES = {"numeric", "decimal", "real", "float", "double"}

CUARENTENA = os.path.join(os.path.dirname(os.path.abspath(__file__)), "filas_rechazadas.csv")


def es_numerico(valor) -> bool:
    """Indica si un valor puede convertirse a numero."""
    if isinstance(valor, (int, float)):
        return True
    try:
        float(str(valor).replace(",", ""))
        return True
    except (TypeError, ValueError):
        return False


def validar_fila(fila, columnas, tipos) -> list[tuple[str, object]]:
    """
    Devuelve los problemas de tipo de una fila.

    SQLite tiene tipado debil y admite texto en columnas declaradas como
    numericas; PostgreSQL no. Detectar esto antes de generar el SQL evita que
    la carga completa se aborte por unas pocas filas con datos corruptos.
    """
    problemas = []
    for col in columnas:
        tipo = (tipos.get(col) or "").lower()
        valor = fila[col]
        if valor is None:
            continue
        if tipo in ENTEROS or tipo in DECIMALES or tipo in BOOLEANOS:
            if not es_numerico(valor):
                problemas.append((col, valor))
    return problemas


def escapar(valor, tipo_sqlite: str) -> str:
    """Convierte un valor de SQLite a un literal SQL válido en PostgreSQL."""
    if valor is None:
        return "NULL"

    tipo = (tipo_sqlite or "").lower()

    if tipo in BOOLEANOS:
        return "true" if int(valor) == 1 else "false"

    if isinstance(valor, (int, float)):
        return str(valor)

    if isinstance(valor, bytes):
        return "'\\x" + valor.hex() + "'"

    texto = str(valor).replace("\\", "\\\\").replace("'", "''")
    return "'" + texto + "'"


def main() -> int:
    if not os.path.exists(SQLITE):
        print(f"ERROR: no se encontró la base SQLite en {SQLITE}", file=sys.stderr)
        return 1

    con = sqlite3.connect(SQLITE)
    con.row_factory = sqlite3.Row
    cur = con.cursor()

    existentes = {
        fila[0]
        for fila in cur.execute("SELECT name FROM sqlite_master WHERE type='table'")
    }

    lineas: list[str] = [
        "-- Datos exportados desde SQLite para PostgreSQL 16",
        f"-- Proyecto: Sistema Web de Gestión y Control - Botica San Juan",
        f"-- Generado: {datetime.now().isoformat(timespec='seconds')}",
        "--",
        "-- Requiere que el esquema ya exista (php artisan migrate --force).",
        "",
        "BEGIN;",
        "SET session_replication_role = 'replica';  -- desactiva FK durante la carga",
        "",
    ]

    resumen: list[tuple[str, int, int]] = []
    rechazadas: list[list[str]] = []

    for tabla in TABLAS:
        if tabla not in existentes:
            print(f"  aviso: la tabla '{tabla}' no existe en SQLite, se omite")
            continue

        info = list(cur.execute(f"PRAGMA table_info({tabla})"))
        columnas = [c[1] for c in info]
        tipos = {c[1]: c[2] for c in info}

        filas = list(cur.execute(f'SELECT * FROM "{tabla}"'))

        validas = []
        for fila in filas:
            problemas = validar_fila(fila, columnas, tipos)
            if problemas:
                detalle = "; ".join(f"{col}={valor!r}" for col, valor in problemas)
                rechazadas.append([
                    tabla,
                    str(fila["id"] if "id" in fila.keys() else ""),
                    detalle,
                    " | ".join(f"{c}={fila[c]!r}" for c in columnas),
                ])
            else:
                validas.append(fila)

        resumen.append((tabla, len(validas), len(filas) - len(validas)))

        lineas.append(f"-- {tabla}: {len(validas)} fila(s)")
        lineas.append(f'TRUNCATE TABLE "{tabla}" CASCADE;')

        if validas:
            cols = ", ".join(f'"{c}"' for c in columnas)
            for fila in validas:
                valores = ", ".join(escapar(fila[c], tipos[c]) for c in columnas)
                lineas.append(f'INSERT INTO "{tabla}" ({cols}) VALUES ({valores});')

        lineas.append("")

    lineas.append("SET session_replication_role = 'origin';  -- reactiva FK")
    lineas.append("")
    lineas.append("-- Reinicio de las secuencias al máximo id de cada tabla")
    for tabla, _, _ in resumen:
        lineas.append(
            f"SELECT setval(pg_get_serial_sequence('{tabla}', 'id'), "
            f"COALESCE((SELECT MAX(id) FROM \"{tabla}\"), 1), true);"
        )

    lineas.append("")
    lineas.append("COMMIT;")
    lineas.append("")

    with open(SALIDA, "w", encoding="utf-8") as f:
        f.write("\n".join(lineas))

    total = sum(n for _, n, _ in resumen)
    total_rechazadas = sum(r for _, _, r in resumen)

    print(f"\nArchivo generado: {SALIDA}")
    print(f"Tablas exportadas: {len(resumen)}  |  Filas migradas: {total}")
    if total_rechazadas:
        print(f"Filas rechazadas por datos corruptos: {total_rechazadas}")
    print()
    for tabla, n, rech in resumen:
        marca = f"  ({rech} rechazada/s)" if rech else ""
        print(f"   {tabla:30s} {n:>6}{marca}")

    if rechazadas:
        import csv

        with open(CUARENTENA, "w", encoding="utf-8-sig", newline="") as f:
            escritor = csv.writer(f, delimiter=";")
            escritor.writerow(["tabla", "id", "problema", "fila_completa"])
            escritor.writerows(rechazadas)

        print(f"\nDetalle de las filas rechazadas: {CUARENTENA}")
        print("Estas filas tienen columnas desalineadas en la base actual (por ejemplo,")
        print("texto en la columna 'stock'). PostgreSQL las rechaza porque valida tipos.")
        print("Corrigelas en el sistema y vuelve a ejecutar este script.")

    con.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
