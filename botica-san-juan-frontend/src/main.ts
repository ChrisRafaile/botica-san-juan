import './styles/global.css'
import './styles/client.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { iniciarObservabilidad } from './observabilidad'

const app = createApp(App)

app.use(createPinia())

// La sesion se restaura ANTES de instalar el router. Vue Router 4 inicia la
// primera navegacion -y por tanto ejecuta router.beforeEach- en el momento de
// app.use(router), no en app.mount(). Si el guard corre antes de que
// checkAuth() resuelva, una recarga sobre /admin redirige a /login aunque el
// token de sesion siga siendo valido.
const authStore = useAuthStore()
authStore.checkAuth().finally(() => {
  app.use(router)

  /* La observabilidad se inicia DESPUÉS del router y antes de montar.
     Después del router porque PostHog se engancha a `afterEach` para mandar
     la vista de página con la URL ya limpia; antes de montar para que un
     error durante el primer render tenga a Sentry escuchando.

     No se espera a que termine: son dos importaciones dinámicas contra
     servidores de terceros, y la aplicación no puede quedarse en blanco
     porque uno de ellos tarde. Si fallan, no se cargan y ya está. */
  void iniciarObservabilidad(app, router)

  app.mount('#app')
})
