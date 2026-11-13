<template>
  <div class="pdf-viewer" :style="containerStyle" v-if="pdfUrl">
    <iframe
      :src="pdfUrl"
      :title="title || 'PDF Viewer'"
      referrerpolicy="no-referrer"
      class="pdf-frame"
    />
  </div>
  <div v-else class="pdf-fallback">
    <slot>No se proporcionó una fuente de PDF.</slot>
  </div>
  
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
  // Puede ser una URL, File o Blob
  src: {
    type: [String, Object],
    default: ''
  },
  // Altura del contenedor: número (px) o string (e.g., '70vh')
  height: {
    type: [Number, String],
    default: '70vh'
  },
  // Ancho del contenedor
  width: {
    type: [Number, String],
    default: '100%'
  },
  // Título para accesibilidad
  title: {
    type: String,
    default: 'Visor PDF'
  }
})

const objectUrl = ref(null)

const normalizedSize = (val, fallback) => {
  if (val === undefined || val === null || val === '') return fallback
  return typeof val === 'number' ? `${val}px` : String(val)
}

const containerStyle = computed(() => ({
  height: normalizedSize(props.height, '70vh'),
  width: normalizedSize(props.width, '100%')
}))

const computeUrl = (value) => {
  if (!value) return ''
  // Si es File o Blob, crear un ObjectURL
  if (typeof value === 'object' && (value instanceof Blob)) {
    // Revocar anterior si existía
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
    objectUrl.value = URL.createObjectURL(value)
    return objectUrl.value
  }
  // Si es string, devolver tal cual
  if (typeof value === 'string') return value
  return ''
}

const pdfUrl = computed(() => computeUrl(props.src))

watch(() => props.src, (next) => {
  // Si cambiamos de Blob a otro, gestionar ObjectURL
  if (objectUrl.value && (typeof next !== 'object' || !(next instanceof Blob))) {
    URL.revokeObjectURL(objectUrl.value)
    objectUrl.value = null
  }
})

onBeforeUnmount(() => {
  if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
})
</script>

<style scoped>
.pdf-viewer {
  position: relative;
  display: block;
}
.pdf-frame {
  border: 0;
  width: 100%;
  height: 100%;
}
.pdf-fallback {
  padding: 12px;
  border: 1px dashed #ddd;
  border-radius: 6px;
  color: #666;
}
</style>

