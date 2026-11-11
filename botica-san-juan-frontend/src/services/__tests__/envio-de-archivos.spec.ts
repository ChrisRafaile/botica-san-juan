import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'
import api from '@/services/api'

/**
 * El archivo tiene que salir del navegador como multipart.
 *
 * POR QUE EXISTE ESTA PRUEBA
 *
 * El cliente fija `Content-Type: application/json` por omision. axios 1.x
 * lee esa cabecera antes de enviar y, si el cuerpo es un FormData, lo pasa
 * por `formDataToJSON` y manda texto (lib/defaults/index.js, linea 57 de la
 * version 1.20.0). El archivo se quedaba dentro del navegador.
 *
 * Desde fuera eso no se parecia a un fallo de serializacion: Laravel recibia
 * una peticion sin archivo y contestaba «Elige una imagen», que apunta al
 * usuario. Se diagnostico tres veces como un problema de tamano o de
 * dimensiones antes de mirar el cuerpo que realmente salia.
 *
 * La prueba PUEDE FALLAR: si alguien quita el borrado de la cabecera en el
 * interceptor, el cuerpo que llega al adaptador deja de ser un FormData y la
 * primera asercion se rompe.
 */
describe('envio de archivos por la API', () => {
  const adaptadorOriginal = api.defaults.adapter
  let capturada: InternalAxiosRequestConfig | undefined

  beforeEach(() => {
    capturada = undefined
    api.defaults.adapter = async (config) => {
      capturada = config as InternalAxiosRequestConfig
      return {
        data: {},
        status: 200,
        statusText: 'OK',
        headers: {},
        config: config as InternalAxiosRequestConfig,
      }
    }
  })

  afterEach(() => {
    api.defaults.adapter = adaptadorOriginal
  })

  function cuerpoConReceta(): FormData {
    const cuerpo = new FormData()
    cuerpo.append(
      'foto',
      new File([new Uint8Array([137, 80, 78, 71])], 'amoxicilina.png', { type: 'image/png' }),
    )
    return cuerpo
  }

  it('manda el FormData tal cual, sin convertirlo a JSON', async () => {
    await api.post('/perfil/foto', cuerpoConReceta())

    expect(capturada?.data).toBeInstanceOf(FormData)
    expect(typeof capturada?.data).not.toBe('string')
  })

  it('no deja puesto application/json cuando el cuerpo es un FormData', async () => {
    await api.post('/perfil/foto', cuerpoConReceta())

    const tipo = String(capturada?.headers?.['Content-Type'] ?? '')
    expect(tipo).not.toContain('application/json')
  })

  it('conserva el nombre del campo que espera el backend', async () => {
    await api.post('/perfil/foto', cuerpoConReceta())

    const cuerpo = capturada?.data as FormData
    const archivo = cuerpo.get('foto')

    expect(archivo).toBeInstanceOf(File)
    expect((archivo as File).name).toBe('amoxicilina.png')
  })

  it('sigue mandando application/json cuando el cuerpo es un objeto', async () => {
    await api.put('/perfil', { nombre: 'Administrador de demostracion' })

    const tipo = String(capturada?.headers?.['Content-Type'] ?? '')
    expect(tipo).toContain('application/json')
    expect(typeof capturada?.data).toBe('string')
  })
})
