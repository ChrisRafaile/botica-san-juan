#!/usr/bin/env python3
"""
Extrae de la salida literal de una suite el guion que dibuja render_terminal.py.

    python docs/scripts/guion-de-suite.py backend  <salida.txt> <guion.json> "<titulo>"
    python docs/scripts/guion-de-suite.py frontend <salida.txt> <guion.json> "<titulo>"

POR QUE NO SE ESCRIBE EL GUION A MANO

Una figura con la cifra de la suite es justo el sitio donde un numero copiado
a mano se queda viejo sin que nadie lo note. El guion sale de la salida real, y
este script se niega a escribirlo si la salida no cuadra consigo misma:

    backend   las marcas de prueba superada suman el total del resumen
    frontend  los archivos listados son los que dice el resumen, y las pruebas
              por archivo suman el total

Si alguna comprobacion falla no se escribe nada: una figura que no cuadra es
peor que no tener figura.
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path


def guion_backend(texto: str) -> list[list[str]]:
    clases = re.findall(r'^\s+PASS\s+(Tests\\\S+)', texto, flags=re.M)
    superadas = len(re.findall(r'^\s+✓ ', texto, flags=re.M))
    resumen = re.search(r'Tests:\s+(\d+) passed \((\d+) assertions\)', texto)
    duracion = re.search(r'Duration:\s+(\S+)', texto)

    if not resumen or not duracion:
        sys.exit('ABORTADO: la salida no trae el resumen de una suite en verde.')
    if 'FAIL' in texto or ' failed' in texto:
        sys.exit('ABORTADO: la salida contiene pruebas fallidas.')
    if superadas != int(resumen.group(1)):
        sys.exit(f'ABORTADO: {superadas} marcas de prueba superada frente a '
                 f'{resumen.group(1)} en el resumen.')

    lineas = [['prompt', 'php artisan test']]
    lineas += [['pass', clase] for clase in clases]
    lineas.append(['ok', f'Tests:    {resumen.group(1)} passed ({resumen.group(2)} assertions)'])
    lineas.append(['tenue', f'Duration: {duracion.group(1)}'])
    print(f'backend: {len(clases)} clases, {superadas} pruebas, {resumen.group(2)} aserciones')
    return lineas


def guion_frontend(texto: str) -> list[list[str]]:
    archivos = re.findall(r'^\s*✓ (src/\S+) \((\d+) tests?\)', texto, flags=re.M)
    res_archivos = re.search(r'Test Files\s+(\d+) passed \((\d+)\)', texto)
    res_pruebas = re.search(r'Tests\s+(\d+) passed \((\d+)\)', texto)
    duracion = re.search(r'Duration\s+(\S+)', texto)

    if not res_archivos or not res_pruebas or not duracion:
        sys.exit('ABORTADO: la salida no trae el resumen de una suite en verde.')
    if 'failed' in texto:
        sys.exit('ABORTADO: la salida contiene pruebas fallidas.')
    if len(archivos) != int(res_archivos.group(1)):
        sys.exit(f'ABORTADO: {len(archivos)} archivos listados frente a '
                 f'{res_archivos.group(1)} en el resumen.')
    suma = sum(int(n) for _, n in archivos)
    if suma != int(res_pruebas.group(1)):
        sys.exit(f'ABORTADO: las pruebas por archivo suman {suma} frente a '
                 f'{res_pruebas.group(1)} en el resumen.')

    lineas = [['prompt', '.\\node_modules\\.bin\\vitest.cmd run']]
    lineas += [['pass', f'{ruta}  ({n} tests)'] for ruta, n in archivos]
    lineas.append(['ok', f'Test Files  {res_archivos.group(1)} passed ({res_archivos.group(2)})'])
    lineas.append(['ok', f'Tests  {res_pruebas.group(1)} passed ({res_pruebas.group(2)})'])
    lineas.append(['tenue', f'Duration  {duracion.group(1)}'])
    print(f'frontend: {len(archivos)} archivos, {suma} pruebas')
    return lineas


def main() -> None:
    if len(sys.argv) != 5 or sys.argv[1] not in ('backend', 'frontend'):
        sys.exit(__doc__)

    texto = Path(sys.argv[2]).read_text(encoding='utf-8')
    lineas = guion_backend(texto) if sys.argv[1] == 'backend' else guion_frontend(texto)

    Path(sys.argv[3]).write_text(
        json.dumps({'titulo': sys.argv[4], 'lineas': lineas}, ensure_ascii=False, indent=1) + '\n',
        encoding='utf-8',
    )


if __name__ == '__main__':
    main()
