<template>
  <div class="card">
    <h3>Ejemplo: Streaming de texto (JSON por líneas)</h3>
    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
      <Button type="button" :disabled="loading" @click="run" class="text-brand-contrast"
        >Iniciar stream</Button
      >
      <Button type="button" :disabled="!loading" @click="cancel" class="text-brand-contrast"
        >Cancelar</Button
      >
      <span v-if="loading">Recibiendo… {{ progress > 0 ? progress + '%' : '' }}</span>
    </div>
    <p v-if="error" class="text-danger">Error: {{ error.message || String(error) }}</p>

    <h4>Resultado unificado</h4>
    <pre class="output">{{ combined }}</pre>

    <details>
      <summary>Ver crudo recibido</summary>
      <pre class="output">{{ text }}</pre>
    </details>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useStreaming, drainBufferObjects } from '@/shared/composables/useStreaming'

import { Button } from 'primevue'

const { start, cancel, loading, error, text, progress } = useStreaming()

// Acumula los mensajes `message` de cada objeto JSON recibido
const combined = ref('')
let buffer = ''

function handleChunk(chunk) {
  buffer += chunk
  const { objects, buffer: remaining } = drainBufferObjects(buffer)
  buffer = remaining

  for (const obj of objects) {
    if (obj && typeof obj.message === 'string') combined.value += obj.message
  }
}

async function run() {
  combined.value = ''
  buffer = ''
  await start('/api/stream/text', {
    method: 'GET',
    as: 'text',
    onChunk: handleChunk
  })
}
</script>

<style scoped>
.card {
  border: 1px solid var(--color-secondary-100);
  border-radius: 4px;
  padding: 16px;
  margin-bottom: 16px;
}
.output {
  white-space: pre-wrap;
  background: var(--color-surface-card);
  border: 1px solid var(--color-secondary-100);
  color: var(--color-text);
  padding: 8px;
  border-radius: 4px;
  max-height: 240px;
  overflow: auto;
}
</style>
