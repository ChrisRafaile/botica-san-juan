/* Punto de entrada para estilos - importa según el rol del usuario */

/* Estilos globales - siempre se importan */
import './global.css'

/* Función para cargar estilos según el rol */
export const loadStylesByRole = (role: 'client' | 'admin') => {
  // Verificar que el DOM esté listo
  if (typeof document === 'undefined') {
    console.warn('DOM not ready, skipping style loading')
    return
  }

  // Remover estilos previos si existen
  const existingClientStyle = document.getElementById('client-styles')
  const existingAdminStyle = document.getElementById('admin-styles')

  if (existingClientStyle) existingClientStyle.remove()
  if (existingAdminStyle) existingAdminStyle.remove()

  // Cargar estilos según el rol
  if (role === 'client') {
    import('./client.css').then(() => {
      console.log('Estilos de cliente cargados')
    }).catch(error => {
      console.error('Error loading client styles:', error)
    })
  } else if (role === 'admin') {
    import('./admin.css').then(() => {
      console.log('Estilos de administrador cargados')
    }).catch(error => {
      console.error('Error loading admin styles:', error)
    })
  }
}

/* Por defecto, cargar estilos de cliente para vistas públicas después de que el DOM esté listo */
if (typeof document !== 'undefined') {
  // Esperar a que el DOM esté listo
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      loadStylesByRole('client')
    })
  } else {
    // DOM ya está listo
    loadStylesByRole('client')
  }
}