/**
 * Migra las clases de color fijas de Tailwind a los tokens del design system.
 *
 *   node scripts/migrar-tokens.mjs --dry     → informe, no escribe nada
 *   node scripts/migrar-tokens.mjs           → aplica
 *   node scripts/migrar-tokens.mjs src/admin/views/AdminHomeView.vue
 *
 * POR QUÉ UN SCRIPT Y NO A MANO
 * Son más de mil sustituciones repartidas en once archivos. A mano se cometen
 * errores silenciosos —un `text-slate-500` que se queda sin migrar no rompe la
 * compilación, sólo se ve mal en modo oscuro— y no queda registro de qué se
 * cambió. Aquí la tabla es explícita, el informe dice qué no supo traducir, y
 * lo que no está en la tabla no se toca.
 *
 * QUÉ NO HACE
 * No decide diseño. Los degradados azul-índigo, los acentos de marca y
 * cualquier color con intención propia quedan fuera de la tabla a propósito:
 * esos se revisan uno por uno, porque la elección es de criterio y no de
 * equivalencia.
 */

import { readFileSync, writeFileSync } from 'node:fs'
import { globSync } from 'node:fs'
import { argv } from 'node:process'

/* --------------------------------------------------------------------------
   Tabla de equivalencias
   --------------------------------------------------------------------------
   El orden importa: se aplica de la clave más larga a la más corta, para que
   `bg-slate-50` no la capture antes una regla de `bg-slate-500`.
*/
const MAPA = {
  /* --- Superficies ------------------------------------------------------ */
  'bg-white': 'bg-superficie-elevada',
  'bg-slate-50': 'bg-superficie-hundida',
  'bg-gray-50': 'bg-superficie-hundida',
  'bg-slate-100': 'bg-superficie-interactiva',
  'bg-gray-100': 'bg-superficie-interactiva',
  'bg-slate-200': 'bg-superficie-interactiva-activa',
  'bg-gray-200': 'bg-superficie-interactiva-activa',

  /* --- Texto ------------------------------------------------------------ */
  'text-slate-900': 'text-texto-primario',
  'text-gray-900': 'text-texto-primario',
  'text-slate-800': 'text-texto-primario',
  'text-gray-800': 'text-texto-primario',
  'text-slate-700': 'text-texto-secundario',
  'text-gray-700': 'text-texto-secundario',
  'text-slate-600': 'text-texto-secundario',
  'text-gray-600': 'text-texto-secundario',
  'text-slate-500': 'text-texto-terciario',
  'text-gray-500': 'text-texto-terciario',
  'text-slate-400': 'text-texto-terciario',
  'text-gray-400': 'text-texto-terciario',
  'text-slate-300': 'text-texto-deshabilitado',
  'text-gray-300': 'text-texto-deshabilitado',

  /* --- Bordes y anillos -------------------------------------------------- */
  'border-slate-200': 'border-borde-sutil',
  'border-gray-200': 'border-borde-sutil',
  'border-slate-300': 'border-borde-base',
  'border-gray-300': 'border-borde-base',
  'border-slate-400': 'border-borde-fuerte',
  'border-gray-400': 'border-borde-fuerte',
  'ring-slate-200': 'ring-borde-sutil',
  'ring-gray-200': 'ring-borde-sutil',
  'ring-slate-300': 'ring-borde-base',
  'ring-gray-300': 'ring-borde-base',
  'divide-slate-200': 'divide-borde-sutil',
  'divide-gray-200': 'divide-borde-sutil',
  'divide-slate-100': 'divide-borde-sutil',
  'divide-gray-100': 'divide-borde-sutil',

  /* --- Estados semánticos ------------------------------------------------
     Estos sí son equivalencias, no decisiones: el rojo de "error" del catálogo
     por defecto pasa al rojo de "peligro" del design system, con la misma
     intención y mejor contraste. */
  'bg-red-50': 'bg-peligro-50',
  'bg-rose-50': 'bg-peligro-50',
  'bg-red-100': 'bg-peligro-50',
  'bg-rose-100': 'bg-peligro-50',
  'text-red-600': 'text-peligro-600',
  'text-rose-600': 'text-peligro-600',
  'text-red-700': 'text-peligro-700',
  'text-rose-700': 'text-peligro-700',
  'text-red-800': 'text-peligro-700',
  'border-red-200': 'border-peligro-500/30',
  'border-rose-200': 'border-peligro-500/30',
  'bg-red-600': 'bg-peligro-600',
  'bg-rose-600': 'bg-peligro-600',

  'bg-amber-50': 'bg-alerta-50',
  'bg-yellow-50': 'bg-alerta-50',
  'bg-amber-100': 'bg-alerta-50',
  'bg-yellow-100': 'bg-alerta-50',
  'text-amber-600': 'text-alerta-600',
  'text-yellow-600': 'text-alerta-600',
  'text-amber-700': 'text-alerta-700',
  'text-yellow-700': 'text-alerta-700',
  'text-amber-800': 'text-alerta-700',
  'bg-amber-500': 'bg-alerta-500',

  'bg-emerald-50': 'bg-exito-50',
  'bg-green-50': 'bg-exito-50',
  'bg-emerald-100': 'bg-exito-50',
  'bg-green-100': 'bg-exito-50',
  'text-emerald-600': 'text-exito-600',
  'text-green-600': 'text-exito-600',
  'text-emerald-700': 'text-exito-700',
  'text-green-700': 'text-exito-700',
  'text-emerald-800': 'text-exito-700',
  'text-green-800': 'text-exito-700',
  'bg-emerald-600': 'bg-exito-600',
  'bg-green-600': 'bg-exito-600',
}

