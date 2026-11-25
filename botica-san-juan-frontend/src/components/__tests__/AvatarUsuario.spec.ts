import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AvatarUsuario from '../AvatarUsuario.vue'
import ZonaDeFoto from '../ZonaDeFoto.vue'

const PROPIA = 'data:image/webp;base64,UklGRg=='
const GOOGLE = 'https://lh3.googleusercontent.com/a/rosa=s96-c'
const rosa = { nombre: 'Rosa Quispe Huamán' }

const nivel = (w: ReturnType<typeof mount>) => w.get('[data-testid="avatar-usuario"]').attributes('data-nivel')

describe('AvatarUsuario · cascada', () => {
  it('nivel 1: la foto propia gana a la de Google', () => {
    const w = mount(AvatarUsuario, { props: { usuario: { ...rosa, avatar_data: PROPIA, avatar_oauth_url: GOOGLE } } })
    expect(nivel(w)).toBe('1')
    expect(w.get('img').attributes('src')).toBe(PROPIA)
  })

  it('nivel 2: Google, sin enviar el Referer', () => {
    const w = mount(AvatarUsuario, { props: { usuario: { ...rosa, avatar_oauth_url: GOOGLE } } })
    expect(nivel(w)).toBe('2')
    expect(w.get('img').attributes('src')).toBe(GOOGLE)
    expect(w.get('img').attributes('referrerpolicy')).toBe('no-referrer')
  })

  it('nivel 3: SVG con dos iniciales', () => {
    const w = mount(AvatarUsuario, { props: { usuario: rosa } })
    expect(nivel(w)).toBe('3')
    expect(w.find('img').exists()).toBe(false)
    expect(w.get('svg text').text()).toBe('RQ')
  })

  it('cada fallo de carga baja un solo nivel', async () => {
    const w = mount(AvatarUsuario, {
      props: { usuario: { ...rosa, foto_perfil: 'storage/perfiles/borrada.jpg', avatar_oauth_url: GOOGLE } },
    })
    expect(nivel(w)).toBe('1')
    await w.get('img').trigger('error')
    expect(nivel(w)).toBe('2')
    await w.get('img').trigger('error')
    expect(nivel(w)).toBe('3')
  })

  it('una foto nueva tiene su propio intento aunque la anterior fallara', async () => {
    const w = mount(AvatarUsuario, { props: { usuario: { ...rosa, avatar_oauth_url: GOOGLE } } })
    await w.get('img').trigger('error')
    expect(nivel(w)).toBe('3')
    await w.setProps({ usuario: { ...rosa, avatar_data: PROPIA, avatar_oauth_url: GOOGLE } })
    expect(nivel(w)).toBe('1')
  })

  it('sin cuenta ni nombre muestra un respaldo y no revienta', () => {
    expect(mount(AvatarUsuario).get('svg text').text()).toBe('?')
    expect(mount(AvatarUsuario, { props: { usuario: { nombre: 'Ñusta' } } }).get('svg text').text()).toBe('Ñ')
  })
})

describe('ZonaDeFoto · volver atrás', () => {
  const boton = (usuario: object) => mount(ZonaDeFoto, { props: { usuario } }).find('button')

  it('con Google detrás ofrece volver a su foto', async () => {
    const w = mount(ZonaDeFoto, { props: { usuario: { ...rosa, avatar_data: PROPIA, avatar_oauth_url: GOOGLE } } })
    expect(w.get('button').text()).toBe('Volver a mi foto de Google')
    await w.get('button').trigger('click')
    expect(w.emitted('eliminar')).toHaveLength(1)
  })

  it('sin Google ofrece quitarla, y sin foto propia no ofrece nada', () => {
    expect(boton({ ...rosa, avatar_data: PROPIA }).text()).toBe('Quitar foto')
    expect(boton({ ...rosa, avatar_oauth_url: GOOGLE }).exists()).toBe(false)
  })
})
