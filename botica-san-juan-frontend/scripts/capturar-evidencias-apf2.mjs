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

  for (const tema of ['claro', 'oscuro']) {
    /* El tema se fija antes de cargar: el script anti-parpadeo lo lee en el
       primer pintado, asi que recargar es parte de la medicion. */
    await enviar('Page.navigate', { url: `${BASE}/products` })
    await dormir(1500)
    await enviar('Runtime.evaluate', { expression: `localStorage.setItem('botica:tema','${tema}')` })
    await enviar('Page.navigate', { url: `${BASE}/products` })
    await dormir(4000)

    const r = await enviar('Runtime.evaluate', { expression: MEDIR, awaitPromise: true, returnByValue: true })
    const datos = JSON.parse(r?.result?.value ?? '{}')

    const png = await enviar('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false })
    if (png?.data) {
      writeFileSync(`${SALIDA}/E4-catalogo-${tema}.png`, Buffer.from(png.data, 'base64'))
    }

    console.log(`[${tema}] CLS ${datos.cls} (${datos.veredicto}) · ${datos.tarjetas} tarjetas · ` +
      `${datos.ilustraciones} ilustraciones (${datos.conDimensionesExplicitas} con width/height) · ` +
      `${datos.etiquetasReferencial} etiquetas "Imagen referencial" · ` +
      `${datos.tarjetasConRenderDiferido} con render diferido`)

    writeFileSync(`${SALIDA}/E4-metricas-${tema}.json`, JSON.stringify(datos, null, 2))
  }
} finally {
  try { ws?.close() } catch { /* ignorado */ }
  proc.kill()
}
