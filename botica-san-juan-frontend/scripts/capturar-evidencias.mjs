/**
 * Capturas de pantalla del panel para el informe.
 *
 *   node scripts/capturar-evidencias.mjs --token=<token> --salida=<carpeta>
 *   node scripts/capturar-evidencias.mjs --token=<token> --tema=oscuro
 *
 * POR QUÉ ASÍ Y NO A MANO
 * Las capturas de un informe envejecen: se toman una vez, la interfaz cambia y
 * en la siguiente entrega muestran algo que ya no existe. Con un script se
 * regeneran todas en un minuto y siempre reflejan el estado real del sistema.
 *
 * POR QUÉ SIN DEPENDENCIAS
 * Instalar Playwright para esto son ~150 MB y una dependencia más que mantener.
 * Chrome ya está en la máquina y habla el protocolo DevTools; Node 22 trae
 * cliente WebSocket incorporado. Con eso alcanza.
 *
 * SOBRE EL TOKEN
 * El panel exige sesión, así que hay que inyectar un token antes de navegar.
 * Se pasa por parámetro y no se guarda en el repositorio. Genéralo con
 * `php artisan dev:token` y revócalo al terminar con `dev:token:revocar`.
 */

import { spawn } from 'node:child_process'
import { writeFileSync, mkdirSync, existsSync } from 'node:fs'
import { join } from 'node:path'
import { argv, exit } from 'node:process'

const opcion = (nombre, pordefecto = null) => {
  const encontrado = argv.find((a) => a.startsWith(`--${nombre}=`))
  return encontrado ? encontrado.slice(nombre.length + 3) : pordefecto
}

const TOKEN = opcion('token')
const SALIDA = opcion('salida', './capturas')
const TEMA = opcion('tema', 'claro')
const BASE = opcion('base', 'http://localhost:5173')
const PUERTO = 9333

if (!TOKEN) {
  console.error('Falta --token=<token>. Genéralo con: php artisan dev:token')
  exit(1)
}

/* Qué se captura y para qué sirve en el informe. */
const PANTALLAS = [
  { id: '10_pos_venta', ruta: '/admin/pos', titulo: 'Punto de venta', esperar: 2500,
    guion: async (cdp) => {
      await escribirBusqueda(cdp, 'PARACETAMOL')
    } },
  { id: '11_pos_cobro', ruta: '/admin/pos', titulo: 'Panel de cobro con vuelto', esperar: 2500,
    guion: async (cdp) => {
      await escribirBusqueda(cdp, 'PARACETAMOL')
      await evaluar(cdp, `
        window.dispatchEvent(new KeyboardEvent('keydown',{key:'F4',bubbles:true}));
      `)

      /* Se espera a que el diálogo esté REALMENTE en pantalla antes de pulsar
         el billete. Un tiempo fijo no basta: el panel verifica el stock contra
         el servidor antes de abrirse, y esa consulta tarda lo que tarde. */
      const abierto = await esperarA(cdp, `!!document.querySelector('[role="dialog"]')`)

      if (!abierto) throw new Error('el panel de cobro no llegó a abrirse')

      await evaluar(cdp, `
        (() => {
          const d = document.querySelector('[role="dialog"]');
          const b = [...d.querySelectorAll('button')].find(x => x.textContent.trim() === '20');
          if (b) b.click();
          return !!b;
        })()
      `)

      /* Y a que el vuelto aparezca: es lo que da valor a esta captura. */
      await esperarA(cdp, `/Vuelto/.test(document.querySelector('[role="dialog"]')?.innerText ?? '')`)
    } },
  { id: '12_inventario', ruta: '/admin/inventory', titulo: 'Inventario con estado de stock', esperar: 3000 },
  { id: '13_conteo', ruta: '/admin/inventory/conteo', titulo: 'Conteo físico por ciclos', esperar: 2500 },
  { id: '14_productos', ruta: '/admin/products', titulo: 'Catálogo de productos', esperar: 3000 },
  { id: '15_tablero', ruta: '/admin/home', titulo: 'Tablero', esperar: 3000 },
]

/* -------------------------------------------------------------------------- */

const dormir = (ms) => new Promise((r) => setTimeout(r, ms))

function rutaChrome() {
  const candidatos = [
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
  ]

  const encontrado = candidatos.find((c) => existsSync(c))

  if (!encontrado) {
    console.error('No se encontró Chrome ni Edge.')
    exit(1)
  }

  return encontrado
}

let siguienteId = 1
const pendientes = new Map()

function enviar(ws, method, params = {}) {
  const id = siguienteId++
  ws.send(JSON.stringify({ id, method, params }))

  return new Promise((resolve, reject) => {
    pendientes.set(id, { resolve, reject })
    setTimeout(() => {
      if (pendientes.has(id)) {
        pendientes.delete(id)
        reject(new Error(`Sin respuesta de ${method}`))
      }
    }, 30_000)
  })
}

const evaluar = (ws, expresion) =>
  enviar(ws, 'Runtime.evaluate', { expression: expresion, awaitPromise: true, returnByValue: true })

