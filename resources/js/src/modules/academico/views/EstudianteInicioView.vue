<template>
  <div>
    <Encabezado contexto="Portal del estudiante" :titulo="perfil ? perfil.nombre : 'Mi portal'" :descripcion="perfil ? `Carnet ${perfil.carnet}` : ''">
      <SelectorExpediente />
    </Encabezado>
    <EstadoVista :cargando="cargando" :error="error" :reintentar="iniciar">
      <section v-if="expediente" aria-labelledby="t-exp" class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <h2 id="t-exp" class="sr-only">Expediente seleccionado</h2>
        <div class="rounded-xl border border-secondary-100 bg-surface-card p-4"><p class="text-xs text-secondary">Carrera</p><p class="font-semibold">{{ expediente.carrera }}</p></div>
        <div class="rounded-xl border border-secondary-100 bg-surface-card p-4"><p class="text-xs text-secondary">Pensum</p><p class="font-semibold">{{ expediente.pensum }}</p></div>
        <div class="rounded-xl border border-secondary-100 bg-surface-card p-4"><p class="text-xs text-secondary">Estado</p><EstadoTag :valor="expediente.estado" :texto="expediente.estado_etiqueta" /></div>
        <div class="rounded-xl border border-secondary-100 bg-surface-card p-4"><p class="text-xs text-secondary">Fecha de ingreso</p><p class="font-semibold">{{ fecha(expediente.fecha_ingreso, 'DD/MM/YYYY') }}</p></div>
      </section>
      <p v-else class="rounded-lg border border-dashed border-secondary-100 py-8 text-center text-secondary">No tienes expedientes asociados a tu cuenta.</p>

      <nav aria-label="Accesos rápidos" class="mb-8 flex flex-wrap gap-3">
        <RouterLink :to="enlace('/estudiante/avance')" class="rounded-lg border border-secondary-100 bg-surface-card px-4 py-2 font-medium hover:bg-secondary-100/40">Avance académico</RouterLink>
        <RouterLink :to="enlace('/estudiante/inscripcion')" class="rounded-lg border border-secondary-100 bg-surface-card px-4 py-2 font-medium hover:bg-secondary-100/40">Inscripción</RouterLink>
        <RouterLink :to="enlace('/estudiante/mis-inscripciones')" class="rounded-lg border border-secondary-100 bg-surface-card px-4 py-2 font-medium hover:bg-secondary-100/40">Mis inscripciones</RouterLink>
        <RouterLink v-if="asistente" :to="enlace('/estudiante/asistente')" class="rounded-lg border border-secondary-100 bg-surface-card px-4 py-2 font-medium hover:bg-secondary-100/40">Asistente</RouterLink>
      </nav>

      <section aria-labelledby="t-cal">
        <h2 id="t-cal" class="mb-3 text-lg font-semibold">Calendario académico</h2>
        <p v-if="cargandoCal" role="status" class="text-secondary">Cargando calendario…</p>
        <p v-else-if="errorCal" role="alert" class="text-red-600">{{ errorCal }}</p>
        <p v-else-if="!ciclos.length" class="text-secondary">No hay ciclos publicados para este expediente.</p>
        <div v-else class="grid gap-4 lg:grid-cols-2">
          <article v-for="c in ciclos" :key="c.id_ciclo" class="rounded-xl border border-secondary-100 bg-surface-card p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
              <h3 class="font-semibold">{{ c.codigo }} · {{ c.nombre }}</h3>
              <EstadoTag :valor="c.estado" />
            </div>
            <p class="text-sm text-secondary">{{ fecha(c.calendario?.inicio, 'DD/MM/YYYY') }} al {{ fecha(c.calendario?.fin, 'DD/MM/YYYY') }} · Zona horaria: {{ c.calendario?.zona ?? '—' }}</p>
            <dl class="mt-3 space-y-3 text-sm">
              <div>
                <dt class="flex items-center gap-2 font-medium">Inscripción <EstadoTag :valor="c.inscripcion?.estado ?? ''" /></dt>
                <dd class="text-secondary">Inicio {{ fecha(c.calendario?.ventanas?.inscripcion?.inicio) }} · Fin regular {{ fecha(c.calendario?.ventanas?.inscripcion?.fin_regular) }} · Fin de gracia {{ fecha(c.calendario?.ventanas?.inscripcion?.fin_gracia) }}</dd>
              </div>
              <div>
                <dt class="flex items-center gap-2 font-medium">Notas <EstadoTag :valor="c.notas?.estado ?? ''" /></dt>
                <dd class="text-secondary">Inicio {{ fecha(c.calendario?.ventanas?.notas?.inicio) }} · Fin regular {{ fecha(c.calendario?.ventanas?.notas?.fin_regular) }} · Fin de gracia {{ fecha(c.calendario?.ventanas?.notas?.fin_gracia) }}</dd>
              </div>
              <div><dt class="inline font-medium">Inscripciones activas: </dt><dd class="inline">{{ c.inscripciones_activas ?? 0 }}</dd></div>
            </dl>
          </article>
        </div>
      </section>
    </EstadoVista>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import SelectorExpediente from '../components/SelectorExpediente.vue'
import { useExpedienteSeleccionado } from '../stores/expedienteSeleccionado'
import { useAuthStore } from '@/shared/stores/auth'
import { api, fecha, mensajeError } from '../services/academico'

const route = useRoute()
const store = useExpedienteSeleccionado()
const auth = useAuthStore()
const cargando = ref(true)
const error = ref('')
const cargandoCal = ref(false)
const errorCal = ref('')
const ciclos = ref([])
// El acceso al asistente depende solo del permiso; la disponibilidad se consulta al abrir su vista.
const asistente = computed(() => auth.permisos.includes('academico_mi_asistente_usar'))
const perfil = computed(() => store.perfil)
const expediente = computed(() => store.expediente)
const enlace = (ruta) => (store.idExpediente ? { path: ruta, query: { expediente: store.idExpediente } } : ruta)

async function cargarCalendario() {
  if (!store.idExpediente) { ciclos.value = []; return }
  cargandoCal.value = true
  errorCal.value = ''
  try {
    ciclos.value = (await api.get('/academico/mi-calendario', { expediente: store.idExpediente })).data ?? []
  } catch (e) {
    errorCal.value = mensajeError(e)
  } finally {
    cargandoCal.value = false
  }
}

async function iniciar() {
  cargando.value = true
  error.value = ''
  try {
    await store.cargarPerfil(route.query.expediente)
  } catch (e) {
    error.value = mensajeError(e)
    cargando.value = false
    return
  }
  cargando.value = false
  cargarCalendario()
}

watch(() => store.idExpediente, cargarCalendario)
onMounted(iniciar)
</script>
