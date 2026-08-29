<template>
  <div class="photo-capture-container">
    <!-- Camera View -->
    <div
      v-show="isCapturing"
      class="camera-view"
    >
      <div class="mb-2 text-center text-sm text-gray-600">
        Cámara activa - Estado: {{ canCapture ? 'Listo para capturar' : 'Cargando...' }}
      </div>
      <video
        ref="videoElement"
        autoplay
        playsinline
        muted
        style="width: 100%; height: 300px; object-fit: cover; background: #000;"
        class="camera-video"
        @loadedmetadata="onVideoLoaded"
      ></video>
      <canvas
        ref="canvasElement"
        class="hidden"
      ></canvas>

      <!-- Camera Controls -->
      <div class="camera-controls">
        <button
          class="capture-btn"
          :disabled="!canCapture"
          @click="capturePhoto"
        >
          <Camera class="w-8 h-8" />
        </button>
        <button
          class="stop-btn"
          @click="stopCapture"
        >
          <X class="w-6 h-6" />
        </button>
      </div>
    </div>

    <!-- Photo Preview -->
    <div
      v-if="capturedPhoto"
      class="photo-preview"
    >
      <img
        :src="capturedPhoto"
        alt="Captured photo"
        class="preview-image"
      />

      <div class="preview-controls">
        <button
          class="retake-btn"
          @click="retakePhoto"
        >
          <RotateCcw class="w-4 h-4 mr-2" />
          Retomar
        </button>
        <button
          class="confirm-btn"
          @click="confirmPhoto"
        >
          <Check class="w-4 h-4 mr-2" />
          Confirmar
        </button>
      </div>
    </div>

    <!-- Camera View -->
    <div
      v-else-if="isCapturing"
      class="camera-view"
    >
      <div class="mb-2 text-center text-sm text-gray-600">
        Cámara activa - Estado: {{ canCapture ? 'Listo para capturar' : 'Cargando...' }}
      </div>
      <video
        ref="videoElement"
        autoplay
        playsinline
        muted
        style="width: 100%; height: 300px; object-fit: cover; background: #000;"
        class="camera-video"
        @loadedmetadata="onVideoLoaded"
      ></video>

      <div class="mt-4 flex gap-2 justify-center">
        <button
          :disabled="!canCapture"
          class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50"
          @click="capturePhoto"
        >
          Capturar Foto
        </button>
        <button
          class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600"
          @click="stopCapture"
        >
          Cancelar
        </button>
      </div>
    </div>

    <!-- Start Capture Button -->
    <div
      v-else
      class="start-capture"
    >
      <div class="mb-4 text-center">
        <p class="text-sm text-gray-600 mb-2">
          Estado de la cámara: {{ hasCamera ? 'Disponible' : 'No disponible' }}
        </p>
        <p
          v-if="error"
          class="text-sm text-red-600 mb-2"
        >
          {{ error }}
        </p>
      </div>
      <button
        class="start-btn"
        :disabled="!hasCamera"
        @click="startCapture"
      >
        <Camera class="w-6 h-6 mr-2" />
        {{ hasCamera ? 'Tomar Foto' : 'Cámara no disponible' }}
      </button>
    </div>

    <!-- Error Message -->
    <div
      v-if="error"
      class="error-message"
    >
      <AlertTriangle class="w-5 h-5 text-red-500 mr-2" />
      <span>{{ error }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import { Camera, X, RotateCcw, Check, AlertTriangle } from 'lucide-vue-next'

// Props
interface Props {
  width?: number
  height?: number
  quality?: number
  autoStart?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  width: 640,
  height: 480,
  quality: 0.8,
  autoStart: false
})

// Emits
const emit = defineEmits<{
  photoCaptured: [photoData: string]
  error: [error: string]
}>()

// Refs
const videoElement = ref<HTMLVideoElement>()
const canvasElement = ref<HTMLCanvasElement>()

// State
const isCapturing = ref(false)
const capturedPhoto = ref<string>('')
const canCapture = ref(false)
const hasCamera = ref(false)
const error = ref<string>('')
const stream = ref<MediaStream | null>(null)

