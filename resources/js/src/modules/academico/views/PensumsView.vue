<template>
  <div>
    <Encabezado contexto="GEN-02 · GEN-03" :titulo="carrera ? carrera.nombre : 'Carrera'" descripcion="Ficha de carrera: gestores asignados y versiones de pensum con materias por nivel y prerrequisitos.">
      <RouterLink to="/academico/administracion/carreras" class="text-sm text-primary underline">← Carreras</RouterLink>
    </Encabezado>
    <EstadoVista :cargando="cargando" :error="error" :reintentar="cargar">
      <div class="grid gap-6 xl:grid-cols-[18rem_minmax(0,1fr)]">
        <aside class="space-y-6">
          <section class="rounded-xl border border-secondary-100 bg-surface-card p-4">
            <h2 class="mb-2 font-semibold">Versiones</h2>
            <ul class="space-y-1">
              <li v-for="p in pensums" :key="p.id_pensum">
                <button type="button" class="flex w-full items-center justify-between rounded px-2 py-1 text-left hover:bg-secondary-100" :class="sel?.id_pensum === p.id_pensum ? 'bg-secondary-100 font-semibold' : ''" @click="abrir(p.id_pensum)">
                  <span>{{ p.version_curricular }}</span><EstadoTag :valor="p.estado" />
                </button>
              </li>
            </ul>
            <form v-if="puedeGestionar" class="mt-3 space-y-2" @submit.prevent="crear">
              <InputText v-model="nueva.version_curricular" placeholder="Nueva versión (ej. 2027)" class="w-full" aria-label="Nueva versión" />
              <Select v-model="nueva.id_pensum_origen" :options="pensums" optionLabel="version_curricular" optionValue="id_pensum" showClear placeholder="Clonar desde (opcional)" class="w-full" aria-label="Clonar desde" />
              <Button type="submit" size="small" label="Crear borrador" class="w-full" />
            </form>
          </section>
          <section v-if="puedeGestores" class="rounded-xl border border-secondary-100 bg-surface-card p-4">
            <h2 class="mb-2 font-semibold">Gestores</h2>
            <ul class="space-y-1 text-sm">
              <li v-for="g in gestores" :key="g.id_usuario" class="flex items-center justify-between gap-2">
                <span class="truncate">{{ g.email }}</span>
                <Button text size="small" icon="pi pi-times" aria-label="Revocar" @click="revocar(g)" />
              </li>
              <li v-if="!gestores.length" class="text-secondary">Sin gestores.</li>
            </ul>
            <AutoComplete v-model="cuenta" :suggestions="cuentas" optionLabel="email" class="mt-2 w-full" inputClass="w-full" placeholder="Asignar por correo" aria-label="Asignar gestor" @complete="buscar" @option-select="asignar" />
            <p class="mt-1 text-xs text-secondary">La cuenta requiere perfil de gestor de carrera o de expediente.</p>
          </section>
        </aside>

        <section v-if="sel" class="rounded-xl border border-secondary-100 bg-surface-card p-5">
          <div class="mb-4 flex flex-wrap items-center gap-3">
            <h2 class="text-xl font-semibold">Pensum {{ sel.version_curricular }}</h2>
            <EstadoTag :valor="sel.estado" />
            <span class="text-sm text-secondary">Créditos obligatorios: {{ sel.creditos_obligatorios }} · v{{ sel.version }}</span>
            <div class="ml-auto flex gap-2" v-if="puedeGestionar">
              <Button v-if="sel.estado === 'borrador'" size="small" label="Publicar" icon="pi pi-check" @click="transicion('publicado')" />
              <Button v-if="sel.estado === 'publicado'" size="small" severity="secondary" label="Retirar" @click="transicion('retirado')" />
            </div>
          </div>
          <p v-if="errorAccion" role="alert" class="mb-3 rounded border border-red-300 p-3 text-sm text-red-700">{{ errorAccion }}</p>

          <template v-if="editable">
            <div class="mb-4 grid gap-3 md:grid-cols-2">
              <div><label for="desde" class="mb-1 block text-sm font-medium">Vigente desde</label><InputText id="desde" v-model="edicion.vigente_desde" type="date" class="w-full" /></div>
              <div><label for="hasta" class="mb-1 block text-sm font-medium">Vigente hasta (opcional)</label><InputText id="hasta" v-model="edicion.vigente_hasta" type="date" class="w-full" /></div>
            </div>
            <table class="mb-3 w-full text-sm">
              <thead class="text-left text-secondary"><tr><th class="py-1">Materia</th><th>Nivel</th><th>Créditos</th><th>Obligatoria</th><th>Prerrequisitos</th><th></th></tr></thead>
              <tbody>
                <tr v-for="(m, i) in edicion.materias" :key="m.id_materia" class="border-t border-secondary-100">
                  <td class="py-1">{{ nombreMateria(m.id_materia) }}</td>
                  <td><InputNumber v-model="m.nivel" :min="1" :max="20" inputClass="w-16" :aria-label="`Nivel ${i}`" /></td>
                  <td><InputNumber v-model="m.creditos" :min="0.5" :max="99" :minFractionDigits="0" :maxFractionDigits="2" inputClass="w-20" :aria-label="`Créditos ${i}`" /></td>
                  <td><Checkbox v-model="m.obligatoria" binary :aria-label="`Obligatoria ${i}`" /></td>
                  <td><MultiSelect v-model="m.prerrequisitos" :options="edicion.materias.filter((x) => x.id_materia !== m.id_materia)" :optionLabel="(x) => nombreMateria(x.id_materia)" optionValue="id_materia" class="w-56" placeholder="Ninguno" :aria-label="`Prerrequisitos ${i}`" /></td>
                  <td><Button text size="small" icon="pi pi-trash" aria-label="Quitar" @click="edicion.materias.splice(i, 1)" /></td>
                </tr>
              </tbody>
            </table>
            <div class="flex flex-wrap items-center gap-2">
              <Select v-model="agregar" :options="materiasDisponibles" :optionLabel="(m) => `${m.codigo} · ${m.nombre}`" optionValue="id_materia" filter placeholder="Agregar materia" class="w-72" aria-label="Agregar materia" />
              <Button size="small" outlined label="Agregar" :disabled="!agregar" @click="agregarMateria" />
              <Button size="small" class="ml-auto" label="Guardar borrador" icon="pi pi-save" @click="guardar" />
            </div>
          </template>

          <div v-else class="space-y-4">
            <p class="text-sm text-secondary">Vigencia: {{ sel.vigente_desde ?? '—' }} → {{ sel.vigente_hasta ?? 'abierta' }}. Una versión publicada es inmutable; clónala para preparar cambios.</p>
            <div v-for="(materias, nivel) in porNivel" :key="nivel">
              <h3 class="mb-1 font-semibold">Nivel {{ nivel }}</h3>
              <ul class="grid gap-2 md:grid-cols-2">
                <li v-for="m in materias" :key="m.id_materia" class="rounded border border-secondary-100 p-2 text-sm">
                  <b>{{ m.codigo }}</b> {{ m.nombre }} · {{ m.creditos }} cr. <span v-if="!m.obligatoria" class="text-secondary">(optativa)</span>
                  <span v-if="m.requisitos.length" class="block text-xs text-secondary">Requiere: {{ m.requisitos.map((r) => r.codigo).join(', ') }}</span>
                </li>
              </ul>
            </div>
          </div>
        </section>
        <p v-else class="text-secondary">Selecciona o crea una versión de pensum.</p>
      </div>
    </EstadoVista>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { AutoComplete, Button, Checkbox, InputNumber, InputText, MultiSelect, Select } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import { api, mensajeError } from '../services/academico'
