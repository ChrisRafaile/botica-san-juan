/**
 * Captura la portada del repositorio en GitHub como evidencia del informe.
 *
 * Se ejecuta sin sesion iniciada, de modo que la captura refleje exactamente
 * lo que ve cualquier persona que abra el enlace publico, y no el panel de
 * administracion del propietario.
 *
 * Uso:  node infra/evidencias/capturar_github.mjs
 */
import { chromium } from 'playwright';
import { fileURLToPath } from 'url';
import path from 'path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SALIDA = path.join(__dirname, 'capturas', 'gh_01_repositorio.png');
const URL_REPO = 'https://github.com/ChrisRafaile/botica-san-juan';

const navegador = await chromium.launch({ channel: 'msedge', headless: true });
const contexto = await navegador.newContext({
  viewport: { width: 1500, height: 1550 },
  deviceScaleFactor: 1,
  colorScheme: 'dark',
});

const pagina = await contexto.newPage();
await pagina.goto(URL_REPO, { waitUntil: 'networkidle', timeout: 60000 });

// Oculta los avisos flotantes que no aportan al informe.
await pagina.addStyleTag({
  content: `
    .js-notice, .flash-banner, dialog, .Popover,
    [data-testid="cookie-consent"], .js-cookie-consent-banner { display: none !important; }
  `,
});

await pagina.waitForTimeout(2500);
await pagina.screenshot({ path: SALIDA, fullPage: false });

console.log('Captura guardada en:', SALIDA);
await navegador.close();
