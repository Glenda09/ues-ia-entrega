<template>
  <div class="card">
    <h3>Ejemplo: Descarga de archivo por stream</h3>
    <div class="row">
      <InputText v-model="nombrePreferido" placeholder="Nombre preferido (opcional)" />
      <Button type="button" :disabled="loading" @click="run" class="text-brand-contrast"
        >Iniciar descarga</Button
      >
      <Button type="button" :disabled="!loading" @click="cancel" class="text-brand-contrast"
        >Cancelar</Button
      >
    </div>

    <div class="info">
      <div>
        <p>Progreso</p>
        <progress :value="progress" max="100"></progress>
        <span>{{ displayProgress }}</span>
      </div>
      <div><strong>Bytes:</strong> {{ bytesReceived }} / {{ totalBytes || '?' }}</div>
      <div><strong>Content-Type:</strong> {{ contentType }}</div>
      <div v-if="responseHeaders && responseHeaders['content-disposition']">
        <strong>Content-Disposition:</strong> {{ responseHeaders['content-disposition'] }}
      </div>
    </div>

    <div class="actions">
      <Button type="button" :disabled="!blob" @click="download" class="text-brand-contrast"
        >Descargar archivo</Button
      >
    </div>

    <p v-if="error" class="text-danger">Error: {{ error.message || String(error) }}</p>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useStreaming } from '@/shared/composables/useStreaming'

import { InputText, Button } from 'primevue'

const {
  start,
  cancel,
  loading,
  error,
  progress,
  bytesReceived,
  totalBytes,
  contentType,
  blob,
  responseHeaders,
  triggerDownload
} = useStreaming()

const nombrePreferido = ref('')

const displayProgress = computed(() =>
  totalBytes.value > 0
    ? `${progress.value}%`
    : bytesReceived.value > 0
    ? `${bytesReceived.value} bytes`
    : '—'
)

const guessExtensionFromContentType = (type) => {
  if (!type) return undefined
  if (type.includes('pdf')) return 'pdf'
  if (type.includes('csv')) return 'csv'
  if (type.includes('json')) return 'json'
  if (type.includes('zip')) return 'zip'
  return undefined
}

async function run() {
  await start('/api/stream/file', {
    method: 'GET',
    as: 'blob'
  })
}

function download() {
  // Si el servidor envió Content-Disposition, se usará para nombrar el archivo.
  // De lo contrario, se usa el nombre preferido + content-type (o sin extensión).
  const nombre = nombrePreferido.value || undefined
  const extension = guessExtensionFromContentType(contentType.value)
  triggerDownload({ nombre, extension })
}
</script>

<style scoped>
.card {
  border: 1px solid var(--color-secondary-100);
  border-radius: 4px;
  padding: 16px;
  margin-bottom: 16px;
}
.row {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}
input {
  padding: 6px 8px;
  border: 1px solid var(--color-secondary-100);
  border-radius: 4px;
  min-width: 240px;
}
.info {
  display: grid;
  gap: 6px;
  margin-top: 8px;
}
.actions {
  margin-top: 12px;
}
</style>
