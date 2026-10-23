import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import ImagenConRespaldo from '@/components/ImagenConRespaldo.vue'

/**
 * Lo que estas pruebas protegen NO es el componente: es la regla.
 *
 * El mismo fallo —imagen rota en pantalla— se cometió tres veces en el
 * proyecto, siempre por escribir el respaldo de una forma que parece correcta
 * y no lo es. Ninguna de las tres la detectó `vue-tsc` ni la compilación: las
 * tres pasaron limpias y el fallo lo delató una captura de pantalla.
 *
 * Por eso cada prueba de aquí corresponde a una de las formas de equivocarse,
 * y no a «cubrir el componente».
 */
describe('ImagenConRespaldo', () => {
  const alt = 'Paracetamol 500 mg'

  it('pinta el respaldo aunque la imagen cargue bien', () => {
    /* Es la regla central. El respaldo es un HERMANO que está siempre, no un
       `v-else`: si fuera excluyente, al fallar la imagen no quedaría nada
       debajo y se vería un hueco. */
    const w = mount(ImagenConRespaldo, { props: { ruta: 'images/p.png', alt } })

    expect(w.find('img').exists()).toBe(true)
    expect(w.find('svg').exists()).toBe(true)
  })

  it('retira la imagen cuando el archivo responde con error', async () => {
    const w = mount(ImagenConRespaldo, { props: { ruta: 'images/borrada.png', alt } })

    expect(w.find('img').exists()).toBe(true)
    await w.find('img').trigger('error')

    expect(w.find('img').exists()).toBe(false)
    expect(w.find('svg').exists()).toBe(true)
  })

  it('no pide ninguna imagen cuando no hay ruta', () => {
    /* Un `src` vacío hace que el navegador pida la página actual como si fuera
       una imagen. Es una petición inútil que además acaba en error. */
    const w = mount(ImagenConRespaldo, { props: { ruta: null, alt } })

    expect(w.find('img').exists()).toBe(false)
    expect(w.find('svg').exists()).toBe(true)
  })

  it('vuelve a intentarlo cuando cambia la ruta', async () => {
    /* Sin esto, una imagen que falló deja el componente marcado para siempre y
       la siguiente —que puede existir— ya no se pinta nunca. Ocurre al navegar
       entre productos con el mismo modal abierto, que es justo el caso del
       modal de editar producto. */
    const w = mount(ImagenConRespaldo, { props: { ruta: 'images/rota.png', alt } })

    await w.find('img').trigger('error')
    expect(w.find('img').exists()).toBe(false)

    await w.setProps({ ruta: 'images/buena.png' })
    expect(w.find('img').exists()).toBe(true)
  })

  it('resuelve la ruta relativa contra el origen de la API, no contra la pagina', () => {
    /* Este era el SEGUNDO fallo del modal, el que no se veía: el `src` recibía
       `images/foo.png` tal cual, y el navegador lo resolvía contra el origen
       de la página. La imagen salía rota aunque el archivo existiera. */
    const w = mount(ImagenConRespaldo, { props: { ruta: 'images/foo.png', alt } })

    expect(w.find('img').attributes('src')).not.toBe('images/foo.png')
    expect(w.find('img').attributes('src')).toContain('images/foo.png')
  })

  it('deja intactas las rutas que ya traen su propio esquema', () => {
    /* La cámara y el selector de archivos entregan `data:` URLs. Tocarlas las
       rompería. */
    const datos = 'data:image/png;base64,iVBORw0KGgo='
    const w = mount(ImagenConRespaldo, { props: { ruta: datos, alt } })

    expect(w.find('img').attributes('src')).toBe(datos)
  })

  it('siempre expone el texto alternativo', () => {
    const w = mount(ImagenConRespaldo, { props: { ruta: 'images/p.png', alt } })

    expect(w.find('img').attributes('alt')).toBe(alt)
  })

  it('el respaldo por defecto es decorativo para los lectores de pantalla', () => {
    /* El icono genérico no aporta información: anunciarlo solo añade ruido a
       quien navega con lector de pantalla. */
    const w = mount(ImagenConRespaldo, { props: { ruta: null, alt } })

    expect(w.find('svg').attributes('aria-hidden')).toBe('true')
  })

  it('admite un respaldo propio por slot', () => {
    const w = mount(ImagenConRespaldo, {
      props: { ruta: null, alt },
      slots: { respaldo: '<span data-prueba="propio">sin foto</span>' },
    })

    expect(w.find('[data-prueba="propio"]').exists()).toBe(true)
  })
})
