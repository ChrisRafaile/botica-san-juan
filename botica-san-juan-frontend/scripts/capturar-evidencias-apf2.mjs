/**
 * Captura las evidencias de frontend del APF2.
 *
 *   node scripts/capturar-evidencias-apf2.mjs
 *
 * POR QUÉ UN SCRIPT Y NO CAPTURAS A MANO
 *
 * La consigna dice que una evidencia debe PROBAR algo, no decorar. Una captura
 * hecha a mano prueba poco: no se sabe con qué ancho se tomó, ni si la página
 * había terminado de cargar, ni se puede repetir cuando el código cambie.
 *
 * Aquí el navegador se abre con un tamaño fijo, se espera a que la rejilla
 * tenga contenido, se MIDE el desplazamiento acumulado (CLS) con
 * PerformanceObserver y se guarda la cifra junto a la imagen. Si mañana alguien
 * rompe el esqueleto de carga, la cifra lo delata.
 */
import { spawn } from 'node:child_process'
import { existsSync, writeFileSync, mkdirSync } from 'node:fs'
import { argv, exit } from 'node:process'

const leer = (n, d) => (argv.find((a) => a.startsWith(`--${n}=`)) ?? `--${n}=${d}`).split('=').slice(1).join('=')

const BASE = leer('base', 'http://localhost:5173')
const SALIDA = leer('salida', '../docs/evidencias')
const PUERTO = Number(leer('puerto', '9801'))

/* Pantallas a medir. `prefijo` nombra los archivos de salida.
   --rutas=/cart,/contact limita la ejecución a una parte. */
const RUTAS_PEDIDAS = leer('rutas', '')
const PANTALLAS = [
  { ruta: '/products', prefijo: 'E4-catalogo' },
  { ruta: '/cart', prefijo: 'E6-carrito' },
  { ruta: '/contact', prefijo: 'E6-contacto' },
].filter((p) => !RUTAS_PEDIDAS || RUTAS_PEDIDAS.split(',').includes(p.ruta))

/* Carrito de ejemplo para la captura. Son ids REALES del catálogo local: el
   servidor rechaza los que no existen, así que un carrito inventado daría una
   pantalla vacía y la medición no mediría nada. */
const SEMILLA_CARRITO = [
  { producto_id: 1, cantidad: 2 },
  /* CLOBETASOL: marcado de venta bajo receta por RecetaMedicaDemoSeeder. Va
     aquí para que la captura demuestre la advertencia funcionando y no solo su
     existencia en el código. */
  { producto_id: 306, cantidad: 1 },
  { producto_id: 2, cantidad: 1 },
]

const CANDIDATOS = [
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
]
const navegador = CANDIDATOS.find(existsSync)
if (!navegador) { console.error('No se encontro Chrome ni Edge.'); exit(1) }

mkdirSync(SALIDA, { recursive: true })
const dormir = (ms) => new Promise((r) => setTimeout(r, ms))

const proc = spawn(navegador, [
  '--headless=new', `--remote-debugging-port=${PUERTO}`,
  '--no-first-run', '--hide-scrollbars', '--window-size=1280,900', 'about:blank',
], { stdio: 'ignore' })

/* Mide el CLS y describe lo que hay en la pagina. Se ejecuta DENTRO del
   navegador, por eso viaja como cadena. */
const MEDIR = String.raw`(async () => {
  const cls = await new Promise((res) => {
    let total = 0; const saltos = [];
    const po = new PerformanceObserver((l) => {
      for (const e of l.getEntries()) {
        if (e.hadRecentInput) continue;
        total += e.value;
        if (e.value > 0.001) saltos.push(Number(e.value.toFixed(4)));
      }
    });
    po.observe({ type: 'layout-shift', buffered: true });
    setTimeout(() => { po.disconnect(); res({ total: Number(total.toFixed(5)), saltos }); }, 3000);
  });

  const tarjetas = document.querySelectorAll('article');
  const imgs = [...document.querySelectorAll('article img')];
  const formas = imgs.filter((i) => i.getAttribute('src')?.includes('/formas/'));
  const etiquetas = [...document.querySelectorAll('article span')]
    .filter((s) => s.textContent.trim() === 'Imagen referencial');
  const recuento = document.querySelector('[role=status]')?.textContent.replace(/\s+/g, ' ').trim();

  /* Especificos del carrito: cuantas lineas hay, si el tope de cantidad esta
     activo y si el desglose fiscal se pinto entero. */
  const campos = [...document.querySelectorAll('input[type=number][max]')];
  const carrito = campos.length ? {
    lineas: campos.length,
    topesDeclarados: campos.filter((c) => Number(c.max) > 0).length,
    enElTope: campos.filter((c) => Number(c.value) >= Number(c.max)).length,
    etiquetasDesglose: [...document.querySelectorAll('dt')]
      .map((d) => d.textContent.trim())
      .filter((t) => /subtotal|igv|total|exonerado|inafecto/i.test(t)),
    avisosReceta: document.body.textContent.match(/Requiere receta médica/g)?.length ?? 0,
    avisoRecetaEnResumen: /venta bajo receta médica/i.test(document.body.textContent),
    /* Solo se comprueba QUE exista el enlace y su prefijo, nunca el numero:
       es un dato personal y este JSON se versiona. */
    salidaWhatsApp: Boolean(document.querySelector('a[href^="https://wa.me/"]')),
  } : null;

  const diferidas = [...tarjetas].filter((t) => getComputedStyle(t).contentVisibility === 'auto').length;

  return JSON.stringify({
    cls: cls.total,
    saltos: cls.saltos,
    veredicto: cls.total <= 0.1 ? 'BUENO' : cls.total <= 0.25 ? 'MEJORABLE' : 'POBRE',
    recuento,
    tarjetas: tarjetas.length,
    ilustraciones: formas.length,
    conDimensionesExplicitas: formas.filter((i) => i.getAttribute('width') && i.getAttribute('height')).length,
    etiquetasReferencial: etiquetas.length,
    tarjetasConRenderDiferido: diferidas,
    carrito,
    ejemplo: formas[0] ? { src: formas[0].getAttribute('src'), alt: formas[0].getAttribute('alt') } : null,
  });
})()`

