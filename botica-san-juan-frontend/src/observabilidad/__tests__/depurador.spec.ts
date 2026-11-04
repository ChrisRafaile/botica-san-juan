import { describe, expect, it } from 'vitest'

import {
  limpiarPropiedades,
  limpiarTexto,
  limpiarUrl,
  rutaGrabable,
} from '../depurador'

/**
 * Lo que NO puede salir del navegador.
 * ---------------------------------------------------------------------------
 * Estas pruebas son la contraparte en el cliente de DepuracionSentryTest en el
 * backend. No comprueban que Sentry o PostHog funcionen —eso es asunto de
 * ellos— sino que lo que se les entrega va depurado.
 *
 * Están escritas sobre casos reales de esta botica: «amoxicilina» en la cadena
 * de consulta, un DNI en el mensaje de una excepción, la ruta de una receta.
 * Una prueba con «foo» y «bar» no habría encontrado ninguno.
 *
 * Si alguien amplía la analítica y una de estas pruebas se pone roja, la
 * pregunta correcta no es cómo arreglar la prueba: es si ese dato debía salir.
 */
describe('depuración de texto', () => {
  it('no deja pasar un DNI', () => {
    const limpio = limpiarTexto('No se encontró al usuario 47382910')

    expect(limpio).not.toContain('47382910')
    expect(limpio).toContain('[dni]')
  })

  it('no deja pasar un correo', () => {
    const limpio = limpiarTexto('Fallo al avisar a rosa.quispe@gmail.com')

    expect(limpio).not.toContain('rosa.quispe@gmail.com')
    expect(limpio).toContain('[correo]')
  })

  it('no deja pasar un token de Sanctum', () => {
    const limpio = limpiarTexto(
      'Authorization: Bearer 43|BFHLaamKd9ET1MQHAGrfHmrIMZmHHIQqAEmR25Tk',
    )

    expect(limpio).not.toContain('BFHLaamKd9ET1MQHAGrfHmrIMZmHHIQqAEmR25Tk')
  })

  it('no deja pasar una clave de API', () => {
    /* Las de Resend empiezan por `re_`; las de otros proveedores por `sk_` o
       `pk_`. Un error de integración las arrastra al mensaje con facilidad. */
    /* Clave FICTICIA, y que se note. Una cadena con pinta de clave real en un
       repositorio público la marcan los escáneres de secretos, y quien la lea
       dentro de un año no podrá saber si lo era. */
    const limpio = limpiarTexto('Rechazado con la clave re_ESTO_ES_FICTICIO_000000')

    expect(limpio).not.toContain('re_ESTO_ES_FICTICIO_000000')
    expect(limpio).toContain('[clave]')
  })

  it('enmascara el RUC y el teléfono', () => {
    const limpio = limpiarTexto('Emisor 10075148297, contacto 987654321')

    expect(limpio).not.toContain('10075148297')
    expect(limpio).not.toContain('987654321')
  })

  it('no destroza un mensaje sin datos personales', () => {
    /* Un depurador demasiado agresivo deja los errores ilegibles, y entonces
       nadie los mira. Un número de pedido o una cantidad no son datos
       personales. */
    const mensaje = 'Stock insuficiente en el pedido 217: se pidieron 10 y hay 3'

    expect(limpiarTexto(mensaje)).toBe(mensaje)
  })
})

describe('depuración de URL', () => {
  it('el término de búsqueda de un medicamento no viaja', () => {
    const limpia = limpiarUrl('https://botica.pe/products?q=amoxicilina&page=2')

    expect(limpia).not.toContain('amoxicilina')
    expect(limpia).toContain('page=2')
  })

  it('tapa los parámetros de búsqueda con cualquiera de sus nombres', () => {
    const limpia = limpiarUrl(
      'https://botica.pe/products?buscar=ivermectina&termino=test+de+embarazo',
    )

    expect(limpia).not.toContain('ivermectina')
    expect(limpia).not.toContain('embarazo')
  })

  it('un parámetro nuevo que nadie añadió a la lista se depura igual', () => {
    /* La defensa no puede depender de mantener al día una lista de nombres:
       lo que quede de la consulta pasa por los patrones de todos modos. */
    const limpia = limpiarUrl('https://botica.pe/perfil?contacto=rosa.quispe@gmail.com')

    expect(limpia).not.toContain('rosa.quispe@gmail.com')
  })

  it('el enlace firmado de recuperación de contraseña no viaja', () => {
    const limpia = limpiarUrl(
      'https://botica.pe/restablecer-contrasena?token=a1b2c3d4e5&correo=rosa@gmail.com',
    )

    expect(limpia).not.toContain('a1b2c3d4e5')
    expect(limpia).not.toContain('rosa@gmail.com')
  })

  it('agrupa los identificadores numéricos en lugar de dispersarlos', () => {
    expect(limpiarUrl('https://botica.pe/pedidos/217')).toContain('/pedidos/:id')
    expect(limpiarUrl('https://botica.pe/products/41/lotes/9')).toContain(
      '/products/:id/lotes/:id',
    )
  })

  it('tira el fragmento', () => {
    /* El `#` no llega al servidor, pero sí al SDK, y ahí puede ir cualquier
       cosa que el portal haya puesto. */
    expect(limpiarUrl('https://botica.pe/perfil#dni-47382910')).not.toContain('47382910')
  })

  it('una cadena malformada pero interpretable se depura igual', () => {
    /* `new URL(x, origen)` resuelve casi cualquier cosa como ruta relativa en
       lugar de fallar. Eso es bueno: la depuración se aplica de todas formas y
       no hace falta llegar al descarte. */
    const limpia = limpiarUrl('://roto?q=amoxicilina')

    expect(limpia).not.toContain('amoxicilina')
  })

  it('una URL que de verdad no se puede analizar se descarta entera', () => {
    /* Hace falta una absoluta inválida —con esquema y un espacio dentro— para
       que el analizador se rinda. Entonces es preferible perder el dato a
       dejar pasar una cadena sin mirar. */
    expect(limpiarUrl('https://bot ica.pe/products?q=amoxicilina')).toBe('[url]')
  })
})

