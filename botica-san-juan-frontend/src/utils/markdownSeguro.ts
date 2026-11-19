/**
 * Convierte un subconjunto mínimo de Markdown a HTML seguro, escapando todo
 * HTML crudo antes de aplicar formato para impedir inyección XSS.
 */
function escaparHtml(texto: string): string {
  return texto
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;')
}

function formatearLinea(linea: string): string {
  return linea
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/`([^`]+)`/g, '<code class="rounded bg-superficie-hundida px-1 py-0.5 font-mono text-xs">$1</code>')
}

export function renderizarMarkdownSeguro(entrada: string): string {
  const seguro = escaparHtml(entrada)
  const lineas = seguro.split(/\r?\n/)
  const salida: string[] = []
  let enLista = false

  for (const linea of lineas) {
    const item = linea.match(/^\s*[-*]\s+(.+)$/)
    if (item) {
      if (!enLista) {
        salida.push('<ul class="my-1 list-disc space-y-0.5 pl-4">')
        enLista = true
      }
      salida.push(`<li>${formatearLinea(item[1] ?? '')}</li>`)
      continue
    }

    if (enLista) {
      salida.push('</ul>')
      enLista = false
    }

    if (linea.trim() === '') {
      salida.push('<div class="h-1.5"></div>')
    } else {
      salida.push(`<p>${formatearLinea(linea)}</p>`)
    }
  }

  if (enLista) {
    salida.push('</ul>')
  }

  return salida.join('')
}
