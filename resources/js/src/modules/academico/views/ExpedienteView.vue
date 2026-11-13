<template>
  <div>
    <Encabezado contexto="EXP-01" :titulo="e ? `Expediente ${e.carnet}` : 'Expediente'" :descripcion="e ? `${e.nombre} — ${e.carrera?.nombre ?? e.carrera ?? ''}` : ''">
      <RouterLink to="/academico/expedientes"><Button outlined label="Volver" icon="pi pi-arrow-left" /></RouterLink>
      <Button v-if="can('academico_expediente_editar')" outlined label="Reenviar acceso" icon="pi pi-envelope" :loading="ocupado" @click="reenviar" />
    </Encabezado>
    <p v-if="mensaje" role="status" class="mb-4 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-800">{{ mensaje }}</p>
    <p v-if="errorAccion" role="alert" class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ errorAccion }}</p>
    <EstadoVista :cargando="cargando" :error="error" :reintentar="cargar">
      <template v-if="e">
        <section class="mb-6 rounded-xl border border-secondary-100 bg-surface-card p-4">
          <h2 class="mb-2 text-lg font-semibold">Datos <EstadoTag :valor="e.estado" :texto="e.estado_etiqueta" /></h2>
          <dl class="mb-4 grid gap-x-6 gap-y-1 text-sm md:grid-cols-2">
            <div><dt class="inline font-medium">Pensum: </dt><dd class="inline">{{ e.pensum?.codigo ?? e.pensum?.nombre ?? e.pensum ?? '—' }}</dd></div>
            <div><dt class="inline font-medium">Fecha de ingreso: </dt><dd class="inline">{{ e.fecha_ingreso }}</dd></div>
            <div><dt class="inline font-medium">Nombre completo: </dt><dd class="inline">{{ e.estudiante.nombres }} {{ e.estudiante.primer_apellido }} {{ e.estudiante.segundo_apellido }}</dd></div>
            <div><dt class="inline font-medium">Correo: </dt><dd class="inline">{{ e.estudiante.email }}</dd></div>
            <div><dt class="inline font-medium">Documento: </dt><dd class="inline">{{ e.estudiante.documento_tipo }} {{ e.estudiante.documento_numero }}</dd></div>
          </dl>
          <form class="grid gap-3 md:grid-cols-2" @submit.prevent="guardar">
            <div v-for="c in CAMPOS" :key="c.k"><label :for="`ex-${c.k}`" class="mb-1 block text-sm font-medium">{{ c.t }}</label><InputText :id="`ex-${c.k}`" v-model="form[c.k]" class="w-full" /></div>
            <div class="md:col-span-2"><Button type="submit" label="Guardar datos" :loading="ocupado" /></div>
          </form>
        </section>

        <section v-if="e.transiciones_permitidas?.length" class="mb-6 rounded-xl border border-secondary-100 bg-surface-card p-4">
          <h2 class="mb-2 text-lg font-semibold">Cambiar estado</h2>
          <div class="grid items-end gap-3 md:grid-cols-3">
            <div><label for="nuevo-estado" class="mb-1 block text-sm font-medium">Nuevo estado</label><Select v-model="nuevoEstado" inputId="nuevo-estado" :options="e.transiciones_permitidas.map((v) => ({ v, n: ESTADOS_ESTUDIANTE[v] ?? v }))" optionLabel="n" optionValue="v" placeholder="Selecciona" class="w-full" /></div>
            <div class="md:col-span-1"><label for="motivo-estado" class="mb-1 block text-sm font-medium">Motivo</label><InputText id="motivo-estado" v-model="motivo" class="w-full" /></div>
            <Button label="Aplicar cambio" :disabled="!nuevoEstado || !motivo" :loading="ocupado" @click="cambiarEstado" />
          </div>
        </section>

        <section class="mb-6 rounded-xl border border-secondary-100 bg-surface-card p-4">
          <h2 class="mb-2 text-lg font-semibold">Historial académico</h2>
          <h3 class="font-medium">Materias finalizadas</h3>
          <p v-if="!e.historial?.finalizados?.length" class="mb-2 text-sm text-secondary">Sin materias finalizadas.</p>
          <table v-else class="mb-3 w-full text-sm"><thead class="text-left text-secondary"><tr><th scope="col">Materia</th><th scope="col">Ciclo</th><th scope="col">Nota</th><th scope="col">Condición</th><th scope="col">UV</th></tr></thead>
            <tbody><tr v-for="(f, i) in e.historial.finalizados" :key="i" class="border-t border-secondary-100"><td>{{ f.materia.codigo }} · {{ f.materia.nombre }}</td><td>{{ f.ciclo?.codigo }}</td><td>{{ f.nota_final }}</td><td>{{ f.condicion }}</td><td>{{ f.creditos }}</td></tr></tbody></table>
          <h3 class="font-medium">En curso</h3>
          <ul v-if="e.historial?.en_curso?.length" class="mb-2 list-disc pl-5 text-sm"><li v-for="(m, i) in e.historial.en_curso" :key="i">{{ m.materia?.codigo }} {{ m.materia?.nombre }} <span v-if="m.ciclo">({{ m.ciclo.codigo }})</span></li></ul>
          <p v-else class="mb-2 text-sm text-secondary">Sin materias en curso.</p>
          <h3 class="font-medium">Retiradas</h3>
          <ul v-if="e.historial?.retirados?.length" class="mb-2 list-disc pl-5 text-sm"><li v-for="(m, i) in e.historial.retirados" :key="i">{{ m.materia?.codigo }} {{ m.materia?.nombre }} <span v-if="m.ciclo">({{ m.ciclo.codigo }})</span></li></ul>
          <p v-else class="mb-2 text-sm text-secondary">Sin materias retiradas.</p>
          <h3 class="font-medium">Eventos del expediente</h3>
          <ul v-if="e.historial?.eventos?.length" class="text-sm"><li v-for="(v, i) in e.historial.eventos" :key="i" class="border-t border-secondary-100 py-1">{{ fecha(v.instante) }} — {{ v.etiqueta ?? v.tipo }}<span v-if="v.motivo">: {{ v.motivo }}</span></li></ul>
          <p v-else class="text-sm text-secondary">Sin eventos.</p>
        </section>

        <section class="rounded-xl border border-secondary-100 bg-surface-card p-4">
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">Documentos</h2>
            <div class="flex flex-wrap gap-2">
              <Button size="small" label="Emitir certificación de notas" :loading="emitiendo" @click="emitir('certificacion_notas')" />
              <Button size="small" outlined label="Emitir constancia de estudio" :loading="emitiendo" @click="emitir('constancia_estudio')" />
            </div>
          </div>
          <p v-if="errorDoc" role="alert" class="mb-2 text-sm text-red-600">{{ errorDoc }}</p>
          <EstadoVista :cargando="cargandoDocs" :error="errorDocs" :vacio="!docs.length" textoVacio="Aún no se han emitido documentos." :reintentar="cargarDocs">
            <table class="w-full text-sm">
              <thead class="text-left text-secondary"><tr><th scope="col" class="px-2 py-1">Tipo</th><th scope="col" class="px-2 py-1">Folio</th><th scope="col" class="px-2 py-1">Estado</th><th scope="col" class="px-2 py-1">Autoridad</th><th scope="col" class="px-2 py-1">Págs.</th><th scope="col" class="px-2 py-1">Emitido</th><th scope="col" class="px-2 py-1 text-right">Acciones</th></tr></thead>
              <tbody>
                <tr v-for="d in docs" :key="d.id_documento" class="border-t border-secondary-100">
                  <td class="px-2 py-1">{{ d.tipo === 'certificacion_notas' ? 'Certificación de notas' : 'Constancia de estudio' }}</td>
                  <td class="px-2 py-1">{{ d.folio ?? '—' }}</td>
                  <td class="px-2 py-1"><EstadoTag :valor="d.estado" /><span v-if="d.mensaje_error" class="block text-xs text-red-600">{{ d.mensaje_error }}</span></td>
                  <td class="px-2 py-1">{{ d.autoridad?.nombre }} <span class="text-secondary">{{ d.autoridad?.cargo }}</span></td>
                  <td class="px-2 py-1">{{ d.paginas ?? '—' }}</td>
                  <td class="px-2 py-1">{{ fecha(d.emitido_en) }}</td>
                  <td class="px-2 py-1 text-right"><Button v-if="d.estado === 'emitido'" text size="small" icon="pi pi-download" label="Ver PDF" :aria-label="`Ver PDF del folio ${d.folio}`" @click="verPdf(d)" /></td>
                </tr>
              </tbody>
            </table>
          </EstadoVista>
        </section>
      </template>
    </EstadoVista>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Button, InputText, Select } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import { api, ESTADOS_ESTUDIANTE, fecha, mensajeError, postIdempotente } from '../services/academico'
