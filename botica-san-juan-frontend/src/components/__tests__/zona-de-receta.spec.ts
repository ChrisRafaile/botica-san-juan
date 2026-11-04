import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import ZonaDeReceta from '../ZonaDeReceta.vue'

/**
 * Zona de arrastre de la receta.
 *
 * Estas pruebas existen porque el gesto de arrastrar y soltar es justo lo que
 * una captura de pantalla no demuestra: la imagen enseña la zona dibujada, no
 * que al soltar un PDF pase algo ni que al soltar un .exe no pase nada.
 *
 * Lo que se protege:
 *
 *   1. Que acepte las dos formas en que llega una receta de verdad: la foto
 *      del móvil y el PDF del consultorio.
 *   2. Que rechace lo demás ANTES de subirlo, para no hacer esperar a nadie
 *      por un archivo que el servidor va a rechazar igual.
 *   3. Que el resaltado del arrastre no parpadee al pasar por los hijos.
 *   4. Que volver a elegir el mismo archivo vuelva a contar.
 */

/* jsdom no implementa object URLs. Se sustituyen por un par de funciones que
   además permiten comprobar que lo reservado se libera. */
const creados: string[] = []
const revocados: string[] = []

beforeEach(() => {
  creados.length = 0
  revocados.length = 0

  globalThis.URL.createObjectURL = vi.fn(() => {
    const url = `blob:prueba/${creados.length}`
    creados.push(url)
    return url
  })
  globalThis.URL.revokeObjectURL = vi.fn((url: string) => {
    revocados.push(url)
  })
})

function archivo(nombre: string, tipo: string, bytes = 1024): File {
  const f = new File(['x'], nombre, { type: tipo })
  Object.defineProperty(f, 'size', { value: bytes })
  return f
}

function soltar(vista: ReturnType<typeof mount>, ...archivos: File[]) {
  return vista.find('[class*="border-dashed"]').trigger('drop', {
    dataTransfer: { files: archivos },
  })
}

describe('ZonaDeReceta', () => {
  it('acepta la foto del movil', async () => {
    const vista = mount(ZonaDeReceta)

    await soltar(vista, archivo('receta.jpg', 'image/jpeg'))

    expect(vista.emitted('elegir')).toHaveLength(1)
    expect(vista.text()).toContain('receta.jpg')
  })

  it('acepta el pdf del consultorio', async () => {
    /* Rechazar el PDF obligaría a imprimirlo para volver a fotografiarlo. */
    const vista = mount(ZonaDeReceta)

    await soltar(vista, archivo('receta.pdf', 'application/pdf'))

    expect(vista.emitted('elegir')).toHaveLength(1)
  })

  it('rechaza un tipo que el mostrador no podria abrir', async () => {
    const vista = mount(ZonaDeReceta)

    await soltar(vista, archivo('virus.exe', 'application/x-msdownload'))

    expect(vista.emitted('elegir')).toBeUndefined()
    expect(vista.text()).toContain('Solo aceptamos')
  })

  it('rechaza un archivo mas grande que el limite del servidor', async () => {
    const vista = mount(ZonaDeReceta)

    await soltar(vista, archivo('enorme.jpg', 'image/jpeg', 9 * 1024 * 1024))

    expect(vista.emitted('elegir')).toBeUndefined()
    /* El mensaje dice cuánto pesa y cuánto cabe: «archivo demasiado grande»
       no le dice a nadie qué hacer. */
    expect(vista.text()).toContain('9.0 MB')
    expect(vista.text()).toContain('8 MB')
  })

  it('no hace nada cuando esta bloqueada', async () => {
    const vista = mount(ZonaDeReceta, { props: { bloqueada: true } })

    await soltar(vista, archivo('receta.jpg', 'image/jpeg'))

    expect(vista.emitted('elegir')).toBeUndefined()
  })

  it('el resaltado no parpadea al pasar por los hijos', async () => {
    const vista = mount(ZonaDeReceta)
    const zona = vista.find('[class*="border-dashed"]')

    /* Con un booleano en lugar de un contador, el `dragleave` del hijo
       apagaría el resaltado aunque el puntero siga dentro de la zona. */
    await zona.trigger('dragenter')
    await zona.trigger('dragenter')
    await zona.trigger('dragleave')

    expect(zona.classes().join(' ')).toContain('border-clinico-500')

    await zona.trigger('dragleave')
    expect(zona.classes().join(' ')).not.toContain('border-clinico-500')
  })

  it('elegir dos veces el mismo archivo cuenta dos veces', async () => {
    const vista = mount(ZonaDeReceta)
    const campo = vista.find('input[type="file"]')
    const uno = archivo('receta.jpg', 'image/jpeg')

    Object.defineProperty(campo.element, 'files', { value: [uno], writable: true })
    await campo.trigger('change')
    await campo.trigger('change')

    /* El componente limpia el valor del campo tras cada elección. Sin eso, el
       navegador no vuelve a disparar `change` con el mismo archivo y parece
       que la aplicación se ha quedado colgada. */
    expect(vista.emitted('elegir')).toHaveLength(2)
  })

  it('libera la vista previa al cambiar de archivo y al desmontar', async () => {
    const vista = mount(ZonaDeReceta)

    await soltar(vista, archivo('una.jpg', 'image/jpeg'))
    await soltar(vista, archivo('otra.jpg', 'image/jpeg'))

    expect(revocados).toContain(creados[0])

    vista.unmount()

    /* Un object URL sin revocar retiene el archivo en memoria mientras viva
       la pestaña. Con recetas de 8 MB eso se nota. */
    expect(revocados).toHaveLength(creados.length)
  })

  it('el campo de archivo es alcanzable con el teclado', async () => {
    const vista = mount(ZonaDeReceta)
    const campo = vista.find('input[type="file"]')

    /* `display:none` lo sacaría del orden de tabulación y dejaría la zona
       inutilizable sin ratón. Se oculta con opacidad. */
    expect(campo.classes().join(' ')).toContain('opacity-0')
    expect(campo.classes().join(' ')).not.toContain('hidden')
    expect(campo.attributes('aria-label')).toBeTruthy()
  })
})
