<template>
  <div>
    <Encabezado contexto="NOT-01" :titulo="d ? `Acta ${d.grupo.materia?.codigo ?? ''} · grupo ${d.grupo.codigo}` : 'Acta'" :descripcion="d ? `${d.grupo.materia?.nombre ?? ''} — ${d.grupo.ciclo?.nombre ?? ''}` : ''">
      <RouterLink to="/academico/actas"><Button outlined label="Volver" icon="pi pi-arrow-left" /></RouterLink>
      <Button v-if="puedePublicar" label="Publicar acta" icon="pi pi-check" :loading="ocupado" @click="publicar" />
    </Encabezado>
    <p v-if="errorAccion" role="alert" class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ errorAccion }}</p>
    <EstadoVista :cargando="cargando" :error="error" :reintentar="cargar">
      <template v-if="d">
        <p class="mb-4 text-sm text-secondary">
          Estado del acta: <EstadoTag :valor="d.cuadricula.acta?.estado ?? ''" /> · Ventana de notas: {{ d.grupo.ventana_notas?.estado ?? d.grupo.ventana_notas ?? '—' }}
          <span v-if="d.resultados_publicados"> · Resultados publicados</span>
        </p>
        <section class="mb-8">
          <h2 class="mb-2 text-lg font-semibold">Cuadrícula de notas (solo lectura)</h2>
          <p v-if="!d.cuadricula.estudiantes?.length" class="text-secondary">No hay estudiantes inscritos.</p>
          <div v-else class="overflow-x-auto rounded-xl border border-secondary-100 bg-surface-card">
            <table class="w-full text-sm">
              <thead class="border-b border-secondary-100 text-left text-secondary">
                <tr><th scope="col" class="px-3 py-2">Carnet</th><th scope="col" class="px-3 py-2">Nombre</th><th v-for="(ev, i) in d.cuadricula.evaluaciones" :key="ev.id_evaluacion ?? i" scope="col" class="px-3 py-2">{{ ev.nombre ?? ev.codigo ?? `Eval. ${i + 1}` }}<span v-if="ev.ponderacion"> ({{ ev.ponderacion }}%)</span></th></tr>
              </thead>
              <tbody>
                <tr v-for="e in d.cuadricula.estudiantes" :key="e.carnet" class="border-b border-secondary-100 last:border-0">
                  <td class="px-3 py-2">{{ e.carnet }}</td><td class="px-3 py-2">{{ e.nombre }}</td>
                  <td v-for="(ev, i) in d.cuadricula.evaluaciones" :key="i" class="px-3 py-2">{{ celda(e.notas, ev, i) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
        <section>
          <h2 class="mb-2 text-lg font-semibold">Cálculo de notas finales</h2>
          <p v-if="!d.calculo" class="text-secondary">Aún no hay cálculo disponible.</p>
          <template v-else>
            <p class="mb-2 text-sm" :class="d.calculo.completo ? 'text-green-700' : 'text-amber-700'">{{ d.calculo.completo ? 'El cálculo está completo.' : `Cálculo incompleto: ${pendientes} nota(s) pendiente(s).` }}</p>
            <div class="overflow-x-auto rounded-xl border border-secondary-100 bg-surface-card">
              <table class="w-full text-sm">
                <thead class="border-b border-secondary-100 text-left text-secondary"><tr><th scope="col" class="px-3 py-2">Carnet</th><th scope="col" class="px-3 py-2">Nota final</th><th scope="col" class="px-3 py-2">Condición</th></tr></thead>
                <tbody><tr v-for="r in d.calculo.resultados" :key="r.id_inscripcion" class="border-b border-secondary-100 last:border-0"><td class="px-3 py-2">{{ r.carnet }}</td><td class="px-3 py-2">{{ r.nota_final ?? '—' }}</td><td class="px-3 py-2"><EstadoTag :valor="r.condicion ?? ''" /></td></tr></tbody>
              </table>
            </div>
          </template>
        </section>
      </template>
    </EstadoVista>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Button } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import { api, mensajeError } from '../services/academico'
import { useValidatePermission } from '@/shared/composables/useValidatePermission'

const { can } = useValidatePermission()
const grupo = useRoute().params.grupo
const d = ref(null)
const cargando = ref(true)
const error = ref('')
const errorAccion = ref('')
const ocupado = ref(false)

const pendientes = computed(() => (Array.isArray(d.value?.calculo?.pendientes) ? d.value.calculo.pendientes.length : (d.value?.calculo?.pendientes ?? 0)))
const puedePublicar = computed(() => d.value && can('academico_acta_publicar') && d.value.cuadricula.acta?.estado === 'borrador' && d.value.calculo?.completo)

function celda(notas, ev, i) {
  if (Array.isArray(notas)) return notas[i] ?? '—'
  return notas?.[ev.id_evaluacion ?? ev.codigo] ?? '—'
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    d.value = (await api.get(`/academico/actas/${grupo}`)).data
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

async function publicar() {
  ocupado.value = true
  errorAccion.value = ''
  try {
    await api.post(`/academico/actas/${grupo}/publicaciones`, { version: d.value.cuadricula.acta.version })
    await cargar()
  } catch (e) {
    const m = { ACTA_CONFLICTO: 'El acta cambió mientras la revisabas. Recarga e intenta de nuevo.', ACTA_INCOMPLETA: 'Faltan notas por registrar.', PERIODO_CERRADO: 'La ventana de notas está cerrada.' }
    errorAccion.value = [m[e?.code], mensajeError(e)].filter(Boolean).join(' ')
    if (e?.code === 'ACTA_CONFLICTO') await cargar()
  } finally {
    ocupado.value = false
  }
}

onMounted(cargar)
</script>
