/**
 * Inserta la guarda de `prefers-reduced-motion` en los componentes del portal.
 *
 *   node scripts/guardar-movimiento.mjs --dry
 *   node scripts/guardar-movimiento.mjs
 *
 * EL PROBLEMA QUE RESUELVE
 *
 * Los 22 componentes públicos animaban con `gsap.from(...)`, que aplica el
 * estado inicial —`opacity: 0`, desplazamiento— en el momento y lo resuelve
 * después, cuadro a cuadro. Ninguno comprobaba si la persona había pedido
 * reducir el movimiento en su sistema operativo.
 *
 * Son dos fallos en uno:
 *
 * 1. Accesibilidad. Para quien tiene trastorno vestibular, media portada
 *    entrando en diagonal no es un adorno, es un mareo. El sistema ya expone
 *    la preferencia y el proyecto ya tiene el ayudante para leerla; nadie lo
 *    estaba usando fuera del panel.
 *
 * 2. Robustez. Como el estado visible depende de que la animación TERMINE,
 *    cualquier cosa que impida que avance deja la página en blanco con el
 *    contenido presente en el DOM: una pestaña en segundo plano, donde el
 *    navegador congela `requestAnimationFrame`, o un fallo al cargar GSAP.
 *    El contenido tiene que verse por omisión y la animación sólo realzarlo.
 *
 * QUÉ NO TOCA
 *
 * `CoverageContact.vue` y `CoverageSelector.vue` montan mapas de Leaflet
 * dentro del mismo `onMounted`. Una vuelta temprana ahí dejaría la página de
 * cobertura sin mapa, así que esos dos se ajustan a mano.
 */

import { readFileSync, writeFileSync } from 'node:fs'
import { argv } from 'node:process'

const soloInforme = argv.includes('--dry')

/* Los que animan y nada más. Los dos de Leaflet quedan fuera a propósito. */
const ARCHIVOS = [
  'AboutContact', 'AboutContent', 'AboutDelivery', 'AboutHero', 'AboutTestimonials',
  'Benefits', 'CTA', 'ContactForm', 'ContactHero', 'ContactInfo',
  'CoverageHero', 'CoverageServices', 'Hero', 'Services', 'ServicesAgora',
  'ServicesCategories', 'ServicesGrid', 'ServicesHero', 'Stats', 'Testimonials',
].map((n) => `src/components/${n}.vue`)

const IMPORT = "import { prefiereMenosMovimiento } from '@/utils/motion'"

const GUARDA_LINEAS = [
  '  /* Si la persona pidió reducir el movimiento, no se anima: el contenido ya',
  '     está en su estado final y visible, que es justamente lo que se quiere. */',
  '  if (prefiereMenosMovimiento()) return',
  '',
]

let tocados = 0
const avisos = []

for (const ruta of ARCHIVOS) {
  let texto = readFileSync(ruta, 'utf8')

  /* Los archivos vienen con finales de línea de Windows. Si se inserta con
     `\n` seco el archivo queda mezclado y el diff se vuelve ilegible. */
  const FIN = texto.includes('\r\n') ? '\r\n' : '\n'
  const GUARDA = GUARDA_LINEAS.join(FIN)

  if (texto.includes('prefiereMenosMovimiento')) {
    avisos.push(`${ruta}: ya tenía la guarda, se deja igual`)
    continue
  }

  /* 1. El import, justo detrás del último import COMPLETO del bloque.
     Ojo con esto: la primera versión buscaba `^import .*$`, que también casa
     con la línea `import {` que ABRE un import de varias líneas, de modo que
     la nueva línea acababa dentro de la lista de iconos y rompía el archivo.
     Un import completo termina en una cadena entrecomillada, así que se exige
     eso: o `import … from '…'`, o `import '…'`, o el `} from '…'` de cierre. */
  const imports = [...texto.matchAll(
    /^(?:import\s.*from\s+['"][^'"]+['"];?|import\s+['"][^'"]+['"];?|\}\s*from\s+['"][^'"]+['"];?)\s*$/gm,
  )]
  if (!imports.length) {
    avisos.push(`${ruta}: SIN imports, se omite`)
    continue
  }
  const ultimo = imports[imports.length - 1]
  const finUltimo = ultimo.index + ultimo[0].length
  texto = texto.slice(0, finUltimo) + FIN + IMPORT + texto.slice(finUltimo)

  /* 2. La guarda, como primera instrucción del onMounted. */
  const apertura = /onMounted\((?:async\s*)?\(\)\s*=>\s*\{\r?\n/.exec(texto)
  if (!apertura) {
    avisos.push(`${ruta}: no se reconoció la forma del onMounted, SE OMITE`)
    continue
  }
  const corte = apertura.index + apertura[0].length
  texto = texto.slice(0, corte) + GUARDA + texto.slice(corte)

  if (!soloInforme) writeFileSync(ruta, texto, 'utf8')
  tocados++
  console.log(`${soloInforme ? '[simulado] ' : ''}${ruta}`)
}

console.log(`\n${soloInforme ? 'SIMULACIÓN · ' : ''}Archivos con guarda: ${tocados} de ${ARCHIVOS.length}`)
if (avisos.length) {
  console.log('\nAvisos:')
  for (const a of avisos) console.log(`   ${a}`)
}