let ws
try {
  let objetivo
  for (let i = 0; i < 40; i++) {
    try {
      const lista = await fetch(`http://127.0.0.1:${PUERTO}/json/list`).then((r) => r.json())
      objetivo = lista.find((t) => t.type === 'page' && !t.url.startsWith('chrome-extension://'))
      if (objetivo) break
    } catch { /* arrancando */ }
    await dormir(300)
  }
  if (!objetivo) throw new Error('el navegador no expuso ninguna pestana')

  ws = new globalThis.WebSocket(objetivo.webSocketDebuggerUrl)
  await new Promise((r) => { ws.onopen = r })

  let id = 0
  const pend = new Map()
  ws.onmessage = (e) => {
    const m = JSON.parse(e.data)
    if (m.id && pend.has(m.id)) { pend.get(m.id)(m.result); pend.delete(m.id) }
  }
  const enviar = (method, params = {}, plazo = 30000) => new Promise((res) => {
    const n = ++id
    const reloj = setTimeout(() => { if (pend.delete(n)) res({ __plazo: true }) }, plazo)
    pend.set(n, (v) => { clearTimeout(reloj); res(v) })
    ws.send(JSON.stringify({ id: n, method, params }))
  })

  await enviar('Page.enable')
  await enviar('Runtime.enable')

  for (const pantalla of PANTALLAS) {
    for (const tema of ['claro', 'oscuro']) {
      /* El tema y el carrito se fijan antes de cargar: el script anti-parpadeo
         lee el tema en el primer pintado y el carrito se lee al crear el store,
         asi que recargar es parte de la medicion. */
      await enviar('Page.navigate', { url: `${BASE}${pantalla.ruta}` })
      await dormir(1500)
      await enviar('Runtime.evaluate', { expression: `localStorage.setItem('botica:tema','${tema}')` })

      /* El carrito tiene que tener algo dentro o la medicion no mide nada: una
         pantalla de "carrito vacio" no tiene lineas que puedan saltar. */
      if (pantalla.ruta === '/cart') {
        await enviar('Runtime.evaluate', {
          expression: `localStorage.setItem('botica:carrito', JSON.stringify(${JSON.stringify(SEMILLA_CARRITO)}))`,
        })
      }

      await enviar('Page.navigate', { url: `${BASE}${pantalla.ruta}` })
      await dormir(4000)

      const r = await enviar('Runtime.evaluate', { expression: MEDIR, awaitPromise: true, returnByValue: true })
      const datos = JSON.parse(r?.result?.value ?? '{}')
      datos.ruta = pantalla.ruta
      datos.tema = tema

      const png = await enviar('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false })
      if (png?.data) {
        writeFileSync(`${SALIDA}/${pantalla.prefijo}-${tema}.png`, Buffer.from(png.data, 'base64'))
      }

      console.log(
        `[${pantalla.ruta} · ${tema}] CLS ${datos.cls} (${datos.veredicto})` +
        (datos.tarjetas ? ` · ${datos.tarjetas} tarjetas` : '') +
        (datos.ilustraciones ? ` · ${datos.ilustraciones} ilustraciones (${datos.conDimensionesExplicitas} con width/height)` : '') +
        (datos.etiquetasReferencial ? ` · ${datos.etiquetasReferencial} etiquetas "Imagen referencial"` : '') +
        (datos.recuento ? ` · "${datos.recuento}"` : ''),
      )

      writeFileSync(`${SALIDA}/${pantalla.prefijo}-metricas-${tema}.json`, JSON.stringify(datos, null, 2))
    }
  }
} finally {
  try { ws?.close() } catch { /* ignorado */ }
  proc.kill()
}
