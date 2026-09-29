/**
 * Segunda pasada: acentos de marca y degradados.
 *
 *   node scripts/migrar-acentos.mjs --dry
 *   node scripts/migrar-acentos.mjs
 *
 * A DIFERENCIA DE LA PRIMERA PASADA, AQUÍ SÍ HAY UNA DECISIÓN
 * La primera pasada traducía equivalencias: un gris es un gris. Esta cambia el
 * aspecto a propósito, y conviene dejar escrito el porqué.
 *
 * Los degradados azul→índigo, violeta→fucsia y esmeralda→verde venían del
 * arranque del proyecto, cuando cada pantalla eligió su color sin criterio
 * común. El resultado era que el botón principal de una vista era azul, el de
 * otra morado y el de otra verde, sin que la diferencia significara nada. El
 * design system ya fijó la regla contraria: el color comunica jerarquía, no
 * decora, y la acción principal de cada pantalla es una sola y va en verde
 * botica.
 *
 * Por eso los degradados se aplanan a color sólido de marca en lugar de
 * traducirse a un degradado equivalente: el degradado mismo era el problema.
 *
 * El foco de los formularios pasa del azul por defecto de Tailwind al patrón
 * que ya usan las vistas migradas, para que todos los campos del panel se
 * comporten igual.
 */

import { readFileSync, writeFileSync } from 'node:fs'
import { globSync } from 'node:fs'
import { argv } from 'node:process'

/* Se sustituyen frases completas, no clases sueltas: un degradado son tres o
   cuatro clases que sólo tienen sentido juntas, y reemplazarlas por separado
   dejaría un `from-` huérfano sin `to-`. */
const FRASES = [
  /* --- Foco de formularios --------------------------------------------- */
  [/focus:border-blue-500\b/g, 'focus:border-borde-marca'],
  [/focus:ring-blue-100\b/g, 'focus:ring-botica-500/20'],
  [/focus:ring-blue-500\b/g, 'focus:ring-botica-500/20'],
  [/focus:ring-2 focus:ring-blue-[0-9]+\b/g, 'focus:ring-2 focus:ring-botica-500/20'],
  [/ring-blue-500\b/g, 'ring-botica-500'],
  [/ring-blue-100\b/g, 'ring-botica-500/20'],
  [/border-blue-500\b/g, 'border-borde-marca'],
  [/focus:border-violet-500\b/g, 'focus:border-borde-marca'],
  [/focus:ring-violet-100\b/g, 'focus:ring-botica-500/20'],
  [/border-violet-500\b/g, 'border-borde-marca'],
  [/ring-violet-100\b/g, 'ring-botica-500/20'],

  /* --- Degradados de acción principal ----------------------------------- */
  [/bg-linear-to-r from-blue-600 to-indigo-600\b/g, 'bg-botica-700'],
  [/bg-linear-to-r from-blue-600 to-purple-600\b/g, 'bg-botica-700'],
  [/bg-linear-to-r from-indigo-600 to-purple-600\b/g, 'bg-botica-700'],
  [/bg-linear-to-r from-violet-600 to-fuchsia-600\b/g, 'bg-botica-700'],
  [/bg-linear-to-r from-emerald-600 to-green-600\b/g, 'bg-botica-700'],
  [/bg-linear-to-r from-blue-500 to-blue-600\b/g, 'bg-botica-700'],
  [/bg-linear-to-r from-purple-500 to-purple-600\b/g, 'bg-botica-700'],
  [/bg-linear-to-r from-amber-500 to-orange-500\b/g, 'bg-alerta-500'],

  [/hover:from-blue-700 hover:to-indigo-700\b/g, 'hover:bg-botica-800'],
  [/hover:from-blue-700 hover:to-purple-700\b/g, 'hover:bg-botica-800'],
  [/hover:from-violet-700 hover:to-fuchsia-700\b/g, 'hover:bg-botica-800'],
  [/hover:from-emerald-700 hover:to-green-700\b/g, 'hover:bg-botica-800'],
  [/hover:from-indigo-700 hover:to-purple-700\b/g, 'hover:bg-botica-800'],

  /* --- Fondos y cabeceras ------------------------------------------------ */
  [/bg-linear-to-br from-blue-50 via-white to-indigo-50\b/g, 'bg-superficie-fondo'],
  [/bg-linear-to-r from-indigo-50 to-blue-50\b/g, 'bg-superficie-hundida'],
  [/border-indigo-100\b/g, 'border-borde-sutil'],

  /* Texto con degradado recortado: sin el degradado detrás queda invisible,
     así que se retira la técnica entera y se deja color sólido. */
  [/bg-linear-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent\b/g, 'text-texto-primario'],

  /* --- Sombras de color -------------------------------------------------- */
  [/shadow-blue-600\/20\b/g, 'shadow-botica-700/20'],
  [/shadow-violet-600\/20\b/g, 'shadow-botica-700/20'],
  [/shadow-emerald-600\/20\b/g, 'shadow-botica-700/20'],
  [/shadow-indigo-600\/20\b/g, 'shadow-botica-700/20'],

  /* --- Acentos sueltos ---------------------------------------------------- */
  [/bg-blue-600\b/g, 'bg-botica-700'],
  [/bg-blue-700\b/g, 'bg-botica-800'],
  [/hover:bg-blue-700\b/g, 'hover:bg-botica-800'],
  [/text-blue-600\b/g, 'text-texto-marca'],
  [/text-blue-700\b/g, 'text-texto-marca'],
  [/text-blue-800\b/g, 'text-botica-800'],
  [/text-blue-900\b/g, 'text-botica-900'],
  [/bg-blue-50\b/g, 'bg-botica-50'],
  [/bg-blue-100\b/g, 'bg-botica-50'],
  [/bg-indigo-100\b/g, 'bg-botica-50'],
  [/text-indigo-600\b/g, 'text-texto-marca'],
  [/text-indigo-700\b/g, 'text-texto-marca'],

  /* Sobre fondo de marca, el texto tenue ya no puede ser azul claro. */
  [/text-blue-100\b/g, 'text-botica-100'],
  [/text-emerald-100\b/g, 'text-botica-100'],
  [/text-amber-100\b/g, 'text-alerta-50'],

  /* --- Estados que faltaron en la primera pasada -------------------------- */
  [/text-red-500\b/g, 'text-peligro-600'],
  [/text-red-900\b/g, 'text-peligro-700'],
  [/text-rose-800\b/g, 'text-peligro-700'],
  [/text-yellow-800\b/g, 'text-alerta-700'],
  [/bg-orange-100\b/g, 'bg-alerta-50'],
  [/border-gray-100\b/g, 'border-borde-sutil'],
  [/border-slate-100\b/g, 'border-borde-sutil'],
  [/bg-slate-900\b/g, 'bg-neutro-900'],
  [/bg-slate-950\b/g, 'bg-neutro-950'],
]

