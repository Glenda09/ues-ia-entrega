<template>
  <div>
    <Encabezado contexto="ADM-02" titulo="Mis solicitudes de admisión" descripcion="Consulta el estado de tus solicitudes, observaciones y pagos.">
      <RouterLink to="/admision"><Button outlined label="Ver convocatorias" icon="pi pi-list" /></RouterLink>
    </Encabezado>
    <EstadoVista :cargando="cargando" :error="error" :vacio="!lista.length" textoVacio="Aún no has enviado ninguna solicitud." :reintentar="cargar">
      <article v-for="s in lista" :key="s.folio" class="mb-4 rounded-xl border border-secondary-100 bg-surface-card p-4">
        <h2 class="text-lg font-semibold">Folio {{ s.folio }} <EstadoTag :valor="s.estado" /></h2>
        <p class="text-sm text-secondary">{{ s.carrera?.nombre ?? s.carrera }} · {{ s.convocatoria?.titulo_publico ?? s.convocatoria }}</p>
        <p v-if="s.observacion" class="mt-2 rounded bg-amber-50 p-2 text-sm text-amber-900">Observación: {{ s.observacion }}</p>
        <div v-if="s.requisitos_pendientes?.length" class="mt-2 text-sm"><p class="font-medium">Requisitos pendientes</p><ul class="list-disc pl-5"><li v-for="(r, i) in s.requisitos_pendientes" :key="i">{{ r.nombre }}</li></ul></div>
        <p v-if="s.pago" class="mt-2 text-sm">Pago {{ s.pago.folio }}: ${{ s.pago.monto }} · <EstadoTag :valor="s.pago.estado" /><span v-if="s.pago.observacion" class="text-secondary"> — {{ s.pago.observacion }}</span></p>
      </article>
    </EstadoVista>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { Button } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import { api, mensajeError } from '../services/academico'

const lista = ref([])
const cargando = ref(true)
const error = ref('')

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    lista.value = (await api.get('/academico/mis-solicitudes')).data
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)
</script>
