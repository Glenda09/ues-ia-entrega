<template>
  <div>
    <Encabezado contexto="Portal del estudiante" titulo="Avance académico" :descripcion="avance ? `Versión curricular ${avance.pensum?.version_curricular ?? '—'}` : ''">
      <SelectorExpediente />
      <RouterLink :to="{ path: '/estudiante/inscripcion', query: { expediente: store.idExpediente } }"><Button label="Ir a inscripción" icon="pi pi-arrow-right" iconPos="right" tabindex="-1" /></RouterLink>
    </Encabezado>
    <EstadoVista :cargando="cargando" :error="error" :vacio="!avance" textoVacio="No hay información de avance." :reintentar="cargar">
      <template v-if="avance">
        <section aria-labelledby="t-res" class="mb-6 rounded-xl border border-secondary-100 bg-surface-card p-5">
          <h2 id="t-res" class="mb-3 text-lg font-semibold">Resumen</h2>
          <ProgressBar v-if="avance.resumen.porcentaje !== null && avance.resumen.porcentaje !== undefined" :value="Number(avance.resumen.porcentaje)" :aria-label="`Avance ${avance.resumen.porcentaje}%`" />
          <p v-else class="rounded-lg bg-yellow-50 p-3 text-sm text-yellow-900" role="status">{{ avance.resumen.aviso || 'El porcentaje de avance no está disponible.' }}</p>
          <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-4">
            <div><dt class="text-secondary">Créditos requeridos</dt><dd class="font-semibold">{{ avance.resumen.creditos_requeridos ?? '—' }}</dd></div>
            <div><dt class="text-secondary">Aprobados</dt><dd class="font-semibold">{{ avance.resumen.creditos_aprobados ?? '—' }}</dd></div>
            <div><dt class="text-secondary">Pendientes</dt><dd class="font-semibold">{{ avance.resumen.creditos_pendientes ?? '—' }}</dd></div>
            <div><dt class="text-secondary">Optativas aprobadas</dt><dd class="font-semibold">{{ avance.resumen.optativas_aprobadas ?? '—' }}</dd></div>
          </dl>
          <p v-if="avance.resumen.regla" class="mt-3 text-xs text-secondary">{{ avance.resumen.regla }}</p>
        </section>

        <section v-for="n in avance.niveles" :key="n.nivel" :aria-labelledby="`nivel-${n.nivel}`" class="mb-6">
          <h2 :id="`nivel-${n.nivel}`" class="mb-2 text-lg font-semibold">Nivel {{ n.nivel }}</h2>
          <div class="overflow-x-auto rounded-xl border border-secondary-100 bg-surface-card">
            <table class="w-full text-sm">
              <thead class="border-b border-secondary-100 text-left text-secondary">
                <tr><th scope="col" class="px-3 py-2">Código</th><th scope="col" class="px-3 py-2">Materia</th><th scope="col" class="px-3 py-2">Créditos</th><th scope="col" class="px-3 py-2">Requisitos</th><th scope="col" class="px-3 py-2">Condición</th><th scope="col" class="px-3 py-2">Intentos</th></tr>
              </thead>
              <tbody>
                <template v-for="m in n.materias" :key="m.id_materia">
                  <tr class="border-b border-secondary-100">
                    <td class="px-3 py-2 align-top">{{ m.codigo }}</td>
                    <td class="px-3 py-2 align-top">
                      {{ m.nombre }} <span v-if="!m.obligatoria" class="text-xs text-secondary">(optativa)</span>
                      <span v-if="m.acreditada_por_equivalencia" class="block text-xs text-secondary">Acreditada por equivalencia</span>
                    </td>
                    <td class="px-3 py-2 align-top">{{ m.creditos }}</td>
                    <td class="px-3 py-2 align-top">{{ m.requisitos?.length ? m.requisitos.map((r) => r.codigo).join(', ') : '—' }}</td>
                    <td class="px-3 py-2 align-top"><EstadoTag :valor="m.condicion" /></td>
                    <td class="px-3 py-2 align-top">
                      <Button v-if="m.intentos?.length" text size="small" :label="`${m.intentos.length} intento(s)`" :aria-expanded="abiertos.has(m.id_materia)" :aria-controls="`int-${m.id_materia}`" @click="alternar(m.id_materia)" />
                      <span v-else class="text-secondary">—</span>
                    </td>
                  </tr>
                  <tr v-if="abiertos.has(m.id_materia)" :id="`int-${m.id_materia}`" class="border-b border-secondary-100 bg-secondary-100/20">
                    <td colspan="6" class="px-3 py-2">
                      <ul class="space-y-1">
                        <li v-for="(i, k) in m.intentos" :key="k">
                          Ciclo {{ i.ciclo }} · <EstadoTag :valor="i.estado" /> · Condición {{ (i.condicion ?? '—').replaceAll('_', ' ') }} · Nota final {{ i.nota_final ?? '—' }}
                          <span v-if="m.intento_sustento === k || m.intento_sustento === i.ciclo" class="text-xs text-secondary">(sustenta la aprobación)</span>
                        </li>
                      </ul>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </section>
      </template>
    </EstadoVista>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Button, ProgressBar } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import SelectorExpediente from '../components/SelectorExpediente.vue'
import { useExpedienteSeleccionado } from '../stores/expedienteSeleccionado'
import { api, mensajeError } from '../services/academico'

const route = useRoute()
const store = useExpedienteSeleccionado()
const avance = ref(null)
const cargando = ref(true)
const error = ref('')
const abiertos = reactive(new Set())

function alternar(id) {
  if (abiertos.has(id)) abiertos.delete(id)
  else abiertos.add(id)
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    if (!store.perfil) await store.cargarPerfil(route.query.expediente)
    if (!store.idExpediente) { avance.value = null; return }
    avance.value = (await api.get('/academico/mi-avance', { expediente: store.idExpediente })).data
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

watch(() => store.idExpediente, (n, o) => { if (o !== null) cargar() })
onMounted(cargar)
</script>