// Check camera availability
const checkCameraAvailability = async () => {
  try {
    // Check if we have permission to access camera
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      hasCamera.value = false
      error.value = 'La API de medios no está disponible en este navegador'
      return
    }

    // Check if we're in a secure context (HTTPS or localhost/local IP)
    const hostname = window.location.hostname
    const isSecure = window.location.protocol === 'https:' ||
                    hostname === 'localhost' ||
                    hostname === '127.0.0.1' ||
                    hostname.startsWith('192.168.') ||
                    hostname.startsWith('10.') ||
                    hostname.startsWith('172.')

    console.log('Secure context check:', { protocol: window.location.protocol, hostname, isSecure })

    if (!isSecure) {
      hasCamera.value = false
      error.value = 'Se requiere HTTPS o una conexión local segura para acceder a la cámara.'
      return
    }

    const devices = await navigator.mediaDevices.enumerateDevices()
    const videoDevices = devices.filter(device => device.kind === 'videoinput')
    hasCamera.value = videoDevices.length > 0

    if (!hasCamera.value) {
      error.value = 'No se encontraron cámaras disponibles en este dispositivo'
    }
  } catch (err) {
    console.error('Error checking camera availability:', err)
    hasCamera.value = false
    const errorMessage = err instanceof Error ? err.message : 'Error desconocido'
    error.value = 'Error al verificar disponibilidad de cámara: ' + errorMessage
  }
}

// Start camera capture
const startCapture = async () => {
  try {
    error.value = ''

    // Try different constraints in order of preference
    const constraintOptions = [
      {
        video: {
          width: { ideal: props.width },
          height: { ideal: props.height },
          facingMode: 'environment' // Back camera
        }
      },
      {
        video: {
          width: { ideal: props.width },
          height: { ideal: props.height },
          facingMode: 'user' // Front camera
        }
      },
      {
        video: {
          width: { ideal: props.width },
          height: { ideal: props.height }
        }
      },
      {
        video: true // Basic video
      }
    ]

    let streamObtained = false
    let lastError = null

    for (const constraints of constraintOptions) {
      try {
        stream.value = await navigator.mediaDevices.getUserMedia(constraints)
        console.log('Camera access granted with constraints:', constraints)
        streamObtained = true
        break
      } catch (err) {
        console.log('Failed with constraints:', constraints, err)
        lastError = err
      }
    }

    if (!streamObtained) {
      throw lastError || new Error('No se pudo acceder a la cámara con ninguna configuración')
    }

    console.log('Camera access granted, setting up video element')
    isCapturing.value = true

    // Wait for the next DOM update to ensure video element is rendered
    await nextTick()

    // Set video srcObject immediately since element should be available now
    if (videoElement.value) {
      console.log('Setting video srcObject')
      videoElement.value.srcObject = stream.value
      console.log('Camera capture started successfully')
    } else {
      console.error('Video element still not available after nextTick')
      error.value = 'Elemento de video no encontrado'
      stopCapture()
    }
  } catch (err: unknown) {
    console.error('Error starting camera:', err)
    isCapturing.value = false

    let errorMessage = 'Error al acceder a la cámara'

    if (err instanceof Error) {
      if (err.name === 'NotAllowedError') {
        errorMessage = 'Permiso denegado. Permite el acceso a la cámara en tu navegador.'
      } else if (err.name === 'NotFoundError') {
        errorMessage = 'No se encontró ninguna cámara en este dispositivo.'
      } else if (err.name === 'NotReadableError') {
        errorMessage = 'La cámara está siendo usada por otra aplicación.'
      } else if (err.name === 'OverconstrainedError') {
        errorMessage = 'La configuración de cámara solicitada no está disponible.'
      } else if (err.name === 'SecurityError') {
        errorMessage = 'Se requiere un contexto seguro (HTTPS) para acceder a la cámara.'
      } else {
        errorMessage = err.message
      }
    }

    error.value = errorMessage
    emit('error', error.value)
  }
}

