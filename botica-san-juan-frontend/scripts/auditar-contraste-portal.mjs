/**
 * Audita el contraste de TODO el portal público en los dos temas.
 *
 *   node scripts/auditar-contraste-portal.mjs
 *   node scripts/auditar-contraste-portal.mjs --base=http://localhost:5173
 *   node scripts/auditar-contraste-portal.mjs --todo    (lista también lo que cumple)
 *
 * POR QUÉ MIDIENDO Y NO MIRANDO
 *
 * "Se ve bien" no es una comprobación. En este proyecto el contraste se
 * calculó mal DOS veces por leer los componentes de `oklch()` como si fueran
 * canales RGB, y las cifras salían plausibles pero falsas. Aquí el color lo
 * resuelve el navegador: se pinta en un lienzo de 1x1 y se lee el píxel ya
 * convertido a sRGB.
 *
 * La comprobación de cordura —blanco sobre negro debe dar exactamente 21:1—
 * está para que un error de fórmula se vea de inmediato.
 *
 * QUÉ MIDE, Y POR QUÉ ESO
 *
 * 1. Texto: todo elemento con texto propio visible, contra su fondo EFECTIVO
 *    (subiendo por los ancestros hasta encontrar uno opaco, porque un fondo
 *    translúcido no dice de qué color se ve el texto encima).
 *    Umbral WCAG AA: 4.5:1, o 3:1 si el texto es grande (>=24px, o >=18.66px
 *    en negrita).
 *
 * 2. Bordes de controles: inputs, botones y selects contra su fondo. Un campo
 *    cuyo borde no se distingue del fondo es un campo que no se ve que existe.
 *    Umbral WCAG AA para componentes: 3:1.
 *
 * 3. Anillo de foco: se enfoca cada control y se mide el color del contorno.
 *    Es lo que usa quien navega con teclado; si desaparece en un tema, esa
 *    persona se queda sin saber dónde está.
 *
 * 4. Elementos DESAPARECIDOS: razón por debajo de 1.5 significa texto
 *    prácticamente del color del fondo. Se informa aparte porque no es "poco
 *    contraste", es contenido invisible.
 *
 * Los resultados se agrupan por par de colores: un mismo token mal usado en
 * cuarenta sitios es UN problema, no cuarenta.
 */
import { spawn } from 'node:child_process'
import { existsSync, writeFileSync } from 'node:fs'
import { argv, exit } from 'node:process'

const leerArg = (nombre, pordefecto) => {
  const a = argv.find((x) => x.startsWith(`--${nombre}=`))
  return a ? a.split('=').slice(1).join('=') : pordefecto
}

const BASE = leerArg('base', 'http://localhost:5173')
const PUERTO = Number(leerArg('puerto', '9445'))

/* Los resultados se van escribiendo a disco segun avanzan, y no solo al
   final por pantalla: la salida por tuberia llega a destiempo y, si una
   pagina se atasca, se pierde TODO lo medido hasta ese momento. */
const ARCHIVO = leerArg('salida', 'auditoria-contraste.json')

const RUTAS = leerArg('rutas', '/,/products,/services,/about,/contact,/coverage,/login,/register').split(',')
const TEMAS = leerArg('temas', 'claro,oscuro').split(',')

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
  '--window-size=1400,1000',
  'about:blank',
], { stdio: 'ignore' })

/* El guion que corre DENTRO de la pagina. Va como cadena porque viaja por
   CDP; de ahi que las barras invertidas vayan dobladas. */
