<template>
  <div>
    <Encabezado contexto="Portal docente" titulo="Evaluaciones y notas" :descripcion="datos ? `Escala ${datos.escala.minimo} a ${datos.escala.maximo}, aprobación desde ${datos.escala.umbral_aprobacion}.` : ''">
      <RouterLink :to="`/docente/grupos/${id}`"><Button label="Volver al grupo" text icon="pi pi-arrow-left" tabindex="-1" /></RouterLink>
    </Encabezado>

    <EstadoVista :cargando="cargando" :error="error" :vacio="!datos" :reintentar="cargar">
      <template v-if="datos">
        <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
          <span>Período de notas:</span><EstadoTag :valor="datos.ventana_notas?.estado ?? ''" />
          <span v-if="datos.ventana_notas?.fin_efectivo" class="text-secondary">Cierra {{ fecha(datos.ventana_notas.fin_efectivo) }}</span>
          <span>Acta:</span><EstadoTag v-if="datos.acta" :valor="datos.acta.estado" /><span v-else class="text-secondary">sin calcular</span>
          <span v-if="datos.acta" class="text-secondary">v{{ datos.acta.version }} · {{ fecha(datos.acta.calculado_en) }}</span>
        </div>
        <p v-if="!editable" role="status" class="mb-4 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900">Las notas están en modo de solo lectura: el período no está abierto o el acta ya fue publicada.</p>
        <div aria-live="polite">
          <p v-if="mensaje" role="status" class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-900">{{ mensaje }}</p>
          <p v-if="errorAccion" role="alert" class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ errorAccion }}</p>
        </div>

        <section v-if="mostrarEstructura" aria-labelledby="t-est" class="mb-6 rounded-xl border border-secondary-100 bg-surface-card p-4">
          <h2 id="t-est" class="mb-1 text-lg font-semibold">Estructura de evaluaciones</h2>
          <p class="mb-3 text-sm text-secondary">La suma de ponderaciones debe ser {{ datos.escala.ponderacion_total }}. La estructura se bloquea cuando existen notas.</p>
          <div v-for="(ev, i) in estructura" :key="i" class="mb-2 flex flex-wrap items-end gap-2">
            <div><label :for="`ev-n-${i}`" class="mb-1 block text-xs">Nombre</label><InputText :id="`ev-n-${i}`" v-model="ev.nombre" /></div>
            <div><label :for="`ev-p-${i}`" class="mb-1 block text-xs">Ponderación</label><InputNumber :inputId="`ev-p-${i}`" v-model="ev.ponderacion" :min="0" :max="datos.escala.ponderacion_total" :maxFractionDigits="2" /></div>
            <Button type="button" text severity="danger" icon="pi pi-trash" :aria-label="`Quitar evaluación ${i + 1}`" @click="estructura.splice(i, 1)" />
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-3">
            <Button type="button" outlined size="small" icon="pi pi-plus" label="Agregar evaluación" @click="estructura.push({ nombre: '', ponderacion: 0 })" />
            <span class="text-sm" :class="sumaOk ? 'text-green-700' : 'text-red-700'">Suma: {{ suma }} / {{ datos.escala.ponderacion_total }}</span>
            <Button type="button" label="Guardar estructura" :disabled="!sumaOk || !estructura.length || estructura.some((e) => !e.nombre.trim())" :loading="guardandoEstructura" @click="guardarEstructura" />
          </div>
        </section>

        <section v-if="datos.evaluaciones.length" aria-labelledby="t-notas" class="mb-6">
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h2 id="t-notas" class="text-lg font-semibold">Notas</h2>
            <div class="flex gap-2">
              <Button v-if="editable" label="Guardar notas" icon="pi pi-save" :disabled="!sucias.size" :loading="guardando" @click="guardarNotas" />
              <Button label="Preparar cálculo final" outlined :loading="calculando" :disabled="!!sucias.size" @click="calcular" />
            </div>
          </div>
          <div class="overflow-x-auto rounded-xl border border-secondary-100 bg-surface-card">
            <table class="w-full text-sm">
              <caption class="sr-only">Notas por estudiante y evaluación</caption>
              <thead class="border-b border-secondary-100 text-left text-secondary">
                <tr>
                  <th scope="col" class="px-3 py-2">Carnet</th><th scope="col" class="px-3 py-2">Estudiante</th>
                  <th v-for="ev in datos.evaluaciones" :key="ev.id_evaluacion" scope="col" class="px-3 py-2">{{ ev.nombre }} <span class="font-normal">({{ ev.ponderacion }}%)</span></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="est in datos.estudiantes" :key="est.id_inscripcion" class="border-b border-secondary-100 last:border-0">
                  <td class="px-3 py-2 align-top">{{ est.carnet }}</td>
                  <td class="px-3 py-2 align-top">{{ est.nombre }}</td>
                  <td v-for="ev in datos.evaluaciones" :key="ev.id_evaluacion" class="px-3 py-2 align-top">
                    <div class="flex flex-col gap-1">
                      <InputNumber :inputId="`n-${est.id_inscripcion}-${ev.id_evaluacion}`" :ariaLabel="`Nota de ${est.nombre} en ${ev.nombre}`" :modelValue="celda(est, ev).valor" :min="datos.escala.minimo" :max="datos.escala.maximo" :minFractionDigits="0" :maxFractionDigits="datos.escala.precision ?? 2" :disabled="!editable || celda(est, ev).condicion === 'ausente'" inputClass="w-24" @update:modelValue="cambiarValor(est, ev, $event)" />
                      <label class="flex items-center gap-1 text-xs"><Checkbox binary :modelValue="celda(est, ev).condicion === 'ausente'" :disabled="!editable" @update:modelValue="cambiarAusente(est, ev, $event)" /> Ausente</label>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section v-if="calculo" aria-labelledby="t-calc" class="rounded-xl border border-secondary-100 bg-surface-card p-4">
          <h2 id="t-calc" class="mb-2 text-lg font-semibold">Cálculo final <span class="text-sm font-normal text-secondary">(versión de acta {{ calculo.version_acta ?? '—' }})</span></h2>
          <p v-if="!calculo.completo" class="mb-2 rounded-lg bg-yellow-50 p-2 text-sm text-yellow-900">Cálculo incompleto. Faltan notas para: {{ calculo.pendientes?.map(textoPendiente).join('; ') || 'algunos estudiantes' }}.</p>
          <table class="w-full text-sm">
            <thead class="text-left text-secondary"><tr><th scope="col" class="py-1">Carnet</th><th scope="col" class="py-1">Nota final</th><th scope="col" class="py-1">Condición</th></tr></thead>
            <tbody>
              <tr v-for="r in calculo.resultados" :key="r.carnet" class="border-t border-secondary-100"><td class="py-1">{{ r.carnet }}</td><td class="py-1">{{ r.nota_final ?? '—' }}</td><td class="py-1"><EstadoTag :valor="r.condicion ?? ''" /></td></tr>
            </tbody>
          </table>
        </section>
      </template>
    </EstadoVista>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Button, Checkbox, InputNumber, InputText } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import { api, fecha, mensajeError } from '../services/academico'

