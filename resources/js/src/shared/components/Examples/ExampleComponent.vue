<template>
  <h1>{{ msg }}</h1>

  <div class="card">
    <h3>Ejemplo Store</h3>
    <Button type="Button" @click="increment" class="text-brand-contrast">count is {{ count }}</Button>
    <p>double is {{ double }}</p>
  </div>

  <div class="card">
    <h3>Ejemplo Day.js</h3>
    <p>Ahora: {{ formattedNow }}</p>
    <p>Desde 1 de enero de 2020: {{ fromNewYear2020 }}</p>
  </div>

  <div class="card">
    <h3>Ejemplo vue3-toastify</h3>
    <Button type="Button" @click="showToast" class="text-brand-contrast">Mostrar Toast</Button>
  </div>

  <div class="card">
    <h3>Ejemplo debounce en input</h3>
    <label for="debounce-input">
      Buscar:
      <InputText
        id="debounce-input"
        v-model="debounce.input.value"
        placeholder="Escribe para probar debounce"
      />
    </label>
    <p>Valor inmediato: "{{ debounce.input }}"</p>
    <p>Valor con debounce (500ms): "{{ debounce.debounced }}"</p>
    <p v-if="debounce.isDebouncing.value">Aplicando debounce…</p>
  </div>

  <div class="card">
    <h3>Ejemplo useApi (GET)</h3>
    <Button type="Button" @click="fetchTodos" :disabled="apiLoading" class="text-brand-contrast">
      {{ apiLoading ? 'Cargando…' : 'Cargar TODOs' }}
    </Button>
    <p v-if="apiError" class="text-danger">Error: {{ apiError.message || apiError }}</p>
    <ul v-if="todos.length">
      <li v-for="t in todos" :key="t.id">{{ t.title }}</li>
    </ul>
  </div>

  <div class="card">
    <h3>Ejemplo PdfViewer (incrustado)</h3>
    <PdfViewer :src="testPdf" height="400px" title="PDF de prueba" />
  </div>

  <div class="card">
    <h3>Ejemplo descarga de PDF</h3>
    <div style="display: flex; gap: 8px; flex-wrap: wrap">
      <Button type="Button" @click="descargarPdfPorHeader" class="text-brand-contrast"
        >Descargar (usar header/uuid)</Button
      >
      <Button type="Button" @click="descargarPdfConNombre" class="text-brand-contrast">
        Descargar (usar nombre preferido)
      </Button>
    </div>
  </div>

  <div class="card">
    <h3>Ejemplo descarga desde Blob</h3>
    <div style="display: flex; gap: 8px; flex-wrap: wrap">
      <Button type="Button" @click="descargarBlobTexto" class="text-brand-contrast"
        >Descargar Blob de texto</Button
      >
      <Button type="Button" @click="descargarBlobJson" class="text-brand-contrast"
        >Descargar Blob JSON</Button
      >
    </div>
    <small>Genera el contenido en el cliente y lo descarga.</small>
  </div>

  <StreamTextExample />

  <StreamFileExample />
</template>

<script setup>
import { inject, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useExampleStore } from '@/shared/stores/example'
import { toast } from 'vue3-toastify'
import { useDebounce } from '@/shared/composables/useDebounce'
import { useApi } from '@/shared/composables/useApi'
import PdfViewer from '@/shared/components/PdfViewer.vue'
import testPdf from '@/shared/assets/files/test.pdf?url'
import { downloadFromResponse, downloadBlob } from '@/shared/utils/download'
import StreamTextExample from '@/shared/components/Examples/StreamTextExample.vue'
import StreamFileExample from '@/shared/components/Examples/StreamFileExample.vue'

import { InputText, Button } from 'primevue'

defineProps({
  msg: String
})

const example = useExampleStore()
const { count, double } = storeToRefs(example)

function increment() {
  example.increment()
}

// Day.js (inyectado por el plugin global en main.js)
const dayjs = inject('dayjs')

const formattedNow = dayjs().format('LLLL')
const fromNewYear2020 = dayjs('2020-01-01').fromNow()

function showToast() {
  toast.success('¡Hola desde vue3-toastify!')
}

// Ejemplo de uso de useDebounce en un input
const debounce = useDebounce('', 500, (val) => {
  // Se ejecuta cuando termina el debounce
  console.log('Debounced value:', val)
})

// Ejemplo de uso de useApi
const { loading: apiLoading, error: apiError, get } = useApi()
const todos = ref([])

async function fetchTodos() {
  try {
    // Limitar a 5 resultados para el ejemplo
    todos.value = await get('https://jsonplaceholder.typicode.com/todos', { params: { _limit: 5 } })
  } catch (e) {
    // El error ya está disponible en apiError
    console.error('Error fetching todos:', e)
  }
}

// Exponer URL de ejemplo para el visor PDF
// (Vite procesa el asset y genera una URL válida)

// Descarga de PDF externo usando la utilidad
const PDF_URL = ''

async function descargarDesdeUrl(nombrePreferido) {
  if (!PDF_URL) {
    console.error('No hay URL de PDF disponible')
    toast.error('No hay URL de PDF disponible')
    return
  }

  try {
    const resp = await fetch(PDF_URL, { method: 'GET' })
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`)
    const blob = await resp.blob()
    const headers = {}
    resp.headers.forEach((v, k) => {
      headers[k] = v
    })
    downloadFromResponse({ data: blob, headers }, nombrePreferido)
  } catch (e) {
    console.error('Error al descargar PDF:', e)
    toast.error('No se pudo descargar el PDF de ejemplo')
  }
}

function descargarPdfPorHeader() {
  // Sin nombre preferido: usará filename del header o UUID
  descargarDesdeUrl()
}

function descargarPdfConNombre() {
  // Con nombre preferido (sin extensión); la utilidad agregará .pdf
  descargarDesdeUrl('pdf-ejemplo')
}

// Ejemplos: descarga de Blob generado en el cliente
function descargarBlobTexto() {
  const contenido = 'Hola desde un Blob en el cliente\nLinea 2'
  const blob = new Blob([contenido], { type: 'text/plain' })
  downloadBlob(blob, { nombre: 'blob-texto', extension: 'txt' })
}

function descargarBlobJson() {
  const data = { mensaje: 'Hola', fecha: new Date().toISOString() }
  // Podemos pasar el objeto directamente. La utilidad lo serializa a JSON si no es Blob
  downloadBlob(data, { nombre: 'blob-datos', extension: 'json', contentType: 'application/json' })
}
</script>

<style scoped>
.card {
  border: 1px solid var(--color-secondary-100);
  border-radius: 4px;
  padding: 16px;
  margin-bottom: 16px;
}
</style>