const GUION_AUDITORIA = String.raw`(() => {
  /* ¿ESTAMOS MIDIENDO LA APLICACION, O LA PANTALLA DE ERROR DEL NAVEGADOR?
     Una vez el servidor de desarrollo se cayo a mitad de la auditoria y Chrome
     sirvio su propio interstitial. El informe dio "1 fallo por pagina, cero
     invisibles" y parecia un exito rotundo: estaba midiendo el contraste del
     boton "Detalles" de Chrome. Un resultado bueno obtenido sobre la pagina
     equivocada es peor que un fallo, porque se lo cree uno. */
  if (!document.querySelector('#app') || document.querySelector('.interstitial-wrapper')) {
    return JSON.stringify({
      noEsLaApp: true,
      titulo: document.title,
      url: location.href,
      pista: document.body ? document.body.innerText.slice(0, 120) : '',
    });
  }

  const lienzo = document.createElement('canvas');
  lienzo.width = 1; lienzo.height = 1;
  const ctx = lienzo.getContext('2d', { willReadFrequently: true });

  /* Resuelve CUALQUIER color CSS a sRGB pintandolo de verdad.
     Se pinta primero negro para que un color con alfa se componga sobre algo
     conocido en vez de devolver basura. */
  const aRGB = (color) => {
    ctx.clearRect(0, 0, 1, 1);
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, 1, 1);
    ctx.fillStyle = color;
    ctx.fillRect(0, 0, 1, 1);
    const d = ctx.getImageData(0, 0, 1, 1).data;
    return [d[0], d[1], d[2]];
  };

  const luminancia = (rgb) => {
    const c = rgb.map((v) => {
      const s = v / 255;
      return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  };

  const razon = (a, b) => {
    const la = luminancia(aRGB(a)), lb = luminancia(aRGB(b));
    const hi = Math.max(la, lb), lo = Math.min(la, lb);
    return (hi + 0.05) / (lo + 0.05);
  };

  const cordura = razon('#ffffff', '#000000');

  const alfaDe = (c) => {
    const m = c.match(/rgba?\(([^)]+)\)/);
    if (!m) return 1;
    const p = m[1].split(',').map((x) => parseFloat(x));
    return p.length > 3 ? p[3] : 1;
  };

  /* Fondo efectivo: sube por los ancestros hasta hallar uno opaco. Si por el
     camino hay una imagen o degradado de fondo, se marca: ahi el contraste
     depende del pixel y una sola cifra mentiria. */
  const fondoDe = (el) => {
    let n = el, imagen = false, veloTranslucido = false;
    while (n && n !== document.documentElement) {
      const cs = getComputedStyle(n);
      /* Un velo semitransparente sobre una foto (el patron de los heroes) deja
         el fondo real compuesto por el navegador. Si subimos atravesandolo y
         acabamos en el fondo del documento, la cifra que saldria no es la que
         ve nadie: el texto blanco del heroe parecia estar sobre blanco. Se
         recuerda que se atraveso un velo para marcarlo como no concluyente. */
      const a = alfaDe(cs.backgroundColor);
      if (a > 0.05 && a <= 0.95) veloTranslucido = true;

      /* CAPAS HERMANAS, que es como se construyen todos los heroes de este
         portal: el fondo no esta en un ancestro sino en un <div absolute
         inset-0> puesto ANTES del contenido. Subiendo por ancestros no se ve
         jamas, asi que el texto blanco del heroe parecia estar sobre el fondo
         del documento y salia como invisible. Aqui se mira si algun ancestro
         tiene una capa hija que cubra todo y no contenga a nuestro elemento. */
      for (const hijo of n.children) {
        if (hijo.contains(el) || hijo === el) continue;
        const hcs = getComputedStyle(hijo);
        if (hcs.position !== 'absolute' && hcs.position !== 'fixed') continue;
        const r = hijo.getBoundingClientRect();
        const rn = n.getBoundingClientRect();
        const cubre = r.width >= rn.width * 0.9 && r.height >= rn.height * 0.9;
        if (!cubre) continue;
        if (hcs.backgroundImage && hcs.backgroundImage !== 'none') return { color: cs.backgroundColor, imagen: true };
        /* La capa puede ser transparente ella misma y llevar la foto DENTRO,
           que es como esta montado el heroe de cobertura: un <div absolute>
           con un <img> y un velo encima. Mirar solo el fondo de la capa no
           bastaba. */
        if (hijo.querySelector('img, video, svg')) return { color: cs.backgroundColor, imagen: true };
        const ha = alfaDe(hcs.backgroundColor);
        if (ha > 0.95) return { color: hcs.backgroundColor, imagen };
        if (ha > 0.05) veloTranslucido = true;
      }
      /* OJO: un degradado es background-image, NO background-color. La
         primera version solo miraba el color, asi que atravesaba el boton con
         degradado y comparaba su texto contra la seccion de detras: daba
         fallos que no existian. Al encontrar un degradado o una imagen se
         deja de subir y se marca el resultado como no concluyente, porque el
         contraste depende del pixel y una sola cifra mentiria igual.
         (Y sin acentos graves aqui dentro: esto vive en una plantilla
         String.raw y un acento grave la cerraria.) */
      if (cs.backgroundImage && cs.backgroundImage !== 'none') {
        return { color: cs.backgroundColor, imagen: true };
      }
      if (alfaDe(cs.backgroundColor) > 0.95) return { color: cs.backgroundColor, imagen };
      n = n.parentElement;
    }
    return {
      color: getComputedStyle(document.body).backgroundColor,
      imagen: imagen || veloTranslucido,
    };
  };

  const visible = (el) => {
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden') return false;
    if (parseFloat(cs.opacity) < 0.1) return false;
    const r = el.getBoundingClientRect();
    return r.width > 1 && r.height > 1;
  };

  /* Texto PROPIO del elemento, sin el de sus hijos: si no, un <div> que
     envuelve toda la pagina se mediria como si fuera un parrafo. */
  const textoPropio = (el) => Array.from(el.childNodes)
    .filter((n) => n.nodeType === 3)
    .map((n) => n.textContent.trim())
    .join(' ')
    .trim();

  const umbralTexto = (cs) => {
    const px = parseFloat(cs.fontSize);
    const peso = parseInt(cs.fontWeight, 10) || 400;
    return (px >= 24 || (px >= 18.66 && peso >= 700)) ? 3 : 4.5;
  };

  const ruta = (el) => {
    const partes = [];
    let n = el;
    for (let i = 0; n && i < 3; i++) {
      let p = n.tagName.toLowerCase();
      const cls = (n.className && typeof n.className === 'string')
        ? n.className.trim().split(/\s+/).filter((c) => !/^(ng-|v-)/.test(c)).slice(0, 2).join('.')
        : '';
      if (cls) p += '.' + cls;
      partes.unshift(p);
      n = n.parentElement;
    }
    return partes.join(' > ');
  };

  const fallos = [];
  const invisibles = [];
  /* Medidas sobre degradado o imagen: la cifra no es concluyente, asi que no
     cuentan como fallo, pero tampoco se tiran. Se listan aparte para que se
     revisen a ojo, que ahi es el unico metodo honesto. */
  const noConcluyentes = [];
  let medidos = 0;

  /* --- 1. Texto -------------------------------------------------------- */
  for (const el of document.querySelectorAll('body *')) {
    const txt = textoPropio(el);
    if (!txt || txt.length < 2) continue;
    if (!visible(el)) continue;

    const cs = getComputedStyle(el);
    const fondo = fondoDe(el);
    const r = razon(cs.color, fondo.color);
    const umbral = umbralTexto(cs);
    medidos++;

    const registro = {
      tipo: 'texto',
      razon: Number(r.toFixed(2)),
      umbral,
      color: cs.color,
      fondo: fondo.color,
      sobreImagen: fondo.imagen,
      texto: txt.slice(0, 40),
      ruta: ruta(el),
    };

    if (r >= umbral) continue;
    if (fondo.imagen) noConcluyentes.push(registro);
    else if (r < 1.5) invisibles.push(registro);
    else fallos.push(registro);
  }

  /* --- 2. Bordes de controles ------------------------------------------ */
  for (const el of document.querySelectorAll('input, select, textarea, button, a[class*="border"]')) {
    if (!visible(el)) continue;
    const cs = getComputedStyle(el);
    if (parseFloat(cs.borderTopWidth) < 0.5) continue;
    if (cs.borderTopStyle === 'none') continue;

    const fondo = fondoDe(el.parentElement || el);
    const r = razon(cs.borderTopColor, fondo.color);
    medidos++;
    if (r < 3) {
      (fondo.imagen ? noConcluyentes : fallos).push({
        tipo: 'borde',
        razon: Number(r.toFixed(2)),
        umbral: 3,
        color: cs.borderTopColor,
        fondo: fondo.color,
        sobreImagen: fondo.imagen,
        texto: (el.getAttribute('placeholder') || el.textContent || el.tagName).trim().slice(0, 40),
        ruta: ruta(el),
      });
    }
  }

  /* --- 3. Anillo de foco ----------------------------------------------- */
  const focables = Array.from(document.querySelectorAll('a[href], button, input, select, textarea'))
    .filter(visible).slice(0, 25);
  for (const el of focables) {
    el.focus();
    /* Casi todos los anillos del proyecto se declaran con focus-visible:, y
       esa pseudoclase NO se activa con un focus() por script en botones ni
       casillas: el navegador la reserva para el foco por teclado. Midiendo asi
       se leia el contorno por defecto (currentColor) y salian fallos que no
       existen. Si no se consigue el estado real, no se mide. */
    if (!el.matches(':focus-visible')) { el.blur(); continue; }
    const cs = getComputedStyle(el);
    const ancho = parseFloat(cs.outlineWidth) || 0;
    const sombra = cs.boxShadow && cs.boxShadow !== 'none';
    if (ancho < 1 && !sombra) {
      fallos.push({
        tipo: 'foco',
        razon: 0, umbral: 3,
        color: 'sin contorno', fondo: fondoDe(el).color, sobreImagen: false,
        texto: (el.textContent || el.getAttribute('aria-label') || el.tagName).trim().slice(0, 40),
        ruta: ruta(el),
      });
      continue;
    }
    if (ancho >= 1) {
      const r = razon(cs.outlineColor, fondoDe(el).color);
      medidos++;
      if (r < 3) {
        fallos.push({
          tipo: 'foco', razon: Number(r.toFixed(2)), umbral: 3,
          color: cs.outlineColor, fondo: fondoDe(el).color, sobreImagen: false,
          texto: (el.textContent || el.tagName).trim().slice(0, 40),
          ruta: ruta(el),
        });
      }
    }
    el.blur();
  }

  return JSON.stringify({ cordura: Number(cordura.toFixed(2)), medidos, fallos, invisibles, noConcluyentes });
})()`

