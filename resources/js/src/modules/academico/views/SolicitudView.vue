<template>
  <div>
    <Encabezado contexto="ADM-02" :titulo="s ? `Solicitud ${s.folio}` : 'Solicitud'" :descripcion="s ? `${s.carrera?.nombre ?? s.carrera ?? ''} — ${s.convocatoria?.titulo_publico ?? s.convocatoria?.codigo ?? s.convocatoria ?? ''}` : ''">
      <RouterLink to="/academico/solicitudes"><Button outlined label="Volver" icon="pi pi-arrow-left" /></RouterLink>
    </Encabezado>
    <EstadoVista :cargando="cargando" :error="error" :reintentar="cargar">
      <template v-if="s">
        <p class="mb-4 flex items-center gap-2 text-sm">Estado: <EstadoTag :valor="s.estado" /> <span class="text-secondary">· revisión actual {{ s.revision_actual }}</span></p>
        <p v-if="s.motivo_observacion" class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Observación: {{ s.motivo_observacion }}</p>

        <section class="mb-6 rounded-xl border border-secondary-100 bg-surface-card p-4">
          <h2 class="mb-2 text-lg font-semibold">Identidad</h2>
          <dl class="grid gap-x-6 gap-y-1 text-sm md:grid-cols-2"><div v-for="(v, k) in (s.identidad ?? {})" :key="k"><dt class="inline font-medium">{{ etiqueta(k) }}: </dt><dd class="inline">{{ mostrar(v) }}</dd></div></dl>
        </section>

        <section class="mb-6 rounded-xl border border-secondary-100 bg-surface-card p-4">
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">Revisión de datos</h2>
            <div v-if="s.revisiones?.length"><label for="rev" class="mr-2 text-sm">Revisión</label><Select v-model="revSel" inputId="rev" :options="s.revisiones" optionLabel="revision" optionValue="revision" /></div>
          </div>
          <template v-if="rev">
            <p class="mb-2 text-xs text-secondary">Enviada {{ fecha(rev.instante) }} · edad al enviar: {{ rev.edad ?? '—' }}</p>
            <table class="mb-4 w-full text-sm">
              <caption class="sr-only">Datos del formulario</caption>
              <tbody><tr v-for="(v, k) in (rev.datos?.formulario ?? {})" :key="k" class="border-b border-secondary-100"><th scope="row" class="w-1/3 py-1 pr-2 text-left font-medium">{{ etiqueta(k) }}</th><td class="py-1">{{ mostrar(v) }}</td></tr></tbody>
            </table>
            <h3 class="mb-1 font-semibold">Anexos</h3>
            <p v-if="!rev.anexos?.length" class="text-sm text-secondary">Sin anexos.</p>
            <ul v-else class="space-y-1 text-sm">
              <li v-for="a in rev.anexos" :key="a.id_anexo" class="flex items-center justify-between gap-2">
                <span>{{ a.nombre }} <span class="text-secondary">({{ a.mime }}, {{ kb(a.tamano) }})</span></span>
                <Button size="small" outlined icon="pi pi-download" label="Descargar" :aria-label="`Descargar ${a.nombre}`" @click="descargar(a)" />
              </li>
            </ul>
          </template>
        </section>

        <section v-if="s.pago" class="mb-6 rounded-xl border border-secondary-100 bg-surface-card p-4">
          <h2 class="mb-2 text-lg font-semibold">Pago</h2>
          <p class="text-sm">Folio {{ s.pago.folio }} · ${{ s.pago.monto }} · <EstadoTag :valor="s.pago.estado" /> · referencia: {{ s.pago.referencia ?? '—' }}</p>
          <p v-if="s.pago.observacion" class="text-sm text-secondary">Observación: {{ s.pago.observacion }}</p>
          <p v-if="errorPago" role="alert" class="mt-2 text-sm text-red-600">{{ errorPago }}</p>
          <div v-if="can('academico_solicitud_decidir') && s.pago.estado !== 'validado'" class="mt-3 space-y-2">
            <label for="pago-motivo" class="block text-sm">Motivo (obligatorio al observar)</label>
            <InputText id="pago-motivo" v-model="motivoPago" class="w-full" />
            <div class="flex gap-2"><Button label="Validar pago" size="small" :loading="ocupado" @click="revisarPago('validado')" /><Button label="Observar pago" severity="danger" outlined size="small" :loading="ocupado" @click="revisarPago('observado')" /></div>
          </div>
        </section>

        <section v-if="s.decisiones?.length" class="mb-6 rounded-xl border border-secondary-100 bg-surface-card p-4">
          <h2 class="mb-2 text-lg font-semibold">Decisiones</h2>
          <ul class="space-y-1 text-sm"><li v-for="(x, i) in s.decisiones" :key="i"><EstadoTag :valor="x.estado ?? ''" /> {{ x.motivo }} <span class="text-secondary">{{ fecha(x.instante ?? x.creado_en) }}</span></li></ul>
        </section>

        <section v-if="resultado" class="mb-6 rounded-xl border border-green-300 bg-green-50 p-4 text-sm text-green-900">
          Solicitud aceptada. Carnet asignado: <strong>{{ resultado.carnet }}</strong>.
          <RouterLink v-if="resultado.id_expediente" class="ml-2 underline" :to="`/academico/expedientes/${resultado.id_expediente}`">Ver expediente</RouterLink>
        </section>

        <section v-if="can('academico_solicitud_decidir') && s.estado === 'recibida'" class="rounded-xl border border-secondary-100 bg-surface-card p-4">
          <h2 class="mb-2 text-lg font-semibold">Decisión</h2>
          <p v-if="errorDecision" role="alert" class="mb-2 rounded border border-red-300 bg-red-50 p-2 text-sm text-red-800">{{ errorDecision }}</p>
          <ul v-if="pendientes.length" class="mb-2 list-disc pl-5 text-sm text-red-700"><li v-for="p in pendientes" :key="p.codigo ?? p">{{ p.nombre ?? p.codigo ?? p }}</li></ul>
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label for="obs" class="mb-1 block text-sm font-medium">Motivo de la observación</label>
              <Textarea id="obs" v-model="motivo" rows="3" class="w-full" />
              <Button class="mt-2" label="Observar" severity="danger" outlined :loading="ocupado" @click="decidir('observada')" />
            </div>
            <div>
              <label for="fing" class="mb-1 block text-sm font-medium">Fecha de ingreso</label>
              <input id="fing" v-model="fechaIngreso" type="date" class="rounded border border-secondary-100 p-2" />
              <div><Button class="mt-2" label="Aceptar solicitud" :loading="ocupado" @click="decidir('aceptada')" /></div>
            </div>
          </div>
        </section>
      </template>
    </EstadoVista>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Button, InputText, Select, Textarea } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import { api, fecha, mensajeError } from '../services/academico'
