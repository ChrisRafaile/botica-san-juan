import './styles/global.css'
import './styles/client.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'

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
  app.mount('#app')
})
