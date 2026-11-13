<template>
  <div>
    <Encabezado contexto="GEN-02" :titulo="ciclo ? `Ciclo ${ciclo.codigo} · ${ciclo.nombre}` : 'Ciclo'" descripcion="Calendario, ventanas de inscripción y notas, y oferta de grupos.">
      <RouterLink to="/academico/gestion-carreras"><Button outlined label="Volver" icon="pi pi-arrow-left" /></RouterLink>
      <Button v-if="ciclo?.estado === 'borrador'" label="Publicar" icon="pi pi-check" :loading="ocupado" @click="transicion('publicado')" />
      <Button v-if="ciclo?.estado === 'publicado'" severity="secondary" label="Cerrar" icon="pi pi-lock" :loading="ocupado" @click="transicion('cerrado')" />
    </Encabezado>
    <EstadoVista :cargando="cargando" :error="error" :reintentar="cargar">
      <template v-if="ciclo">
        <p v-if="errorCiclo" role="alert" class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ errorCiclo }}</p>
        <section class="mb-8 rounded-xl border border-secondary-100 bg-surface-card p-4">
          <div class="mb-3 flex items-center gap-3"><EstadoTag :valor="ciclo.estado" /><span class="text-sm text-secondary">Zona horaria: {{ form.zona }} · versión {{ ciclo.version }}</span></div>
          <form class="space-y-4" @submit.prevent="guardar">
            <div><label for="nombre" class="mb-1 block text-sm font-medium">Nombre</label><InputText id="nombre" v-model="form.nombre" class="w-full max-w-md" /></div>
            <div class="grid gap-3 md:grid-cols-2">
              <div><label for="cal-ini" class="mb-1 block text-sm font-medium">Inicio del ciclo</label><input id="cal-ini" v-model="form.inicio" type="datetime-local" class="w-full rounded border border-secondary-100 p-2" /></div>
              <div><label for="cal-fin" class="mb-1 block text-sm font-medium">Fin del ciclo</label><input id="cal-fin" v-model="form.fin" type="datetime-local" class="w-full rounded border border-secondary-100 p-2" /></div>
            </div>
            <fieldset v-for="v in VENTANAS" :key="v.k" class="rounded-lg border border-secondary-100 p-3">
              <legend class="px-1 text-sm font-semibold">Ventana de {{ v.t }}</legend>
              <div class="grid gap-3 md:grid-cols-3">
                <div><label :for="`${v.k}-i`" class="mb-1 block text-sm">Inicio</label><input :id="`${v.k}-i`" v-model="form.ventanas[v.k].inicio" type="datetime-local" class="w-full rounded border border-secondary-100 p-2" /></div>
                <div><label :for="`${v.k}-r`" class="mb-1 block text-sm">Fin regular</label><input :id="`${v.k}-r`" v-model="form.ventanas[v.k].fin_regular" type="datetime-local" class="w-full rounded border border-secondary-100 p-2" /></div>
                <div><label :for="`${v.k}-g`" class="mb-1 block text-sm">Fin de gracia</label><input :id="`${v.k}-g`" v-model="form.ventanas[v.k].fin_gracia" type="datetime-local" class="w-full rounded border border-secondary-100 p-2" /></div>
              </div>
              <p class="mt-2 text-xs text-secondary">
                Regular: {{ txt(form.ventanas[v.k].inicio) }} → {{ txt(form.ventanas[v.k].fin_regular) }}.
                Gracia: {{ txt(form.ventanas[v.k].fin_regular) }} → {{ txt(form.ventanas[v.k].fin_gracia) }}.
                Estado actual: {{ ciclo.ventanas_estado?.[v.k] ?? '—' }}.
              </p>
            </fieldset>
            <div v-if="ciclo.estado === 'publicado'"><label for="motivo" class="mb-1 block text-sm font-medium">Motivo del ajuste (obligatorio en ciclos publicados)</label><Textarea id="motivo" v-model="form.motivo" rows="2" class="w-full" /></div>
            <Button type="submit" label="Guardar calendario" :loading="ocupado" />
          </form>
          <div v-if="ciclo.ajustes?.length" class="mt-5">
            <h3 class="mb-2 font-semibold">Historial de ajustes</h3>
            <ul class="space-y-1 text-sm"><li v-for="a in ciclo.ajustes" :key="a.version_resultante">v{{ a.version_resultante }} · {{ fecha(a.instante) }} — {{ a.motivo }}</li></ul>
          </div>
        </section>

        <section>
          <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-xl font-semibold">Oferta de materias y grupos</h2>
            <div class="flex items-end gap-2">
              <div>
                <label for="mat" class="mb-1 block text-sm">Agregar materia del pensum publicado</label>
                <Select v-model="materiaSel" inputId="mat" :options="materiasDisponibles" optionLabel="etiqueta" optionValue="id_materia" filter placeholder="Selecciona una materia" class="w-72" />
              </div>
              <Button label="Agregar materia" icon="pi pi-plus" :disabled="!materiaSel" @click="agregarMateria" />
            </div>
          </div>
          <p v-if="errorOferta" role="alert" class="mb-3 text-sm text-red-600">{{ errorOferta }}</p>
          <EstadoVista :cargando="cargandoOferta" :error="errorCargaOferta" :vacio="!ofertas.length" textoVacio="Aún no hay materias ofertadas." :reintentar="cargarOfertas">
            <article v-for="o in ofertas" :key="o.id_oferta_materia" class="mb-4 rounded-xl border border-secondary-100 bg-surface-card p-4">
              <div class="mb-2 flex items-center justify-between">
                <h3 class="font-semibold">{{ o.materia.codigo }} · {{ o.materia.nombre }}</h3>
                <Button size="small" outlined icon="pi pi-plus" label="Nuevo grupo" @click="abrirGrupo(o)" />
              </div>
              <p v-if="!o.grupos?.length" class="text-sm text-secondary">Sin grupos.</p>
              <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="text-left text-secondary"><tr><th scope="col" class="px-2 py-1">Grupo</th><th scope="col" class="px-2 py-1">Docente</th><th scope="col" class="px-2 py-1">Horario</th><th scope="col" class="px-2 py-1">Cupo</th><th scope="col" class="px-2 py-1">Estado</th><th scope="col" class="px-2 py-1 text-right">Acciones</th></tr></thead>
                  <tbody>
                    <tr v-for="g in o.grupos" :key="g.id_grupo" class="border-t border-secondary-100">
                      <td class="px-2 py-1">{{ g.codigo }}</td>
                      <td class="px-2 py-1">{{ g.docente?.nombre_academico ?? 'Sin docente' }}</td>
                      <td class="px-2 py-1">{{ (g.horarios ?? []).map((h) => `${DIAS[h.dia - 1]} ${h.hora_inicio}-${h.hora_fin}`).join('; ') || '—' }}</td>
                      <td class="px-2 py-1">{{ g.inscritos }}/{{ g.cupo }} ({{ g.disponibles }} disp.)</td>
                      <td class="px-2 py-1"><EstadoTag :valor="g.estado" /></td>
                      <td class="px-2 py-1 text-right whitespace-nowrap">
                        <Button text size="small" icon="pi pi-pencil" aria-label="Editar grupo" @click="abrirGrupo(o, g)" />
                        <Button v-if="g.estado === 'borrador'" text size="small" label="Publicar" @click="estadoGrupo(g, 'publicado')" />
                        <Button v-if="g.estado !== 'cancelado'" text size="small" severity="danger" label="Cancelar" @click="estadoGrupo(g, 'cancelado')" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </article>
          </EstadoVista>
        </section>
      </template>
    </EstadoVista>

    <Dialog v-model:visible="dialogoGrupo" modal :header="grupoEdit ? `Editar grupo ${grupoEdit.codigo}` : 'Nuevo grupo'" class="w-full max-w-2xl">
      <form class="space-y-4" @submit.prevent="guardarGrupo">
        <div v-if="!grupoEdit"><label for="gcod" class="mb-1 block text-sm font-medium">Código</label><InputText id="gcod" v-model="fg.codigo" class="w-full" /></div>
        <div><label for="gcupo" class="mb-1 block text-sm font-medium">Cupo</label><InputNumber v-model="fg.cupo" inputId="gcupo" :min="1" /></div>
        <div><label for="gdoc" class="mb-1 block text-sm font-medium">Docente</label><Select v-model="fg.id_docente" inputId="gdoc" :options="docentes" optionLabel="etiqueta" optionValue="id_docente" filter showClear placeholder="Selecciona un docente" class="w-full" /></div>
        <fieldset>
          <legend class="mb-1 text-sm font-medium">Horarios</legend>
          <div v-for="(h, i) in fg.horarios" :key="i" class="mb-2 flex flex-wrap items-center gap-2">
            <Select v-model="h.dia" :options="DIAS_OPC" optionLabel="n" optionValue="v" :aria-label="`Día ${i + 1}`" class="w-40" />
            <input v-model="h.hora_inicio" type="time" :aria-label="`Hora de inicio ${i + 1}`" class="rounded border border-secondary-100 p-2" />
            <input v-model="h.hora_fin" type="time" :aria-label="`Hora de fin ${i + 1}`" class="rounded border border-secondary-100 p-2" />
            <Button type="button" text severity="danger" icon="pi pi-trash" :aria-label="`Quitar horario ${i + 1}`" @click="fg.horarios.splice(i, 1)" />
          </div>
          <Button type="button" outlined size="small" icon="pi pi-plus" label="Agregar horario" @click="fg.horarios.push({ dia: 1, hora_inicio: '07:00', hora_fin: '09:00' })" />
        </fieldset>
        <p v-if="errorGrupo" role="alert" class="text-sm text-red-600">{{ errorGrupo }}</p>
        <div class="flex justify-end gap-2"><Button type="button" text label="Cancelar" @click="dialogoGrupo = false" /><Button type="submit" label="Guardar" :loading="ocupado" /></div>
      </form>
    </Dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Button, Dialog, InputNumber, InputText, Select, Textarea } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import { api, aLocal, deLocal, fecha, mensajeError } from '../services/academico'

