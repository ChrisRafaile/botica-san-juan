import { describe, expect, it } from 'vitest'
import { urlDeMedia } from '../media'

describe('urlDeMedia', () => {
  it('trata images/default_image.png como ausencia de foto para evitar 404 en el catálogo', () => {
    expect(urlDeMedia('images/default_image.png')).toBeNull()
    expect(urlDeMedia('/images/default_image.png')).toBeNull()
    expect(urlDeMedia('  IMAGES/default_image.png  ')).toBeNull()
  })

  it('devuelve null ante valores vacíos o nulos', () => {
    expect(urlDeMedia(null)).toBeNull()
    expect(urlDeMedia(undefined)).toBeNull()
    expect(urlDeMedia('   ')).toBeNull()
  })

  it('conserva URLs absolutas ya resueltas', () => {
    expect(urlDeMedia('https://cdn.ejemplo.pe/productos/amoxicilina.webp')).toBe(
      'https://cdn.ejemplo.pe/productos/amoxicilina.webp',
    )
    expect(urlDeMedia('data:image/webp;base64,AAA')).toBe('data:image/webp;base64,AAA')
  })

  it('normaliza rutas relativas de storage/productos sin duplicar barras', () => {
    const resuelta = urlDeMedia('/storage/productos/38530.webp')
    expect(resuelta).toBeTruthy()
    expect(resuelta).toMatch(/\/storage\/productos\/38530\.webp$/)
    expect(urlDeMedia('storage/productos/38530.webp')).toBe(resuelta)
  })
})
