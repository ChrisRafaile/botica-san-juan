<script setup lang="ts">
/**
 * Control de tema del portal público.
 *
 * POR QUÉ HACÍA FALTA
 *
 * El composable `useTheme` siempre tuvo tres estados y respetaba la
 * preferencia del sistema, pero sólo lo montaba la barra del administrador.
 * El portal público nunca lo invocaba, así que un visitante no tenía ninguna
 * forma de cambiar de tema: se quedaba con lo que dijera su sistema
 * operativo, sin manera de corregirlo si en esa pantalla concreta leía mal.
 *
 * POR QUÉ TRES ESTADOS Y NO UN INTERRUPTOR
 *
 * "Seguir al sistema" no es lo mismo que "claro". Quien tiene el equipo
 * configurado para oscurecerse de noche espera que la web haga lo mismo; un
 * interruptor de dos posiciones obliga a elegir uno fijo y pierde eso para
 * siempre. El botón recorre sistema → claro → oscuro y vuelve a sistema.
 *
 * ACCESIBILIDAD
 *
 * El nombre accesible dice en qué modo está Y qué pasa al pulsar, porque un
 * botón que sólo se llama "Tema" no le dice nada a quien no ve el icono. El
 * icono es decorativo y el texto visible sólo aparece en la versión ancha.
 */
import { computed } from 'vue'
import { Sun, Moon, MonitorCog } from 'lucide-vue-next'
import { useTheme, type PreferenciaTema } from '@/composables/useTheme'

const { preferencia, esOscuro, establecerTema } = useTheme()

const CICLO: PreferenciaTema[] = ['sistema', 'claro', 'oscuro']

const ROTULOS: Record<PreferenciaTema, string> = {
  sistema: 'Según el sistema',
  claro: 'Tema claro',
  oscuro: 'Tema oscuro',
}

const siguiente = computed<PreferenciaTema>(() => {
  /* Si el valor guardado no estuviera en el ciclo, `indexOf` devuelve -1 y el
     indice se saldria del arreglo. Se vuelve a 'sistema', que es el valor por
     defecto del composable. */
  const i = CICLO.indexOf(preferencia.value)
  if (i < 0) return 'sistema'
  return CICLO[(i + 1) % CICLO.length] ?? 'sistema'
})

/* El icono refleja lo que se ESTÁ VIENDO, no la preferencia: en modo
   'sistema' lo útil es saber si ahora mismo está claro u oscuro. */
const icono = computed(() => {
  if (preferencia.value === 'sistema') return MonitorCog
  return esOscuro.value ? Moon : Sun
})

const etiqueta = computed(
  () => `${ROTULOS[preferencia.value]}. Pulsa para cambiar a: ${ROTULOS[siguiente.value].toLowerCase()}`,
)
</script>

<template>
  <button
    type="button"
    class="foco-dentro inline-flex items-center gap-2 rounded-lg p-2 text-texto-secundario transition-colors duration-200 hover:bg-superficie-interactiva hover:text-texto-primario"
    :aria-label="etiqueta"
    :title="etiqueta"
    @click="establecerTema(siguiente)"
  >
    <component
      :is="icono"
      class="size-5 shrink-0"
      aria-hidden="true"
    />
    <!-- El estado también en texto: un icono solo es ambiguo, y en modo
         'sistema' no hay icono que explique por sí mismo qué significa. -->
    <span class="hidden text-sm font-medium lg:inline">{{ ROTULOS[preferencia] }}</span>
  </button>
</template>
