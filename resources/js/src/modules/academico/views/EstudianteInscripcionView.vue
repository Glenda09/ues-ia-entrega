<template>
  <div>
    <Encabezado contexto="Portal del estudiante" titulo="Inscripción de materias" descripcion="Elige un grupo por materia, valida tu selección y confirma la inscripción.">
      <SelectorExpediente />
    </Encabezado>

    <div class="mb-4 flex items-center gap-2">
      <label for="ciclo" class="text-sm font-medium">Ciclo</label>
      <Select inputId="ciclo" v-model="idCiclo" :options="ciclos" optionLabel="etiqueta" optionValue="id_ciclo" placeholder="Selecciona un ciclo" class="min-w-64" />
    </div>

    <EstadoVista :cargando="cargando" :error="error" :vacio="!oferta" textoVacio="Selecciona un ciclo publicado para ver la oferta." :reintentar="cargarOferta">
      <div v-if="oferta" class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div>
          <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
            <span>Ventana de inscripción:</span><EstadoTag :valor="oferta.ventana?.estado ?? ''" />
            <span v-if="oferta.ventana?.fin_efectivo" class="text-secondary">Cierra {{ fecha(oferta.ventana.fin_efectivo) }}</span>
            <span v-if="oferta.carga" class="text-secondary">Carga: {{ oferta.carga.actual }} / {{ oferta.carga.maxima }} créditos</span>
          </div>
          <div v-if="oferta.razones_generales?.length" role="alert" class="mb-4 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900">
            <ul class="list-disc pl-5"><li v-for="r in oferta.razones_generales" :key="r.code">{{ r.message }}</li></ul>
          </div>

          <p v-if="!oferta.materias?.length" class="text-secondary">No hay materias ofertadas en este ciclo.</p>
          <fieldset v-for="m in oferta.materias" :key="m.id_materia" class="mb-4 rounded-xl border border-secondary-100 bg-surface-card p-4">
            <legend class="px-1 font-semibold">{{ m.codigo }} · {{ m.nombre }} <span class="text-sm font-normal text-secondary">Nivel {{ m.nivel }} · {{ m.creditos }} créditos</span></legend>
            <ul v-if="m.razones?.length" class="mb-2 list-disc pl-5 text-sm text-yellow-800"><li v-for="r in m.razones" :key="r.code">{{ r.message }}</li></ul>
            <p v-if="!m.grupos?.length" class="text-sm text-secondary">Sin grupos disponibles.</p>
            <div v-for="g in m.grupos" :key="g.id_grupo" class="mb-2 flex items-start gap-3 rounded-lg border border-secondary-100 p-3" :class="{ 'opacity-60': !seleccionable(g) }">
              <input :id="`g-${g.id_grupo}`" type="radio" class="mt-1" :name="`materia-${m.id_materia}`" :value="g.id_grupo" :checked="seleccion[m.id_materia] === g.id_grupo" :disabled="!seleccionable(g)" @click="alternarGrupo(m.id_materia, g.id_grupo)" @change="elegir(m.id_materia, g.id_grupo)" />
              <label :for="`g-${g.id_grupo}`" class="flex-1 text-sm">
                <span class="font-medium">Grupo {{ g.codigo }}</span>
                <span v-if="g.inscrito" class="ml-2"><EstadoTag valor="activa" texto="Inscrito" /></span>
                <span class="block text-secondary">Cupo {{ g.inscritos }}/{{ g.cupo }} · {{ g.disponibles }} disponibles · Docente: {{ g.docente?.nombre_academico ?? '—' }}</span>
                <span class="block text-secondary">{{ horarios(g) }}</span>
                <span v-for="r in g.razones" :key="r.code ?? r" class="block text-yellow-800">{{ r.message ?? r }}</span>
              </label>
            </div>
          </fieldset>
        </div>

        <aside aria-labelledby="t-res" class="h-fit rounded-xl border border-secondary-100 bg-surface-card p-4 lg:sticky lg:top-4">
          <h2 id="t-res" class="mb-2 text-lg font-semibold">Resumen</h2>
          <p v-if="!elegidos.length" class="text-sm text-secondary">Aún no seleccionas grupos.</p>
          <ul v-else class="mb-3 space-y-1 text-sm">
            <li v-for="e in elegidos" :key="e.grupo.id_grupo">{{ e.materia.codigo }} · Grupo {{ e.grupo.codigo }} ({{ e.materia.creditos }} cr.)</li>
          </ul>
          <p class="mb-3 text-sm font-medium">Total: {{ totalCreditos }} créditos</p>
          <div class="flex flex-col gap-2">
            <Button label="Validar selección" outlined :disabled="!elegidos.length" :loading="validando" @click="validar" />
            <Button label="Confirmar inscripción" :disabled="!elegidos.length" :loading="confirmando" @click="confirmar" />
          </div>
          <div aria-live="polite">
            <ul v-if="erroresValidacion.length" class="mt-3 list-disc pl-5 text-sm text-red-700"><li v-for="(e, i) in erroresValidacion" :key="i">{{ e.message ?? e }}</li></ul>
            <p v-else-if="validado" class="mt-3 text-sm text-green-700">La selección es válida.</p>
            <div v-if="errorConfirmar" role="alert" class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ errorConfirmar }}</div>
          </div>
          <div v-if="resultado" role="status" class="mt-3 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-900">
            <p class="font-semibold">Inscripción registrada</p>
            <ul class="mt-1 list-disc pl-5"><li v-for="(r, i) in resultado" :key="i">{{ r.materia?.codigo ?? r.id_materia ?? '' }} {{ r.grupo?.codigo ? `· Grupo ${r.grupo.codigo}` : '' }} {{ r.estado ? `(${r.estado})` : '' }}</li></ul>
            <RouterLink :to="{ path: '/estudiante/mis-inscripciones', query: { expediente: store.idExpediente } }" class="mt-2 inline-block font-medium underline">Ver mis inscripciones</RouterLink>
          </div>
        </aside>
      </div>
    </EstadoVista>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Button, Select } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import SelectorExpediente from '../components/SelectorExpediente.vue'