const route = useRoute()
const id = computed(() => route.params.grupo)
const datos = ref(null)
const cargando = ref(true)
const error = ref('')
const mensaje = ref('')
const errorAccion = ref('')
const estructura = ref([])
const guardandoEstructura = ref(false)
const guardando = ref(false)
const calculando = ref(false)
const calculo = ref(null)
const edicion = reactive({}) // clave "insc:eval" -> { valor, condicion }
const sucias = reactive(new Set())

const editable = computed(() => ['regular', 'gracia'].includes(datos.value?.ventana_notas?.estado) && datos.value?.acta?.estado !== 'publicada')
const mostrarEstructura = computed(() => editable.value && (!datos.value.evaluaciones.length || !datos.value.estructura_bloqueada))
const suma = computed(() => Math.round(estructura.value.reduce((s, e) => s + Number(e.ponderacion || 0), 0) * 100) / 100)
const sumaOk = computed(() => suma.value === Number(datos.value?.escala?.ponderacion_total))
const llave = (est, ev) => `${est.id_inscripcion}:${ev.id_evaluacion}`

function notaOriginal(est, ev) {
  return est.notas?.find((n) => n.id_evaluacion === ev.id_evaluacion)
}
function celda(est, ev) {
  return edicion[llave(est, ev)] ?? { valor: notaOriginal(est, ev)?.valor ?? null, condicion: notaOriginal(est, ev)?.condicion ?? null }
}
function actualizar(est, ev, parcial) {
  const k = llave(est, ev)
  edicion[k] = { ...celda(est, ev), ...parcial }
  sucias.add(k)
}
function cambiarValor(est, ev, v) {
  actualizar(est, ev, { valor: v, condicion: v === null || v === undefined ? null : 'nota' })
}
function cambiarAusente(est, ev, marcado) {
  actualizar(est, ev, marcado ? { valor: null, condicion: 'ausente' } : { condicion: null })
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    datos.value = (await api.get(`/academico/mis-grupos/${id.value}/evaluaciones`)).data
    estructura.value = datos.value.evaluaciones.map((e) => ({ nombre: e.nombre, ponderacion: Number(e.ponderacion) }))
    Object.keys(edicion).forEach((k) => delete edicion[k])
    sucias.clear()
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

async function guardarEstructura() {
  guardandoEstructura.value = true
  errorAccion.value = ''
  mensaje.value = ''
  try {
    const r = await api.put(`/academico/mis-grupos/${id.value}/evaluaciones`, { evaluaciones: estructura.value.map((e) => ({ nombre: e.nombre.trim(), ponderacion: e.ponderacion })) })
    mensaje.value = r.message || 'Estructura guardada.'
    await cargar()
  } catch (e) {
    errorAccion.value = mensajeError(e)
  } finally {
    guardandoEstructura.value = false
  }
}

async function guardarNotas() {
  guardando.value = true
  errorAccion.value = ''
  mensaje.value = ''
  const notas = []
  for (const k of sucias) {
    const [insc, ev] = k.split(':').map(Number)
    const est = datos.value.estudiantes.find((x) => x.id_inscripcion === insc)
    const original = est?.notas?.find((n) => n.id_evaluacion === ev)
    const c = edicion[k]
    notas.push({ id_evaluacion: ev, id_inscripcion: insc, valor: c.condicion === 'ausente' ? null : c.valor, condicion: c.condicion ?? 'nota', version: original?.version ?? 0 })
  }
  try {
    const r = await api.put(`/academico/mis-grupos/${id.value}/notas`, { notas })
    mensaje.value = r.message || 'Notas guardadas.'
    await cargar()
  } catch (e) {
    // Ante errores (incluidos conflictos de versión) se muestra el mensaje y se recargan los valores vigentes.
    errorAccion.value = mensajeError(e)
    const msg = errorAccion.value
    await cargar()
    errorAccion.value = msg
  } finally {
    guardando.value = false
  }
}

async function calcular() {
  calculando.value = true
  errorAccion.value = ''
  mensaje.value = ''
  try {
    const r = await api.post(`/academico/mis-grupos/${id.value}/calculo-final`)
    calculo.value = r.data
  } catch (e) {
    errorAccion.value = mensajeError(e)
  } finally {
    calculando.value = false
  }
}

onMounted(cargar)

/** Describe un pendiente del cálculo: estudiante (carnet) y evaluación faltante. */
function textoPendiente(p) {
  if (typeof p !== 'object' || p === null) return String(p)
  const est = datos.value?.estudiantes?.find((e) => e.id_inscripcion === p.id_inscripcion)
  return [est?.carnet, p.evaluacion, p.motivo].filter(Boolean).join(' · ') || p.message || 'pendiente'
}
</script>