import http from '@/shared/services/http'
import { useValidatePermission } from '@/shared/composables/useValidatePermission'

const { can } = useValidatePermission()
const id = useRoute().params.expediente
const CAMPOS = [{ k: 'nombre_uso', t: 'Nombre de uso' }, { k: 'telefono', t: 'Teléfono' }, { k: 'direccion', t: 'Dirección' }, { k: 'contacto_emergencia', t: 'Contacto de emergencia' }]
const e = ref(null)
const cargando = ref(true)
const error = ref('')
const errorAccion = ref('')
const mensaje = ref('')
const ocupado = ref(false)
const form = reactive({ nombre_uso: '', telefono: '', direccion: '', contacto_emergencia: '' })
const nuevoEstado = ref(null)
const motivo = ref('')

const docs = ref([])
const cargandoDocs = ref(true)
const errorDocs = ref('')
const errorDoc = ref('')
const emitiendo = ref(false)

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    e.value = (await api.get(`/academico/expedientes/${id}`)).data
    for (const c of CAMPOS) form[c.k] = e.value.estudiante?.[c.k] ?? ''
    nuevoEstado.value = null
    motivo.value = ''
    cargarDocs()
  } catch (x) {
    error.value = mensajeError(x)
  } finally {
    cargando.value = false
  }
}