// Stop camera capture
const stopCapture = () => {
  if (stream.value) {
    stream.value.getTracks().forEach(track => track.stop())
    stream.value = null
  }

  if (videoElement.value) {
    videoElement.value.srcObject = null
  }

  isCapturing.value = false
  canCapture.value = false
}

// Handle video loaded
const onVideoLoaded = async () => {
  console.log('Video loaded, dimensions:', videoElement.value?.videoWidth, 'x', videoElement.value?.videoHeight)
  canCapture.value = true

  // Try to play the video explicitly (some browsers require this)
  if (videoElement.value) {
    try {
      await videoElement.value.play()
      console.log('Video playback started successfully')
    } catch (err) {
      console.error('Error playing video:', err)
    }
  }
}

// Capture photo
const capturePhoto = () => {
  if (!videoElement.value || !canvasElement.value || !canCapture.value) return

  const video = videoElement.value
  const canvas = canvasElement.value
  const context = canvas.getContext('2d')

  if (!context) return

  // Set canvas size to video size
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight

  // Draw video frame to canvas
  context.drawImage(video, 0, 0, canvas.width, canvas.height)

  // Convert to data URL
  capturedPhoto.value = canvas.toDataURL('image/jpeg', props.quality)

  // Stop camera
  stopCapture()
}

// Retake photo
const retakePhoto = () => {
  capturedPhoto.value = ''
  startCapture()
}

// Confirm photo
const confirmPhoto = () => {
  emit('photoCaptured', capturedPhoto.value)
  capturedPhoto.value = ''
}

// Lifecycle
onMounted(() => {
  console.log('PhotoCapture component mounted')
  checkCameraAvailability()
})

onUnmounted(() => {
  stopCapture()
})

// Watchers
// Removed videoElement watcher as it's no longer needed with v-else-if structure

// Expose methods
defineExpose({
  startCapture,
  stopCapture,
  hasCamera: computed(() => hasCamera.value)
})
</script>

<style scoped>
.photo-capture-container {
  position: relative;
  background-color: rgb(243 244 246);
  border-radius: 0.5rem;
  overflow: hidden;
  min-height: 300px;
}

.camera-view {
  position: relative;
}

.camera-video {
  width: 100%;
  height: auto;
  max-height: 400px;
}

.camera-controls {
  position: absolute;
  bottom: 1rem;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 1rem;
}

.capture-btn {
  background-color: white;
  border-radius: 9999px;
  padding: 1rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  transition: background-color 0.15s ease-in-out;
}

.capture-btn:hover {
  background-color: rgb(249 250 251);
}

.capture-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.stop-btn {
  background-color: rgb(239 68 68);
  color: white;
  border-radius: 9999px;
  padding: 0.75rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  transition: background-color 0.15s ease-in-out;
}

.stop-btn:hover {
  background-color: rgb(220 38 38);
}

.photo-preview {
  padding: 1rem;
}

.preview-image {
  width: 100%;
  height: auto;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.05);
  max-height: 500px;
}

.preview-controls {
  margin-top: 1rem;
  display: flex;
  justify-content: center;
  gap: 1rem;
}

.retake-btn {
  background-color: rgb(107 114 128);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  transition: background-color 0.15s ease-in-out;
  display: flex;
  align-items: center;
}

.retake-btn:hover {
  background-color: rgb(75 85 99);
}

.confirm-btn {
  background-color: rgb(59 130 246);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  transition: background-color 0.15s ease-in-out;
  display: flex;
  align-items: center;
}

.confirm-btn:hover {
  background-color: rgb(37 99 235);
}

.start-capture {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 16rem;
}

.start-btn {
  background-color: rgb(59 130 246);
  color: white;
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  transition: background-color 0.15s ease-in-out;
  display: flex;
  align-items: center;
  font-weight: 500;
}

.start-btn:hover {
  background-color: rgb(37 99 235);
}

.start-btn:disabled {
  background-color: rgb(156 163 175);
  cursor: not-allowed;
}

.error-message {
  position: absolute;
  bottom: 1rem;
  left: 1rem;
  right: 1rem;
  background-color: rgb(254 202 202);
  color: rgb(185 28 28);
  padding: 1rem;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
}
</style>