/**
 * Espera a que una condición se cumpla en la página, consultándola cada 200 ms.
 *
 * Los tiempos fijos son la causa habitual de que una captura salga a medias:
 * funcionan en la máquina donde se escribieron y fallan en otra más lenta. Aquí
 * se espera al hecho, no al reloj.
 */
async function esperarA(ws, condicion, msMaximo = 8000) {
  const limite = Date.now() + msMaximo

  while (Date.now() < limite) {
    const { result } = await evaluar(ws, `Boolean(${condicion})`)
    if (result?.value === true) {
      await dormir(250)  // margen para que termine de pintar
      return true
    }
    await dormir(200)
  }

  return false
}

async function escribirBusqueda(ws, termino) {
  await evaluar(ws, `
    (() => {
      const set = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype,'value').set;
      const campo = [...document.querySelectorAll('input[type="search"]')].pop();
      if (!campo) return false;
      campo.focus(); set.call(campo, ${JSON.stringify(termino)});
      campo.dispatchEvent(new Event('input',{bubbles:true}));
      return true;
    })()
  `)
  await dormir(1500)
  await evaluar(ws, `
    (() => {
      const campo = [...document.querySelectorAll('input[type="search"]')].pop();
      if (campo) campo.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));
    })()
  `)
  await dormir(900)
}

/* -------------------------------------------------------------------------- */

const chrome = spawn(rutaChrome(), [
  '--headless=new',
  `--remote-debugging-port=${PUERTO}`,
  '--window-size=1440,900',
  '--hide-scrollbars',
  '--disable-gpu',
  '--no-first-run',
  '--user-data-dir=' + join(process.env.TEMP ?? '/tmp', 'botica-capturas'),
  'about:blank',
], { stdio: 'ignore' })

process.on('exit', () => chrome.kill())

console.log('Esperando a que arranque el navegador…')
await dormir(3000)

let objetivo
for (let intento = 0; intento < 10; intento++) {
  try {
    const lista = await fetch(`http://127.0.0.1:${PUERTO}/json/list`).then((r) => r.json())
    objetivo = lista.find((t) => t.type === 'page')
    if (objetivo) break
  } catch { /* todavía no responde */ }
  await dormir(1000)
}

if (!objetivo) {
  console.error('El navegador no expuso ninguna pestaña.')
  chrome.kill()
  exit(1)
}

const ws = new WebSocket(objetivo.webSocketDebuggerUrl)

ws.addEventListener('message', (evento) => {
  const mensaje = JSON.parse(evento.data)
  const pendiente = pendientes.get(mensaje.id)

  if (!pendiente) return

  pendientes.delete(mensaje.id)
  mensaje.error ? pendiente.reject(new Error(mensaje.error.message)) : pendiente.resolve(mensaje.result)
})

await new Promise((r) => ws.addEventListener('open', r, { once: true }))

await enviar(ws, 'Page.enable')
await enviar(ws, 'Runtime.enable')

/* Primero se abre el origen para poder escribir en su localStorage: el
   almacenamiento va por origen, y en about:blank no existe. */
await enviar(ws, 'Page.navigate', { url: BASE })
await dormir(2500)

await evaluar(ws, `
  localStorage.setItem('auth_token', ${JSON.stringify(TOKEN)});
  localStorage.setItem('botica:tema', ${JSON.stringify(TEMA)});
  'listo'
`)

if (!existsSync(SALIDA)) mkdirSync(SALIDA, { recursive: true })

for (const pantalla of PANTALLAS) {
  process.stdout.write(`  ${pantalla.id} · ${pantalla.titulo} … `)

  await enviar(ws, 'Page.navigate', { url: BASE + pantalla.ruta })
  await dormir(pantalla.esperar)

  /* El panel oculto no ejecuta requestAnimationFrame, del que dependen las
     transiciones de Vue. Se sustituye por un temporizador para que los
     diálogos terminen de abrirse antes de la captura. */
  await evaluar(ws, `window.requestAnimationFrame = (cb) => setTimeout(() => cb(performance.now()), 16); 'ok'`)

  /* Se oculta el botón flotante de Vue DevTools: es una herramienta de
     desarrollo y no tiene por qué salir en un informe. */
  await evaluar(ws, `
    (() => {
      const estilo = document.createElement('style');
      estilo.textContent = '#__vue-devtools-container__, .vue-devtools__anchor-btn, [data-v-inspector-container] { display: none !important; }';
      document.head.appendChild(estilo);
      return true;
    })()
  `)

  if (pantalla.guion) await pantalla.guion(ws)

  const { data } = await enviar(ws, 'Page.captureScreenshot', { format: 'png' })
  const destino = join(SALIDA, `${pantalla.id}_${TEMA}.png`)
  writeFileSync(destino, Buffer.from(data, 'base64'))

  console.log(`guardada (${Math.round(Buffer.from(data, 'base64').length / 1024)} KB)`)
}

ws.close()
chrome.kill()

console.log(`\nCapturas en: ${SALIDA}`)