import { useExpedienteSeleccionado } from '../stores/expedienteSeleccionado'
import { api, fecha, mensajeError, nuevaClave, postIdempotente } from '../services/academico'

const DIAS = { 1: 'Lunes', 2: 'Martes', 3: 'Miércoles', 4: 'Jueves', 5: 'Viernes', 6: 'Sábado', 7: 'Domingo' }
const route = useRoute()
const store = useExpedienteSeleccionado()
const ciclos = ref([])
const idCiclo = ref(Number(route.query.ciclo) || null)
const oferta = ref(null)
const cargando = ref(false)
const error = ref('')
const seleccion = reactive({})
const validando = ref(false)
const validado = ref(false)
const erroresValidacion = ref([])
const confirmando = ref(false)
const errorConfirmar = ref('')
const resultado = ref(null)
let clave = nuevaClave()
let gruposIniciales = String(route.query.grupos ?? '').split(',').map(Number).filter(Boolean)

const seleccionable = (g) => g.puede_inscribir || g.inscrito
const horarios = (g) => (g.horarios?.length ? g.horarios.map((h) => `${DIAS[h.dia] ?? h.dia} ${String(h.hora_inicio).slice(0, 5)}–${String(h.hora_fin).slice(0, 5)}`).join(' · ') : 'Sin horario')
const elegidos = computed(() => {
  const r = []
  for (const m of oferta.value?.materias ?? []) {
    const g = m.grupos?.find((x) => x.id_grupo === seleccion[m.id_materia])
    if (g) r.push({ materia: m, grupo: g })
  }
  return r
})
const idsGrupos = computed(() => elegidos.value.map((e) => e.grupo.id_grupo))
const totalCreditos = computed(() => elegidos.value.reduce((s, e) => s + Number(e.materia.creditos || 0), 0))

function reiniciarIntento() {
  clave = nuevaClave()
  validado.value = false
  erroresValidacion.value = []
  errorConfirmar.value = ''
  resultado.value = null
}
function elegir(idMateria, idGrupo) {
  seleccion[idMateria] = idGrupo
  reiniciarIntento()
}
function alternarGrupo(idMateria, idGrupo) {
  // Permite deseleccionar al pulsar de nuevo el grupo ya elegido.
  if (seleccion[idMateria] === idGrupo) {
    setTimeout(() => { delete seleccion[idMateria]; reiniciarIntento() }, 0)
  }
}

