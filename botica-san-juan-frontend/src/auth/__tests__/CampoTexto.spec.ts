import { describe, expect, it } from 'vitest'
import { markRaw } from 'vue'
import { mount } from '@vue/test-utils'

import CampoTexto from '@/auth/components/CampoTexto.vue'

const base = { id: 'prueba-dni', etiqueta: 'DNI', modelValue: '' }

describe('CampoTexto', () => {
  it('enlaza el mensaje de error con el campo para los lectores de pantalla', () => {
    /* Un mensaje rojo sin enlazar no existe para quien usa lector de pantalla:
       lo ve el resto y él no. */
    const w = mount(CampoTexto, { props: { ...base, error: 'El DNI tiene 8 digitos' } })
    const input = w.find('input')

    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.attributes('aria-describedby')).toBe('prueba-dni-error')
    expect(w.find('#prueba-dni-error').text()).toBe('El DNI tiene 8 digitos')
  })

  it('no marca el campo como invalido cuando no hay error', () => {
    const w = mount(CampoTexto, { props: base })

    expect(w.find('input').attributes('aria-invalid')).toBeUndefined()
    expect(w.find('input').attributes('aria-describedby')).toBeUndefined()
  })

  it('reserva el hueco del error aunque no haya error', () => {
    /* Es lo que impide que el resto del formulario salte hacia abajo cuando
       aparece el mensaje, justo mientras se lee qué corregir. El párrafo
       existe siempre; lo que cambia es su contenido. */
    const w = mount(CampoTexto, { props: base })
    const hueco = w.find('#prueba-dni-error')

    expect(hueco.exists()).toBe(true)
    expect(hueco.text()).toBe('')
    expect(hueco.classes()).toContain('min-h-4')
  })

  it('anuncia el error en cuanto aparece', async () => {
    const w = mount(CampoTexto, { props: base })

    expect(w.find('[role="alert"]').text()).toBe('')
    await w.setProps({ error: 'Obligatorio' })
    expect(w.find('[role="alert"]').text()).toBe('Obligatorio')
  })

  it('expone enfocar() para que el formulario lleve el foco al campo que fallo', () => {
    const w = mount(CampoTexto, { props: base, attachTo: document.body })

    expect(typeof w.vm.enfocar).toBe('function')
    w.vm.enfocar()
    expect(document.activeElement).toBe(w.find('input').element)

    w.unmount()
  })

  it('el boton de mostrar contrasena se anuncia con su proposito', () => {
    const w = mount(CampoTexto, {
      props: {
        ...base,
        etiqueta: 'Contrasena',
        type: 'password',
        /* `markRaw` porque un componente pasado como prop acabaría envuelto en
           un proxy reactivo sin ninguna utilidad, y Vue avisa de ello. */
        accionIcono: markRaw({ render: () => null }),
        accionEtiqueta: 'Mostrar contrasena',
      },
    })

    expect(w.find('button').attributes('aria-label')).toBe('Mostrar contrasena')
  })
})