/* Clases que se dejan a propósito para revisión humana. */
const REVISAR = /\b(from|via|to)-(blue|indigo|violet|purple|fuchsia|cyan|sky|emerald|green)-[0-9]+\b/

const claves = Object.keys(MAPA).sort((a, b) => b.length - a.length)

const soloInforme = argv.includes('--dry')
const rutasPedidas = argv.slice(2).filter((a) => !a.startsWith('--'))

const archivos = rutasPedidas.length
  ? rutasPedidas
  : globSync('src/admin/**/*.vue')

let totalCambios = 0
const pendientes = new Map()

for (const ruta of archivos) {
  const original = readFileSync(ruta, 'utf8')
  let texto = original
  let cambiosArchivo = 0

  for (const clave of claves) {
    /* Se exige frontera de palabra a ambos lados para no partir
       `bg-slate-50` dentro de `bg-slate-500`, ni tocar un sufijo de opacidad
       que se conserva aparte. */
    const patron = new RegExp(`(?<![\\w-])${clave}(?![\\w-])`, 'g')
    const encontrados = texto.match(patron)

    if (encontrados) {
      texto = texto.replace(patron, MAPA[clave])
      cambiosArchivo += encontrados.length
    }
  }

  /* Lo que queda sin traducir, para el informe. */
  const restantes = texto.match(
    /\b(bg|text|border|ring|divide|from|to|via|placeholder|shadow|outline|accent|caret)-(white|black|gray|slate|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)(-[0-9]+)?(\/[0-9]+)?\b/g,
  ) ?? []

  for (const clase of restantes) {
    const actual = pendientes.get(clase) ?? { total: 0, archivos: new Set() }
    actual.total++
    actual.archivos.add(ruta.split('/').pop())
    pendientes.set(clase, actual)
  }

  if (cambiosArchivo > 0) {
    totalCambios += cambiosArchivo

    if (!soloInforme) writeFileSync(ruta, texto, 'utf8')

    console.log(
      `${soloInforme ? '[simulado] ' : ''}${ruta.padEnd(52)} ${String(cambiosArchivo).padStart(4)} cambios` +
        (restantes.length ? `  · quedan ${restantes.length}` : '  · limpio'),
    )
  }
}

console.log(`\n${soloInforme ? 'SIMULACIÓN · ' : ''}Total sustituido: ${totalCambios}`)

if (pendientes.size > 0) {
  console.log(`\nSin traducir (${[...pendientes.values()].reduce((n, p) => n + p.total, 0)} usos, ${pendientes.size} clases distintas):`)

  const ordenadas = [...pendientes.entries()].sort((a, b) => b[1].total - a[1].total)

  for (const [clase, info] of ordenadas.slice(0, 40)) {
    const marca = REVISAR.test(clase) ? ' ← decisión de diseño' : ''
    console.log(`   ${String(info.total).padStart(4)}  ${clase.padEnd(28)} ${[...info.archivos].slice(0, 3).join(', ')}${marca}`)
  }

  if (ordenadas.length > 40) console.log(`   … y ${ordenadas.length - 40} clases más`)
}