import http from '@/shared/services/http'
import { useValidatePermission } from '@/shared/composables/useValidatePermission'

const { can } = useValidatePermission()
const id = useRoute().params.solicitud
const s = ref(null)
const cargando = ref(true)
const error = ref('')
const revSel = ref(null)
const ocupado = ref(false)
const motivo = ref('')
const motivoPago = ref('')
const fechaIngreso = ref(new Date().toISOString().slice(0, 10))
const errorDecision = ref('')
const errorPago = ref('')
const pendientes = ref([])
const resultado = ref(null)

const rev = computed(() => s.value?.revisiones?.find((r) => r.revision === revSel.value))
const etiqueta = (k) => String(k).replaceAll('_', ' ').replace(/^./, (c) => c.toUpperCase())
const mostrar = (v) => (v === true ? 'Sí' : v === false ? 'No' : v === null || v === undefined || v === '' ? '—' : typeof v === 'object' ? JSON.stringify(v) : v)
const kb = (b) => `${Math.max(1, Math.round((b ?? 0) / 1024))} KB`

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    s.value = (await api.get(`/academico/solicitudes/${id}`)).data
    revSel.value = s.value.revision_actual ?? s.value.revisiones?.at(-1)?.revision ?? null
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

async function descargar(a) {
  try {
    const r = await http.get(`/academico/solicitudes/${id}/anexos/${a.id_anexo}`, { responseType: 'blob' })
    const url = URL.createObjectURL(r.data)
    window.open(url, '_blank', 'noopener')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    errorDecision.value = mensajeError(e)
  }
}

async function revisarPago(estado) {
  ocupado.value = true
  errorPago.value = ''
  try {
    await api.post(`/academico/solicitudes/${id}/pago/revisiones`, { estado, version: s.value.pago.version, ...(motivoPago.value ? { motivo: motivoPago.value } : {}) })
    motivoPago.value = ''
    await cargar()
  } catch (e) {
    errorPago.value = mensajeError(e)
  } finally {
    ocupado.value = false
  }
}

async function decidir(estado) {
  ocupado.value = true
  errorDecision.value = ''
  pendientes.value = []
  const body = { estado, version: s.value.version }
  if (estado === 'observada') body.motivo = motivo.value
  else body.fecha_ingreso = fechaIngreso.value
  try {
    const r = await api.post(`/academico/solicitudes/${id}/decisiones`, body)
    const d = r.data ?? {}
    resultado.value = estado === 'aceptada' ? { carnet: d.carnet ?? d.expediente?.carnet, id_expediente: d.id_expediente ?? d.expediente?.id_expediente } : null
    await cargar()
  } catch (e) {
    const m = { PAGO_NO_VALIDADO: 'El pago aún no está validado.', REQUISITOS_PENDIENTES: 'Hay requisitos pendientes de cumplir.', IDENTIDAD_AMBIGUA: 'La identidad coincide con más de un registro; resuélvela antes de aceptar.', EXPEDIENTE_EXISTENTE: 'Ya existe un expediente para esta persona.' }
    errorDecision.value = [m[e?.code], mensajeError(e)].filter(Boolean).join(' ')
    if (e?.code === 'REQUISITOS_PENDIENTES') pendientes.value = e.meta?.requisitos ?? e.meta?.pendientes ?? []
  } finally {
    ocupado.value = false
  }
}

onMounted(cargar)
</script>
