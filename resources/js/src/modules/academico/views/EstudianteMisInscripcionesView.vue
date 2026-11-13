<template>
  <div>
    <Encabezado contexto="Portal del estudiante" titulo="Mis inscripciones" descripcion="Consulta tus inscripciones y retira materias mientras el período lo permita.">
      <SelectorExpediente />
    </Encabezado>
    <p v-if="mensaje" role="status" class="mb-3 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-900">{{ mensaje }}</p>
    <TablaPaginada v-if="store.idExpediente" ref="tabla" url="/academico/mis-inscripciones" clave="id_inscripcion" :filtros="filtros" textoVacio="No tienes inscripciones registradas." :columnas="[{ campo: 'materia.codigo', titulo: 'Código' }, { campo: 'materia.nombre', titulo: 'Materia' }, { campo: 'grupo.codigo', titulo: 'Grupo' }, { campo: 'estado', titulo: 'Estado' }, { campo: 'inscrito_en', titulo: 'Inscrito' }, { campo: 'eventos', titulo: 'Eventos' }]">
      <template #celda-estado="{ fila }"><EstadoTag :valor="fila.estado" /></template>
      <template #celda-inscrito_en="{ fila }">{{ fecha(fila.inscrito_en) }}</template>
      <template #celda-eventos="{ fila }">{{ Array.isArray(fila.eventos) ? fila.eventos.length : (fila.eventos ?? '—') }}</template>
      <template #acciones="{ fila }">
        <Button v-if="fila.estado === 'activa'" text size="small" severity="danger" label="Retirar" @click="abrir(fila)" />
      </template>
    </TablaPaginada>
    <p v-else class="text-secondary">No hay un expediente seleccionado.</p>

    <Dialog v-model:visible="dialogo" modal header="Retirar materia" class="w-full max-w-lg">
      <form class="space-y-4" @submit.prevent="retirar">
        <p v-if="objetivo" class="text-sm">Vas a retirar <strong>{{ objetivo.materia?.codigo }} · {{ objetivo.materia?.nombre }}</strong> (grupo {{ objetivo.grupo?.codigo }}).</p>
        <div>
          <label for="motivo" class="mb-1 block text-sm font-medium">Motivo del retiro</label>
          <Textarea id="motivo" v-model="motivo" rows="3" class="w-full" />
        </div>
        <p v-if="errorForm" role="alert" class="text-sm text-red-600">{{ errorForm }}</p>
        <div class="flex justify-end gap-2">
          <Button type="button" text label="Cancelar" @click="dialogo = false" />
          <Button type="submit" label="Confirmar retiro" severity="danger" :loading="ocupado" :disabled="!motivo.trim()" />
        </div>
      </form>
    </Dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Button, Dialog, Textarea } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoTag from '../components/EstadoTag.vue'
import SelectorExpediente from '../components/SelectorExpediente.vue'
import TablaPaginada from '../components/TablaPaginada.vue'
import { useExpedienteSeleccionado } from '../stores/expedienteSeleccionado'
import { api, fecha, mensajeError } from '../services/academico'

const route = useRoute()
const store = useExpedienteSeleccionado()
const tabla = ref()
const filtros = reactive({ expediente: store.idExpediente, ciclo: route.query.ciclo ?? '' })
const dialogo = ref(false)
const objetivo = ref(null)
const motivo = ref('')
const ocupado = ref(false)
const errorForm = ref('')
const mensaje = ref('')

function abrir(fila) {
  objetivo.value = fila
  motivo.value = ''
  errorForm.value = ''
  dialogo.value = true
}

async function retirar() {
  ocupado.value = true
  errorForm.value = ''
  try {
    const r = await api.post(`/academico/mis-inscripciones/${objetivo.value.id_inscripcion}/retiros`, { motivo: motivo.value.trim() })
    mensaje.value = r.message || 'La materia fue retirada.'
    dialogo.value = false
    tabla.value?.cargar()
  } catch (e) {
    errorForm.value = mensajeError(e)
  } finally {
    ocupado.value = false
  }
}

watch(() => store.idExpediente, (v) => { filtros.expediente = v })
onMounted(async () => {
  if (!store.perfil) {
    try { await store.cargarPerfil(route.query.expediente) } catch { /* se muestra sin expediente */ }
  }
  filtros.expediente = store.idExpediente
})
</script>