let ws
const resumen = []

try {
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

  ws = new globalThis.WebSocket(objetivo.webSocketDebuggerUrl)
  await new Promise((r) => { ws.onopen = r })

  let id = 0
  const pendientes = new Map()
  ws.onmessage = (e) => {
    const m = JSON.parse(e.data)
    if (m.id && pendientes.has(m.id)) { pendientes.get(m.id)(m.result); pendientes.delete(m.id) }
  }
  /* CON PLAZO, y no por prudencia abstracta: la primera version se quedo
     colgada para siempre en la ultima pagina porque una respuesta de CDP no
     llego nunca y la promesa no tenia forma de rendirse. Una auditoria que se
     cuelga sin decir nada es peor que una que falla. */
  const enviar = (method, params = {}, plazo = 20000) => new Promise((res) => {
    const n = ++id
    const reloj = setTimeout(() => {
      if (pendientes.delete(n)) res({ __plazoAgotado: true, method })
    }, plazo)
    pendientes.set(n, (valor) => { clearTimeout(reloj); res(valor) })
    ws.send(JSON.stringify({ id: n, method, params }))
  })

  await enviar('Page.enable')
  await enviar('Runtime.enable')

  for (const tema of TEMAS) {
    for (const ruta of RUTAS) {
      /* El tema se fija ANTES de cargar y se recarga: el script
         anti-parpadeo del index.html lo lee en el primer pintado. */
      await enviar('Page.navigate', { url: `${BASE}${ruta}` })
      await dormir(1200)
      await enviar('Runtime.evaluate', {
        expression: `localStorage.setItem('botica:tema','${tema}')`,
      })
      await enviar('Page.navigate', { url: `${BASE}${ruta}` })
      await dormir(2600)

      const r = await enviar('Runtime.evaluate', { expression: GUION_AUDITORIA, returnByValue: true }, 30000)
      if (r?.__plazoAgotado) {
        console.log(`  [${tema}] ${ruta}: SIN RESPUESTA en 30 s, se omite`)
        continue
      }
      if (r?.exceptionDetails) {
        console.log(`  [${tema}] ${ruta}: el guion lanzo ${r.exceptionDetails.exception?.description?.split('\n')[0] ?? 'un error'}`)
        continue
      }
      if (!r?.result?.value) {
        console.log(`  [${tema}] ${ruta}: la pagina no devolvio medidas`)
        continue
      }
      const d = JSON.parse(r.result.value)
      if (d.noEsLaApp) {
        console.error(`\nLA PAGINA NO ES LA APLICACION en ${ruta} (${tema})`)
        console.error(`  titulo: ${d.titulo}`)
        console.error(`  ${d.pista.replace(/\s+/g, ' ').slice(0, 110)}`)
        console.error(`  Comprueba que el servidor de desarrollo este levantado en ${BASE}.`)
        exit(1)
      }
      if (d.cordura !== 21) {
        console.error(`FORMULA INCORRECTA en ${ruta} (${tema}): blanco sobre negro dio ${d.cordura}`)
        exit(1)
      }
      resumen.push({ tema, ruta, ...d })
      writeFileSync(ARCHIVO, JSON.stringify(resumen), 'utf8')
      console.log(`  [${tema.padEnd(6)}] ${ruta.padEnd(11)} medidos ${String(d.medidos).padStart(4)}  fallos ${String(d.fallos.length).padStart(3)}  invisibles ${d.invisibles.length}  sobre-degradado ${d.noConcluyentes.length}`)
    }
  }
} finally {
  try { ws?.close() } catch { /* ignorado */ }
  proc.kill()
}