const route = useRoute()
const carrera = route.params.carrera
const idCiclo = route.params.ciclo
const VENTANAS = [{ k: 'inscripcion', t: 'inscripción' }, { k: 'notas', t: 'notas' }]
const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']
const DIAS_OPC = DIAS.map((n, i) => ({ n, v: i + 1 }))

const ciclo = ref(null)
const cargando = ref(true)
const error = ref('')
const errorCiclo = ref('')
const ocupado = ref(false)
const form = reactive({ nombre: '', zona: '', motivo: '', inicio: '', fin: '', ventanas: { inscripcion: {}, notas: {} } })

const ofertas = ref([])
const cargandoOferta = ref(true)
const errorCargaOferta = ref('')
const errorOferta = ref('')
const materias = ref([])
const materiaSel = ref(null)
const docentes = ref([])

const materiasDisponibles = computed(() => {
  const usadas = new Set(ofertas.value.map((o) => o.id_materia))
  return materias.value.filter((m) => !usadas.has(m.id_materia))
})

const txt = (v) => (v ? fecha(v) : '—')

function llenar(c) {
  ciclo.value = c
  form.nombre = c.nombre
  form.zona = c.calendario?.zona ?? ''
  form.motivo = ''
  form.inicio = aLocal(c.calendario?.inicio)
  form.fin = aLocal(c.calendario?.fin)
  for (const v of VENTANAS) {
    const w = c.calendario?.ventanas?.[v.k] ?? {}
    form.ventanas[v.k] = { inicio: aLocal(w.inicio), fin_regular: aLocal(w.fin_regular), fin_gracia: aLocal(w.fin_gracia) }
  }
}

