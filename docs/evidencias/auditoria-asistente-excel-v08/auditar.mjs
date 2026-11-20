import { createRequire } from 'node:module'
import fs from 'node:fs'
import path from 'node:path'

const require = createRequire(import.meta.url)
const { chromium } = require(process.env.PLAYWRIGHT_CORE) // ruta a ...
pm-cache_npx<hash>
ode_modulesplaywright-core

const RAIZ = 'C:/tareas universitarios/TAREAS U/Tareas Univ/Ciclo 5/Taller de programacion Web/botica_san_juan'
const OUT = `${RAIZ}/docs/evidencias/auditoria-asistente-excel-v08`
fs.mkdirSync(OUT, { recursive: true })
const env = Object.fromEntries(
  fs.readFileSync(`${RAIZ}/botica-san-juan-backend/.env`, 'utf8').split(/\r?\n/)
    .filter((l) => l.includes('=') && !l.startsWith('#')).map((l) => [l.slice(0, l.indexOf('=')), l.slice(l.indexOf('=') + 1).trim().replace(/^"|"$/g, '')]),
)
const CSV = `${RAIZ}/docs/evidencias/importador-excel/ejemplo-reposicion.csv`
const FRONT = 'http://127.0.0.1:5173'
const API = 'http://127.0.0.1:8083'
const informe = []
const log = (k, v) => { informe.push({ k, v }); console.log(k, '=>', typeof v === 'string' ? v : JSON.stringify(v)) }

const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true })

// 1) Documentación
{
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } })
  const p = await ctx.newPage()
  for (const [nombre, ruta] of [['scalar', '/docs/v1'], ['stoplight', '/docs/api']]) {
    await p.goto(API + ruta, { waitUntil: 'networkidle' }).catch(() => {})
    await p.waitForTimeout(2500)
    const txt = await p.evaluate(() => document.body.innerText)
    log(`docs ${nombre} versión 0.8.0`, /0\.8\.0/.test(txt))
    log(`docs ${nombre} contiene 0.5.0`, /0\.5\.0/.test(txt))
    log(`docs ${nombre} VR002`, /VR002/.test(txt))
    log(`docs ${nombre} endpoints _N`, (txt.match(/(^|\s)_\d(\s|$)/g) || []).length)
    await p.screenshot({ path: `${OUT}/01-docs-${nombre}.png` })
  }
  await ctx.close()
}

async function login(ctx, dni, pass) {
  const p = await ctx.newPage()
  await p.goto(`${FRONT}/login`, { waitUntil: 'networkidle' })
  await p.fill('#acceso-dni', dni)
  await p.fill('#acceso-clave', pass)
  await Promise.all([p.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 15000 }).catch(() => {}), p.click('button[type=submit]')])
  return p
}

for (const ancho of [1280, 360]) {
  const alto = ancho === 360 ? 740 : 900
  const ctx = await browser.newContext({ viewport: { width: ancho, height: alto } })
  const p = await login(ctx, '10000001', env.DEMO_ADMIN_PASSWORD)
  log(`${ancho} url tras login`, p.url())
  await p.addInitScript(() => {
    window.__cls = 0
    new PerformanceObserver((l) => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value }).observe({ type: 'layout-shift', buffered: true })
  })
  await p.goto(`${FRONT}/admin/products`, { waitUntil: 'networkidle' })
  await p.waitForTimeout(1500)
  await p.screenshot({ path: `${OUT}/02-admin-products-${ancho}.png` })
  const fotos = await p.evaluate(() => [...document.images].map((i) => ({ ok: i.complete && i.naturalWidth > 0 })).reduce((a, i) => ({ t: a.t + 1, ok: a.ok + (i.ok ? 1 : 0) }), { t: 0, ok: 0 }))
  log(`${ancho} /admin/products imágenes`, fotos)

  const abrir = p.locator('button[aria-label="Abrir asistente de botica"]')
  log(`${ancho} botón asistente (admin)`, await abrir.count())
  await abrir.click()
  await p.waitForTimeout(600)
  const adj = p.locator('button[aria-label="Adjuntar archivo Excel o CSV"]')
  log(`${ancho} botón adjuntar (admin)`, await adj.count())
  await p.setInputFiles('input[aria-label="Seleccionar archivo Excel o CSV"]', CSV)
  await p.waitForSelector('text=Confirmar importación', { timeout: 15000 }).catch(() => log(`${ancho} previsualización`, 'NO apareció'))
  await p.screenshot({ path: `${OUT}/03-previsualizacion-${ancho}.png` })
  const desb = await p.evaluate(() => ({ scrollW: document.documentElement.scrollWidth, innerW: window.innerWidth }))
  log(`${ancho} desborde horizontal`, desb.scrollW > desb.innerW ? `SÍ ${JSON.stringify(desb)}` : 'no')
  if (ancho === 1280) {
    await p.click('text=Confirmar importación')
    await p.waitForTimeout(2500)
    await p.screenshot({ path: `${OUT}/04-importacion-confirmada-${ancho}.png` })
    log('texto tras confirmar', (await p.locator('[role=dialog], aside, section').last().innerText().catch(() => '')).slice(0, 300))
  } else {
    await p.click('text=Cancelar').catch(() => {})
    await p.waitForTimeout(500)
    await p.screenshot({ path: `${OUT}/04-cancelada-${ancho}.png` })
  }
  await p.goto(`${FRONT}/admin/pos`, { waitUntil: 'networkidle' }).catch(() => {})
  await p.waitForTimeout(1500)
  await p.screenshot({ path: `${OUT}/05-admin-pos-${ancho}.png` })
  const fotosPos = await p.evaluate(() => [...document.images].reduce((a, i) => ({ t: a.t + 1, ok: a.ok + (i.complete && i.naturalWidth > 0 ? 1 : 0) }), { t: 0, ok: 0 }))
  log(`${ancho} /admin/pos imágenes`, fotosPos)
  log(`${ancho} CLS acumulado`, await p.evaluate(() => window.__cls ?? null))
  await ctx.close()
}

// 3) Cliente no ve el adjuntar
{
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } })
  const p = await login(ctx, '10000002', env.DEMO_CLIENTE_PASSWORD)
  await p.waitForTimeout(1000)
  const abrir = p.locator('button[aria-label="Abrir asistente de botica"]')
  log('cliente: botón asistente', await abrir.count())
  if (await abrir.count()) await abrir.first().click().catch(() => {})
  await p.waitForTimeout(500)
  log('cliente: botón adjuntar', await p.locator('button[aria-label="Adjuntar archivo Excel o CSV"]').count())
  await p.screenshot({ path: `${OUT}/06-cliente-sin-adjuntar.png` })
  await ctx.close()
}
await browser.close()
fs.writeFileSync(`${OUT}/resultados.json`, JSON.stringify(informe, null, 2))
