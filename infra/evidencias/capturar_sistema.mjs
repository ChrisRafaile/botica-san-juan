/**
 * Genera las capturas de evidencia del sistema en ejecucion.
 *
 * Usa Playwright sobre el Microsoft Edge ya instalado (channel: 'msedge'), en
 * modo headless: no abre ninguna ventana en la pantalla del usuario ni captura
 * su escritorio. Cada imagen contiene unicamente la pagina del sistema.
 *
 * Requisitos: backend en :8083 y frontend en :5173 en ejecucion.
 * Uso: node infra/evidencias/capturar_sistema.mjs
 */
import { chromium } from 'playwright'
import { mkdirSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const AQUI = path.dirname(fileURLToPath(import.meta.url))
const DESTINO = path.join(AQUI, 'capturas')
const BASE = process.env.URL_FRONT ?? 'http://127.0.0.1:5173'

const CREDENCIALES = {
  dni: process.env.DEMO_DNI ?? '99000001',
  password: process.env.DEMO_PASS ?? 'DemoApf1#2026',
}

const PUBLICAS = [
  ['sis_01_portal_publico', '/'],
  ['sis_02_catalogo', '/products'],
  ['sis_03_login', '/login'],
  ['sis_04_servicios', '/services'],
  ['sis_05_cobertura', '/coverage'],
]

const ADMIN = [
  ['sis_06_admin_tablero', '/admin/home'],
  ['sis_07_admin_productos', '/admin/products'],
  ['sis_08_admin_inventario', '/admin/inventory'],
  ['sis_09_admin_facturacion', '/admin/billing'],
  ['sis_10_admin_reportes', '/admin/reports'],
  ['sis_11_admin_proveedores', '/admin/supply/suppliers'],
  ['sis_12_admin_carga_masiva', '/admin/products/bulk-upload'],
]

mkdirSync(DESTINO, { recursive: true })

/** Espera a que la pagina quede estable antes de capturar. */
async function estabilizar(page, ms = 2600) {
  await page.waitForLoadState('networkidle').catch(() => {})
  await page.waitForTimeout(ms)
}

async function capturar(page, nombre, ruta) {
  await page.goto(`${BASE}${ruta}`, { waitUntil: 'domcontentloaded', timeout: 45000 })
  await estabilizar(page)
  await page.screenshot({ path: path.join(DESTINO, `${nombre}.png`) })
  console.log(`OK  ${nombre}.png  <- ${ruta}`)
}

const navegador = await chromium.launch({ channel: 'msedge', headless: true })
const contexto = await navegador.newContext({
  viewport: { width: 1600, height: 1000 },
  deviceScaleFactor: 1.25,
  locale: 'es-PE',
})
const page = await contexto.newPage()

// Diagnostico: registra errores de consola y peticiones fallidas
page.on('console', (m) => {
  if (m.type() === 'error') console.log('   [consola]', m.text().slice(0, 160))
})
page.on('requestfailed', (r) => {
  console.log('   [red fallida]', r.method(), r.url().slice(0, 90), r.failure()?.errorText ?? '')
})
page.on('response', (r) => {
  if (r.url().includes('/api/') && r.status() >= 400) {
    console.log('   [api]', r.status(), r.url().slice(0, 90))
  }
})

try {
  // ---- Pantallas publicas -------------------------------------------------
  for (const [nombre, ruta] of PUBLICAS) {
    await capturar(page, nombre, ruta)
  }

  // ---- Autenticacion por la interfaz --------------------------------------
  // Se inicia sesion llenando el formulario real, no inyectando el token: el
  // estado de sesion vive en el store de Pinia y no se rehidrata desde
  // localStorage al recargar la pagina.
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
  await estabilizar(page, 2000)

  await page.fill('#dni', CREDENCIALES.dni)
  await page.fill('#password', CREDENCIALES.password)
  await page.click('button[type="submit"]')

  await page.waitForURL(/\/admin/, { timeout: 25000 }).catch(() => {})
  await estabilizar(page, 3200)

  if (!page.url().includes('/admin')) {
    console.error(`\nNo se pudo autenticar. URL actual: ${page.url()}`)
  } else {
    console.log(`\nSesion iniciada. URL: ${page.url()}\n`)
    await page.screenshot({ path: path.join(DESTINO, 'sis_06_admin_tablero.png') })
    console.log('OK  sis_06_admin_tablero.png')

    // La navegacion se hace dentro del SPA para no perder el estado de sesion.
    for (const [nombre, ruta] of ADMIN.slice(1)) {
      await page.evaluate((r) => {
        window.history.pushState({}, '', r)
        window.dispatchEvent(new PopStateEvent('popstate'))
      }, ruta)
      await estabilizar(page, 3000)
      await page.screenshot({ path: path.join(DESTINO, `${nombre}.png`) })
      console.log(`OK  ${nombre}.png  <- ${ruta}`)
    }
  }
} finally {
  await navegador.close()
}

console.log(`\nCapturas generadas en: ${DESTINO}`)