async function recargarCiclo() {
  llenar((await api.get(`/academico/carreras/${carrera}/ciclos/${idCiclo}`)).data)
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    await recargarCiclo()
    cargarOfertas()
    cargarMaterias()
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

async function guardar() {
  ocupado.value = true
  errorCiclo.value = ''
  const ventanas = {}
  for (const v of VENTANAS) {
    const w = form.ventanas[v.k]
    ventanas[v.k] = { inicio: deLocal(w.inicio), fin_regular: deLocal(w.fin_regular), fin_gracia: deLocal(w.fin_gracia) }
  }
  const body = { version: ciclo.value.version, nombre: form.nombre, calendario: { inicio: deLocal(form.inicio), fin: deLocal(form.fin), ventanas } }
  if (ciclo.value.estado === 'publicado') body.motivo = form.motivo
  try {
    await api.patch(`/academico/ciclos/${ciclo.value.id_ciclo_carrera}`, body)
    await recargarCiclo()
  } catch (e) {
    errorCiclo.value = mensajeError(e)
  } finally {
    ocupado.value = false
  }
}

async function transicion(estado) {
  ocupado.value = true
  errorCiclo.value = ''
  try {
    await api.post(`/academico/ciclos/${ciclo.value.id_ciclo_carrera}/transiciones`, { estado, version: ciclo.value.version })
    await recargarCiclo()
  } catch (e) {
    const codigos = { CONFIGURACION_INCOMPLETA: 'La configuración del ciclo está incompleta.', PENSUM_NO_PUBLICADO: 'La carrera no tiene un pensum publicado.', CIERRE_NO_PERMITIDO: 'El ciclo no puede cerrarse todavía.' }
    errorCiclo.value = [codigos[e?.code], mensajeError(e)].filter(Boolean).join(' ')
  } finally {
    ocupado.value = false
  }
}

async function cargarOfertas() {
  cargandoOferta.value = true
  errorCargaOferta.value = ''
  try {
    ofertas.value = (await api.get(`/academico/ciclos/${ciclo.value.id_ciclo_carrera}/ofertas`, { per_page: 100 })).data
  } catch (e) {
    errorCargaOferta.value = mensajeError(e)
  } finally {
    cargandoOferta.value = false
  }
}

async function cargarMaterias() {
  try {
    const pensums = (await api.get(`/academico/carreras/${carrera}/pensums`, { per_page: 100 })).data.filter((p) => p.estado === 'publicado')
    const detalles = await Promise.all(pensums.map((p) => api.get(`/academico/pensums/${p.id_pensum ?? p.id}`)))
    const mapa = new Map()
    detalles.forEach((d) => (d.data.materias ?? []).forEach((m) => mapa.set(m.id_materia, { ...m, etiqueta: `${m.codigo} · ${m.nombre}` })))
    materias.value = [...mapa.values()]
  } catch (e) {
    errorOferta.value = mensajeError(e)
  }
}

async function agregarMateria() {
  errorOferta.value = ''
  try {
    await api.post(`/academico/ciclos/${ciclo.value.id_ciclo_carrera}/ofertas`, { id_materia: materiaSel.value })
    materiaSel.value = null
    await cargarOfertas()
  } catch (e) {
    errorOferta.value = mensajeError(e)
  }
}

const dialogoGrupo = ref(false)
const grupoEdit = ref(null)
const ofertaActual = ref(null)
const errorGrupo = ref('')
const fg = reactive({ codigo: '', cupo: 30, id_docente: null, horarios: [] })

function erroresGrupo(e) {
  const m = { CUPO_INFERIOR_A_INSCRITOS: 'El cupo es inferior al número de inscritos.', GRUPO_CON_INSCRIPCIONES: 'El grupo tiene inscripciones activas.', DOCENTE_INACTIVO: 'El docente está inactivo.', HORARIO_AFECTA_INSCRIPCIONES: 'El cambio de horario afecta inscripciones existentes.' }
  return [m[e?.code], mensajeError(e)].filter(Boolean).join(' ')
}

async function abrirGrupo(o, g = null) {
  ofertaActual.value = o
  grupoEdit.value = g
  fg.codigo = g?.codigo ?? ''
  fg.cupo = g?.cupo ?? 30
  fg.id_docente = g?.docente?.id_docente ?? null
  fg.horarios = (g?.horarios ?? []).map((h) => ({ dia: h.dia, hora_inicio: h.hora_inicio?.slice(0, 5), hora_fin: h.hora_fin?.slice(0, 5) }))
  errorGrupo.value = ''
  dialogoGrupo.value = true
  if (!docentes.value.length) {
    try {
      docentes.value = (await api.get('/academico/docentes', { estado: 'activo', per_page: 100 })).data.map((d) => ({ ...d, etiqueta: `${d.codigo} · ${d.nombre_academico}` }))
    } catch (e) {
      errorGrupo.value = mensajeError(e)
    }
  }
}

async function guardarGrupo() {
  ocupado.value = true
  errorGrupo.value = ''
  try {
    if (grupoEdit.value) {
      await api.patch(`/academico/grupos/${grupoEdit.value.id_grupo}`, { version: grupoEdit.value.version, cupo: fg.cupo, id_docente: fg.id_docente, horarios: fg.horarios })
    } else {
      await api.post(`/academico/ofertas/${ofertaActual.value.id_oferta_materia}/grupos`, { codigo: fg.codigo, cupo: fg.cupo, id_docente: fg.id_docente, horarios: fg.horarios })
    }
    dialogoGrupo.value = false
    await cargarOfertas()
  } catch (e) {
    errorGrupo.value = erroresGrupo(e)
  } finally {
    ocupado.value = false
  }
}

async function estadoGrupo(g, estado) {
  errorOferta.value = ''
  try {
    await api.patch(`/academico/grupos/${g.id_grupo}`, { version: g.version, estado })
  } catch (e) {
    errorOferta.value = erroresGrupo(e)
  }
  await cargarOfertas()
}

onMounted(cargar)
</script>