function aplicarIniciales() {
  if (!gruposIniciales.length || !oferta.value) return
  for (const m of oferta.value.materias ?? []) {
    const g = m.grupos?.find((x) => gruposIniciales.includes(x.id_grupo) && seleccionable(x))
    if (g) seleccion[m.id_materia] = g.id_grupo
  }
  gruposIniciales = []
}

function limpiarSeleccion() {
  for (const k of Object.keys(seleccion)) delete seleccion[k]
}

async function cargarOferta(conservar = false) {
  if (!store.idExpediente || !idCiclo.value) { oferta.value = null; return }
  cargando.value = true
  error.value = ''
  try {
    oferta.value = (await api.get('/academico/mi-oferta', { expediente: store.idExpediente, ciclo: idCiclo.value })).data
    if (conservar === true) {
      // Mantiene solo grupos que siguen existiendo en la oferta.
      const validos = new Set((oferta.value.materias ?? []).flatMap((m) => (m.grupos ?? []).map((g) => g.id_grupo)))
      for (const [k, v] of Object.entries(seleccion)) if (!validos.has(v)) delete seleccion[k]
    } else {
      limpiarSeleccion()
      aplicarIniciales()
      for (const m of oferta.value.materias ?? []) {
        const inscrito = m.grupos?.find((g) => g.inscrito)
        if (inscrito && !seleccion[m.id_materia]) seleccion[m.id_materia] = inscrito.id_grupo
      }
    }
  } catch (e) {
    error.value = mensajeError(e)
    oferta.value = null
  } finally {
    cargando.value = false
  }
}

async function validar() {
  validando.value = true
  validado.value = false
  erroresValidacion.value = []
  try {
    const r = await api.get('/academico/mi-oferta', { expediente: store.idExpediente, ciclo: idCiclo.value, grupos: idsGrupos.value })
    erroresValidacion.value = r.data?.seleccion?.errores ?? []
    validado.value = !erroresValidacion.value.length
  } catch (e) {
    erroresValidacion.value = [{ message: mensajeError(e) }]
  } finally {
    validando.value = false
  }
}

async function confirmar() {
  confirmando.value = true
  errorConfirmar.value = ''
  resultado.value = null
  try {
    const r = await postIdempotente('/academico/mis-inscripciones', { id_expediente: store.idExpediente, id_ciclo: idCiclo.value, grupos: idsGrupos.value }, clave)
    resultado.value = Array.isArray(r.data) ? r.data : (r.data?.inscripciones ?? [])
    if (!resultado.value.length) resultado.value = elegidos.value.map((e) => ({ materia: e.materia, grupo: e.grupo, estado: 'activa' }))
    clave = nuevaClave()
    await cargarOferta(true)
  } catch (e) {
    errorConfirmar.value = mensajeError(e)
    if (e?.status === 409) { clave = nuevaClave(); await cargarOferta(true) }
  } finally {
    confirmando.value = false
  }
}

async function iniciar() {
  try {
    if (!store.perfil) await store.cargarPerfil(route.query.expediente)
  } catch (e) {
    error.value = mensajeError(e)
    return
  }
  await cargarCiclos()
}

async function cargarCiclos() {
  if (!store.idExpediente) return
  try {
    const lista = (await api.get('/academico/mi-calendario', { expediente: store.idExpediente })).data ?? []
    ciclos.value = lista.filter((c) => c.estado === 'publicado').map((c) => ({ ...c, etiqueta: `${c.codigo} · ${c.nombre}` }))
    if (!ciclos.value.some((c) => c.id_ciclo === idCiclo.value)) idCiclo.value = ciclos.value[0]?.id_ciclo ?? null
    else await cargarOferta()
  } catch (e) {
    error.value = mensajeError(e)
  }
}

watch(idCiclo, () => cargarOferta())
watch(() => store.idExpediente, (n, o) => { if (o) { idCiclo.value = null; oferta.value = null; cargarCiclos() } })
onMounted(iniciar)
</script>
