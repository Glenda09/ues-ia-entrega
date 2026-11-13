<template>
  <div>
    <Encabezado contexto="Portal docente" :titulo="grupo ? `${grupo.materia?.codigo} · ${grupo.materia?.nombre}` : 'Grupo'" :descripcion="grupo ? `Grupo ${grupo.codigo} · ${grupo.ciclo?.nombre ?? ''} · ${grupo.carrera?.nombre ?? ''}` : ''">
      <RouterLink to="/docente/grupos"><Button label="Volver" text icon="pi pi-arrow-left" tabindex="-1" /></RouterLink>
      <RouterLink :to="`/docente/grupos/${id}/notas`"><Button label="Notas" icon="pi pi-pencil" tabindex="-1" /></RouterLink>
    </Encabezado>
    <EstadoVista :cargando="cargando" :error="error" :vacio="!grupo" :reintentar="cargar">
      <section v-if="grupo" aria-labelledby="t-det" class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <h2 id="t-det" class="sr-only">Detalle del grupo</h2>
        <div class="rounded-xl border border-secondary-100 bg-surface-card p-4"><p class="text-xs text-secondary">Inscritos / cupo</p><p class="font-semibold">{{ grupo.inscritos ?? 0 }} / {{ grupo.cupo ?? '—' }}</p></div>
        <div class="rounded-xl border border-secondary-100 bg-surface-card p-4"><p class="text-xs text-secondary">Docente</p><p class="font-semibold">{{ grupo.docente?.nombre_academico ?? '—' }}</p></div>
        <div class="rounded-xl border border-secondary-100 bg-surface-card p-4"><p class="text-xs text-secondary">Período de notas</p><EstadoTag v-if="grupo.ventana_notas?.estado" :valor="grupo.ventana_notas.estado" /><span v-else>—</span></div>
        <div class="rounded-xl border border-secondary-100 bg-surface-card p-4"><p class="text-xs text-secondary">Acta</p><EstadoTag v-if="grupo.acta?.estado" :valor="grupo.acta.estado" /><span v-else>—</span></div>
        <div class="rounded-xl border border-secondary-100 bg-surface-card p-4 sm:col-span-2 lg:col-span-4">
          <p class="mb-1 text-xs text-secondary">Horarios</p>
          <p v-if="!grupo.horarios?.length">Sin horarios registrados.</p>
          <ul v-else class="list-disc pl-5 text-sm"><li v-for="(h, i) in grupo.horarios" :key="i">{{ DIAS[h.dia] ?? h.dia }} {{ String(h.hora_inicio).slice(0, 5) }}–{{ String(h.hora_fin).slice(0, 5) }}</li></ul>
        </div>
      </section>
    </EstadoVista>

    <section aria-labelledby="t-pad">
      <h2 id="t-pad" class="mb-3 text-lg font-semibold">Padrón de estudiantes</h2>
      <TablaPaginada :url="`/academico/mis-grupos/${id}/estudiantes`" clave="id_inscripcion" textoVacio="No hay estudiantes inscritos." :columnas="[{ campo: 'carnet', titulo: 'Carnet' }, { campo: 'nombre', titulo: 'Nombre' }, { campo: 'estado', titulo: 'Estado' }]">
        <template #celda-estado="{ fila }"><EstadoTag :valor="fila.estado ?? ''" /></template>
      </TablaPaginada>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Button } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import TablaPaginada from '../components/TablaPaginada.vue'
import { api, mensajeError } from '../services/academico'

const DIAS = { 1: 'Lunes', 2: 'Martes', 3: 'Miércoles', 4: 'Jueves', 5: 'Viernes', 6: 'Sábado', 7: 'Domingo' }
const route = useRoute()
const id = computed(() => route.params.grupo)
const grupo = ref(null)
const cargando = ref(true)
const error = ref('')

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    grupo.value = (await api.get(`/academico/mis-grupos/${id.value}`)).data
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)
</script>
