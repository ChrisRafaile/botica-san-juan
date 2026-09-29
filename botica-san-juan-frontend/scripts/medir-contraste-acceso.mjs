/**
 * Mide el contraste real de la pantalla de acceso.
 *
 * POR QUE PINTANDO EN UN LIENZO Y NO LEYENDO EL COLOR DECLARADO
 *
 * En este proyecto se calculo mal el contraste dos veces por leer los
 * componentes de `oklch()` como si fueran canales RGB. La unica forma fiable
 * es pedirle al navegador que resuelva el color: se pinta en un lienzo de
 * 1x1 y se lee el pixel ya convertido a sRGB.
 *
 * La comprobacion de cordura —blanco sobre negro debe dar exactamente 21:1—
 * esta ahi para que un error de formula se vea de inmediato en vez de
 * producir cifras plausibles pero falsas.
 *
 *   node scripts/medir-contraste-acceso.mjs [--base=http://localhost:5173]
 */
import { spawn } from 'node:child_process'
import { existsSync } from 'node:fs'
import { argv, exit } from 'node:process'

const BASE = (argv.find((a) => a.startsWith('--base=')) ?? '--base=http://localhost:5173').split('=').slice(1).join('=')
const PUERTO = 9444

const CANDIDATOS = [
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
]

const navegador = CANDIDATOS.find(existsSync)
if (!navegador) {
  console.error('No se encontro Chrome ni Edge.')
  exit(1)
}

const dormir = (ms) => new Promise((r) => setTimeout(r, ms))

const proc = spawn(navegador, [
  '--headless=new',
  `--remote-debugging-port=${PUERTO}`,
  '--no-first-run',
  '--window-size=1400,900',
  'about:blank',
], { stdio: 'ignore' })

let ws
try {
  /* Se filtra por type === 'page': la lista incluye tambien las paginas de
     fondo de las extensiones instaladas, y engancharse a una de ellas
     devuelve un documento vacio que parece un fallo de la aplicacion. */
  let objetivo
  for (let i = 0; i < 40; i++) {
    try {
      const lista = await fetch(`http://127.0.0.1:${PUERTO}/json/list`).then((r) => r.json())
      objetivo = lista.find((t) => t.type === 'page' && !t.url.startsWith('chrome-extension://'))
      if (objetivo) break
    } catch { /* todavia arrancando */ }
    await dormir(300)
  }
  if (!objetivo) throw new Error('el navegador no expuso ninguna pestana')

  const { WebSocket } = await import('node:worker_threads').then(() => globalThis)
  ws = new globalThis.WebSocket(objetivo.webSocketDebuggerUrl)
  await new Promise((r) => { ws.onopen = r })

  let id = 0
  const pendientes = new Map()
  ws.onmessage = (e) => {
    const m = JSON.parse(e.data)
    if (m.id && pendientes.has(m.id)) {
      pendientes.get(m.id)(m.result)
      pendientes.delete(m.id)
    }
  }
  const enviar = (method, params = {}) => new Promise((res) => {
    const n = ++id
    pendientes.set(n, res)
    ws.send(JSON.stringify({ id: n, method, params }))
  })

  await enviar('Page.enable')
  await enviar('Runtime.enable')
  await enviar('Page.navigate', { url: `${BASE}/login` })
  await dormir(3000)

  const guion = `(() => {
    const lienzo = document.createElement('canvas');
    lienzo.width = 1; lienzo.height = 1;
    const ctx = lienzo.getContext('2d', { willReadFrequently: true });

    // Resuelve CUALQUIER color CSS a sRGB pintandolo de verdad.
    const aRGB = (color) => {
      ctx.clearRect(0, 0, 1, 1);
      ctx.fillStyle = '#000';
      ctx.fillRect(0, 0, 1, 1);
      ctx.fillStyle = color;
      ctx.fillRect(0, 0, 1, 1);
      const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data;
      return [r, g, b];
    };

    const luminancia = ([r, g, b]) => {
      const c = [r, g, b].map((v) => {
        const s = v / 255;
        return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
      });
      return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    };

    const razon = (a, b) => {
      const la = luminancia(aRGB(a)), lb = luminancia(aRGB(b));
      const [hi, lo] = la > lb ? [la, lb] : [lb, la];
      return (hi + 0.05) / (lo + 0.05);
    };

    // Comprobacion de cordura: si esto no da 21, la formula esta mal.
    const cordura = razon('#ffffff', '#000000');

    // Fondo efectivo de un elemento: sube por los ancestros hasta hallar uno opaco.
    const fondoDe = (el) => {
      let n = el;
      while (n && n !== document.documentElement) {
        const bg = getComputedStyle(n).backgroundColor;
        const [r, g, b, a] = bg.replace(/[^\\d.,]/g, '').split(',').map(Number);
        if (a === undefined || a > 0.95) return bg;
        n = n.parentElement;
      }
      return getComputedStyle(document.body).backgroundColor;
    };

    const panel = document.querySelector('.panel-marca');
    const medir = (sel, etiqueta) => {
      const el = (panel || document).querySelector(sel);
      if (!el) return { etiqueta, error: 'no encontrado' };
      const cs = getComputedStyle(el);
      return {
        etiqueta,
        texto: el.textContent.trim().slice(0, 28),
        color: cs.color,
        fondo: fondoDe(el),
        razon: Number(razon(cs.color, fondoDe(el)).toFixed(2)),
        tamano: cs.fontSize,
      };
    };

    return JSON.stringify({
      diagnostico: {
        url: location.href,
        titulo: document.title,
        h2s: document.querySelectorAll('h2').length,
        panel: !!panel,
        cuerpo: document.body.innerText.slice(0, 60),
      },
      cordura: Number(cordura.toFixed(2)),
      medidas: [
        medir('h2', 'titulo del panel'),
        medir('p', 'rotulo de marca'),
        medir('button', 'boton del panel'),
      ],
    });
  })()`

  const r = await enviar('Runtime.evaluate', { expression: guion, returnByValue: true })
  const datos = JSON.parse(r.result.value)

  if (process.env.DEPURAR) console.log(datos.diagnostico)
  console.log(`\nComprobacion de cordura (blanco sobre negro): ${datos.cordura}:1  ${datos.cordura === 21 ? 'OK' : 'FORMULA INCORRECTA'}`)
  console.log('')
  for (const m of datos.medidas) {
    if (m.error) { console.log(`  ${m.etiqueta}: ${m.error}`); continue }
    const umbral = parseFloat(m.tamano) >= 24 ? 3 : 4.5
    const veredicto = m.razon >= umbral ? 'CUMPLE' : 'NO CUMPLE'
    console.log(`  ${m.etiqueta.padEnd(20)} ${String(m.razon).padStart(6)}:1  (minimo ${umbral})  ${veredicto}`)
    console.log(`  ${''.padEnd(20)} "${m.texto}"  ${m.color} sobre ${m.fondo}`)
  }
  console.log('')
} finally {
  try { ws?.close() } catch { /* ignorado */ }
  proc.kill()
}