import { useValidatePermission } from '@/shared/composables/useValidatePermission'

const route = useRoute()
const id = computed(() => route.params.carrera)
const { can } = useValidatePermission()
const puedeGestionar = computed(() => can('academico_pensum_gestionar'))
const puedeGestores = computed(() => can('academico_gestor_asignar'))
const cargando = ref(true)
const error = ref('')
const errorAccion = ref('')
const carrera = ref(null)
const pensums = ref([])
const gestores = ref([])
const materias = ref([])
const sel = ref(null)
const nueva = reactive({ version_curricular: '', id_pensum_origen: null })
const edicion = reactive({ vigente_desde: '', vigente_hasta: '', materias: [] })
const agregar = ref(null)
const cuenta = ref(null)
const cuentas = ref([])

const editable = computed(() => sel.value?.estado === 'borrador' && puedeGestionar.value)
const porNivel = computed(() => (sel.value?.materias ?? []).reduce((a, m) => ((a[m.nivel] ??= []).push(m), a), {}))
const materiasDisponibles = computed(() => materias.value.filter((m) => !edicion.materias.some((x) => x.id_materia === m.id_materia)))

function nombreMateria(idM) {
  const m = materias.value.find((x) => x.id_materia === idM)
  return m ? `${m.codigo} · ${m.nombre}` : idM
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    carrera.value = (await api.get(`/academico/carreras/${id.value}`)).data
    pensums.value = (await api.get(`/academico/carreras/${id.value}/pensums`, { per_page: 100 })).data
    if (puedeGestionar.value) materias.value = (await api.get('/academico/materias', { per_page: 100, estado: 'activa' })).data
    if (puedeGestores.value) gestores.value = (await api.get(`/academico/carreras/${id.value}/gestores`)).data
    if (pensums.value.length && !sel.value) await abrir(pensums.value[pensums.value.length - 1].id_pensum)
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

async function abrir(idP) {
  errorAccion.value = ''
  sel.value = (await api.get(`/academico/pensums/${idP}`)).data
  edicion.vigente_desde = sel.value.vigente_desde ?? ''
  edicion.vigente_hasta = sel.value.vigente_hasta ?? ''
  edicion.materias = sel.value.materias.map((m) => ({ id_materia: m.id_materia, nivel: m.nivel, creditos: m.creditos, obligatoria: m.obligatoria, prerrequisitos: m.requisitos.filter((r) => r.tipo === 'prerrequisito').map((r) => r.id_materia) }))
  for (const m of sel.value.materias) if (!materias.value.some((x) => x.id_materia === m.id_materia)) materias.value.push({ id_materia: m.id_materia, codigo: m.codigo, nombre: m.nombre })
}

function agregarMateria() {
  edicion.materias.push({ id_materia: agregar.value, nivel: 1, creditos: 4, obligatoria: true, prerrequisitos: [] })
  agregar.value = null
}

async function guardar() {
  errorAccion.value = ''
  try {
    const r = await api.patch(`/academico/pensums/${sel.value.id_pensum}`, {
      version: sel.value.version,
      vigente_desde: edicion.vigente_desde || null,
      vigente_hasta: edicion.vigente_hasta || null,
      materias: edicion.materias.map((m, i) => ({ id_materia: m.id_materia, nivel: m.nivel, orden: i + 1, creditos: m.creditos, obligatoria: m.obligatoria })),
      requisitos: edicion.materias.flatMap((m) => m.prerrequisitos.map((p) => ({ id_materia: m.id_materia, id_materia_requisito: p, tipo: 'prerrequisito' })))
    })
    await abrir(r.data.id_pensum)
  } catch (e) {
    errorAccion.value = mensajeError(e)
  }
}

async function transicion(estado) {
  errorAccion.value = ''
  try {
    await api.post(`/academico/pensums/${sel.value.id_pensum}/transiciones`, { estado, version: sel.value.version })
    await abrir(sel.value.id_pensum)
    pensums.value = (await api.get(`/academico/carreras/${id.value}/pensums`, { per_page: 100 })).data
  } catch (e) {
    errorAccion.value = mensajeError(e)
  }
}

async function crear() {
  errorAccion.value = ''
  try {
    const r = await api.post(`/academico/carreras/${id.value}/pensums`, { version_curricular: nueva.version_curricular, id_pensum_origen: nueva.id_pensum_origen })
    nueva.version_curricular = ''
    nueva.id_pensum_origen = null
    pensums.value = (await api.get(`/academico/carreras/${id.value}/pensums`, { per_page: 100 })).data
    await abrir(r.data.id_pensum)
  } catch (e) {
    errorAccion.value = mensajeError(e)
  }
}

async function buscar(e) {
  if (e.query.length >= 2) cuentas.value = (await api.get('/academico/cuentas', { buscar: e.query })).data
}

async function asignar(e) {
  try {
    await api.put(`/academico/carreras/${id.value}/gestores/${e.value.id_usuario}`)
  } catch (err) {
    errorAccion.value = mensajeError(err)
  }
  cuenta.value = null
  gestores.value = (await api.get(`/academico/carreras/${id.value}/gestores`)).data
}

async function revocar(g) {
  if (!window.confirm(`¿Revocar a ${g.email}?`)) return
  await api.del(`/academico/carreras/${id.value}/gestores/${g.id_usuario}`)
  gestores.value = (await api.get(`/academico/carreras/${id.value}/gestores`)).data
}

onMounted(cargar)
</script>