const soloInforme = argv.includes('--dry')
const archivos = globSync('src/admin/**/*.vue')

let total = 0

for (const ruta of archivos) {
  const original = readFileSync(ruta, 'utf8')
  let texto = original
  let cambios = 0

  for (const [patron, reemplazo] of FRASES) {
    const encontrados = texto.match(patron)

    if (encontrados) {
      texto = texto.replace(patron, reemplazo)
      cambios += encontrados.length
    }
  }

  if (cambios > 0) {
    total += cambios

    if (!soloInforme) writeFileSync(ruta, texto, 'utf8')

    console.log(`${soloInforme ? '[simulado] ' : ''}${ruta.padEnd(52)} ${String(cambios).padStart(4)} cambios`)
  }
}

console.log(`\n${soloInforme ? 'SIMULACIÓN · ' : ''}Total: ${total}`)

/* Qué queda vivo después de las dos pasadas. */
const vivas = new Map()

for (const ruta of archivos) {
  const restantes = readFileSync(ruta, 'utf8').match(
    /\b(bg|text|border|ring|divide|from|to|via|placeholder|shadow|outline|accent|caret)-(gray|slate|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)(-[0-9]+)?(\/[0-9]+)?\b/g,
  ) ?? []

  for (const clase of restantes) {
    vivas.set(clase, (vivas.get(clase) ?? 0) + 1)
  }
}

if (vivas.size > 0) {
  const suma = [...vivas.values()].reduce((a, b) => a + b, 0)
  console.log(`\nQuedan ${suma} usos en ${vivas.size} clases:`)

  for (const [clase, n] of [...vivas.entries()].sort((a, b) => b[1] - a[1]).slice(0, 25)) {
    console.log(`   ${String(n).padStart(4)}  ${clase}`)
  }
} else {
  console.log('\nNo queda ninguna clase de color fija en src/admin.')
}