/* ---------------------------------------------------------------- informe */

const agrupar = (lista) => {
  const mapa = new Map()
  for (const f of lista) {
    const clave = `${f.tipo}|${f.color}|${f.fondo}|${f.umbral}`
    if (!mapa.has(clave)) mapa.set(clave, { ...f, veces: 0, donde: new Set(), ejemplos: new Set() })
    const g = mapa.get(clave)
    g.veces++
    g.donde.add(`${f.tema}:${f.ruta}`)
    if (g.ejemplos.size < 3) g.ejemplos.add(f.texto)
  }
  return [...mapa.values()].sort((a, b) => a.razon - b.razon)
}

const todosFallos = resumen.flatMap((r) => r.fallos.map((f) => ({ ...f, tema: r.tema, ruta: r.ruta })))
const todosInvisibles = resumen.flatMap((r) => r.invisibles.map((f) => ({ ...f, tema: r.tema, ruta: r.ruta })))

console.log(`\n${'='.repeat(78)}`)
console.log(`Total medido: ${resumen.reduce((n, r) => n + r.medidos, 0)} comprobaciones en ${resumen.length} paginas-tema`)
console.log(`Fallos: ${todosFallos.length}   Invisibles: ${todosInvisibles.length}`)
console.log('='.repeat(78))

