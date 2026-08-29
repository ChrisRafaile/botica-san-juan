// Logger simple para navegador - sin dependencias de Node.js
const isDevelopment = import.meta.env.DEV

// Función helper para formatear mensajes
const formatMessage = (level: string, message: string): string => {
  const timestamp = new Date().toISOString()
  const emoji = {
    info: '🔵',
    error: '🔴',
    warn: '🟡',
    debug: '🟣'
  }[level] || '📝'

  return `${emoji} ${timestamp} [${level.toUpperCase()}]: ${message}`
}

// Logger compatible con navegador
export const browserLogger = {
  info: (message: string, meta?: unknown) => {
    const formattedMessage = formatMessage('info', message)
    console.log(formattedMessage, meta || '')

    // En desarrollo, también enviar a un endpoint de logging si es necesario
    if (isDevelopment && meta) {
      // Aquí podríamos enviar logs a un servicio externo si fuera necesario
    }
  },

  error: (message: string, meta?: unknown) => {
    const formattedMessage = formatMessage('error', message)
    console.error(formattedMessage, meta || '')

    // En desarrollo, también enviar a un endpoint de logging si es necesario
    if (isDevelopment && meta) {
      // Aquí podríamos enviar logs a un servicio externo si fuera necesario
    }
  },

  warn: (message: string, meta?: unknown) => {
    const formattedMessage = formatMessage('warn', message)
    console.warn(formattedMessage, meta || '')

    // En desarrollo, también enviar a un endpoint de logging si es necesario
    if (isDevelopment && meta) {
      // Aquí podríamos enviar logs a un servicio externo si fuera necesario
    }
  },

  debug: (message: string, meta?: unknown) => {
    if (isDevelopment) {
      const formattedMessage = formatMessage('debug', message)
      console.debug(formattedMessage, meta || '')
    }
    // En producción, ignorar logs de debug
  }
}

export default browserLogger