async function accion(fn, ok) {
  ocupado.value = true
  errorAccion.value = ''
  mensaje.value = ''
  try {
    await fn()
    if (ok) mensaje.value = ok
    await cargar()
  } catch (x) {
    errorAccion.value = mensajeError(x)
    if (x?.status === 409) await cargar()
  } finally {
    ocupado.value = false
  }
}

const guardar = () => accion(() => api.patch(`/academico/expedientes/${id}`, { version: e.value.version, ...form }), 'Datos actualizados.')
const cambiarEstado = () => accion(() => api.post(`/academico/expedientes/${id}/transiciones`, { estado: nuevoEstado.value, motivo: motivo.value, version: e.value.version }), 'Estado actualizado.')
const reenviar = () => accion(() => api.post(`/academico/expedientes/${id}/acceso/reenvios`), 'Se reenviaron las instrucciones de acceso.')

async function cargarDocs() {
  cargandoDocs.value = true
  errorDocs.value = ''
  try {
    docs.value = (await api.get(`/academico/expedientes/${id}/documentos`)).data
  } catch (x) {
    errorDocs.value = mensajeError(x)
  } finally {
    cargandoDocs.value = false
  }
}

async function emitir(tipo) {
  emitiendo.value = true
  errorDoc.value = ''
  try {
    await postIdempotente(`/academico/expedientes/${id}/documentos`, { tipo })
  } catch (x) {
    const m = { DOCUMENTO_NO_EMITIBLE: 'El expediente no cumple las condiciones para emitir este documento.', AUTORIDAD_DOCUMENTAL_INVALIDA: 'No hay una autoridad documental vigente y válida.', IDENTIDAD_INSTITUCIONAL_INCOMPLETA: 'La identidad institucional está incompleta.' }
    errorDoc.value = [m[x?.code], mensajeError(x)].filter(Boolean).join(' ')
  } finally {
    emitiendo.value = false
    await cargarDocs()
  }
}

async function verPdf(d) {
  errorDoc.value = ''
  try {
    const r = await http.get(`/academico/expedientes/${id}/documentos/${d.id_documento}/archivo`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([r.data], { type: 'application/pdf' }))
    window.open(url, '_blank', 'noopener')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (x) {
    errorDoc.value = mensajeError(x)
  }
}

onMounted(cargar)

// Mientras haya documentos pendientes, refresca su estado cada 3 s (la generación del PDF corre en cola).
const sondeo = setInterval(() => {
  if (docs.value.some((d) => d.estado === 'pendiente')) cargarDocs()
}, 3000)
onBeforeUnmount(() => clearInterval(sondeo))
</script>