if (todosInvisibles.length) {
  console.log(`\nINVISIBLE (razon < 1.5 — el texto es casi del color del fondo):`)
  for (const g of agrupar(todosInvisibles)) {
    console.log(`\n  ${g.razon}:1  ${g.color} sobre ${g.fondo}${g.sobreImagen ? '  [hay imagen/degradado detras]' : ''}`)
    console.log(`     ${g.veces} apariciones · ${[...g.donde].slice(0, 4).join(', ')}`)
    console.log(`     ej: "${[...g.ejemplos].join('" / "')}"`)
    console.log(`     ${g.ruta}`)
  }
}

if (todosFallos.length) {
  console.log(`\nPOR DEBAJO DEL MINIMO:`)
  for (const g of agrupar(todosFallos)) {
    console.log(`\n  [${g.tipo}] ${g.razon}:1 (minimo ${g.umbral})  ${g.color} sobre ${g.fondo}${g.sobreImagen ? '  [hay imagen/degradado detras]' : ''}`)
    console.log(`     ${g.veces} apariciones · ${[...g.donde].slice(0, 4).join(', ')}`)
    console.log(`     ej: "${[...g.ejemplos].join('" / "')}"`)
    console.log(`     ${g.ruta}`)
  }
}

if (!todosFallos.length && !todosInvisibles.length) {
  console.log('\nSin fallos de contraste en ninguno de los dos temas.')
}
console.log('')