describe('rutas que no se graban', () => {
  it.each([
    ['/perfil', 'lleva nombre, DNI, correo y la foto de la persona'],
    ['/cart', 'las líneas del carrito dicen qué medicamentos compra'],
    ['/restablecer-contrasena', 'la pantalla tiene la contraseña escrita'],
    ['/verificar-correo', 'lleva el enlace firmado en la dirección'],
    ['/auth/google', 'es el retorno del proveedor, con el código de intercambio'],
  ])('%s no es grabable porque %s', (ruta) => {
    expect(rutaGrabable(ruta)).toBe(false)
  })

  it('las rutas hijas de una prohibida tampoco se graban', () => {
    /* `startsWith`, no igualdad: si mañana aparece /perfil/recetas, queda
       cubierto sin tocar la lista. */
    expect(rutaGrabable('/perfil/recetas')).toBe(false)
    expect(rutaGrabable('/cart/confirmar')).toBe(false)
  })

  it('el catálogo y el inicio sí se graban', () => {
    /* La grabación no es un todo o nada: en las pantallas sin datos clínicos
       sigue sirviendo para ver dónde se atasca la gente, y ahí el texto va
       enmascarado por `maskTextSelector`. */
    expect(rutaGrabable('/')).toBe(true)
    expect(rutaGrabable('/products')).toBe(true)
    expect(rutaGrabable('/contacto')).toBe(true)
  })
})

describe('propiedades de un evento de analítica', () => {
  it('el título de la página se descarta entero', () => {
    /* En la ficha de un producto el título ES el nombre del medicamento, y no
       hay forma de distinguirlo de un título inocuo. */
    const limpias = limpiarPropiedades({
      $title: 'Amoxicilina 500 mg · Botica San Juan',
      title: 'Amoxicilina 500 mg',
    })

    expect(limpias).not.toHaveProperty('$title')
    expect(limpias).not.toHaveProperty('title')
  })

  it('la URL actual y el referente se depuran', () => {
    const limpias = limpiarPropiedades({
      $current_url: 'https://botica.pe/products/217?q=amoxicilina',
      $referrer: 'https://botica.pe/products?buscar=ivermectina',
    })

    expect(JSON.stringify(limpias)).not.toContain('amoxicilina')
    expect(JSON.stringify(limpias)).not.toContain('ivermectina')
    expect(limpias.$current_url).toContain('/products/:id')
  })

  it('las URL iniciales de la sesión también', () => {
    const limpias = limpiarPropiedades({
      $initial_current_url: 'https://botica.pe/products?q=test+de+embarazo',
      $initial_referrer: 'https://buscador.pe/?q=farmacia+test+embarazo',
    })

    expect(JSON.stringify(limpias)).not.toContain('embarazo')
  })

  it('una propiedad cualquiera con datos personales se limpia', () => {
    /* Las propiedades no son solo las que pone PostHog: cualquier evento que
       se mande a mano pasa por aquí. */
    const limpias = limpiarPropiedades({
      detalle: 'Pedido de rosa.quispe@gmail.com, documento 47382910',
      cantidad: 3,
    })

    expect(limpias.detalle).not.toContain('rosa.quispe@gmail.com')
    expect(limpias.detalle).not.toContain('47382910')
    expect(limpias.cantidad).toBe(3)
  })

  it('no inventa propiedades ni pierde las que no estorban', () => {
    const limpias = limpiarPropiedades({ ruta: 'catalogo', pasos: 4 })

    expect(limpias).toEqual({ ruta: 'catalogo', pasos: 4 })
  })
})
