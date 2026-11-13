<template>
  <div>
    <Encabezado contexto="GEN-02" :titulo="titulo" :descripcion="descripcion">
      <Button v-if="puedeGestionar" label="Nuevo" icon="pi pi-plus" @click="abrir()" />
    </Encabezado>
    <div v-for="cat in catalogos" :key="cat" :class="catalogos.length > 1 ? 'mb-10' : ''">
      <h2 v-if="catalogos.length > 1" class="mb-3 text-lg font-semibold">{{ nombres[cat] }}</h2>
      <TablaPaginada :ref="(el) => (tablas[cat] = el)" :url="`/academico/${cat}`" :clave="`id_${singular[cat]}`" :filtros="filtros[cat]" :columnas="columnas(cat)">
        <template #filtros="{ recargar }">
          <InputText v-model="filtros[cat].buscar" placeholder="Código o nombre" aria-label="Buscar" @keyup.enter="recargar" />
          <Select v-model="filtros[cat].estado" :options="['activa', 'archivada']" showClear placeholder="Estado" aria-label="Estado" @change="recargar" />
          <Button v-if="catalogos.length > 1 && puedeGestionar" label="Nuevo" outlined size="small" @click="abrir(null, cat)" />
        </template>
        <template #celda-estado="{ fila }"><EstadoTag :valor="fila.estado" /></template>
        <template #celda-sedes="{ fila }">{{ (fila.sedes ?? []).map((s) => s.nombre).join(', ') || '—' }}</template>
        <template #celda-facultad="{ fila }">{{ fila.facultad?.nombre ?? '—' }}</template>
        <template #acciones="{ fila }">
          <RouterLink v-if="cat === 'carreras'" :to="`/academico/administracion/carreras/${fila.id_carrera}/pensums`" class="mr-2 text-sm text-primary underline">Ficha y pensum</RouterLink>
          <Button v-if="puedeGestionar" text size="small" icon="pi pi-pencil" aria-label="Editar" @click="abrir(fila, cat)" />
          <Button v-if="puedeGestionar" text size="small" :label="fila.estado === 'activa' ? 'Archivar' : 'Reactivar'" @click="archivar(fila, cat)" />
        </template>
      </TablaPaginada>
    </div>

    <Dialog v-model:visible="dialogo" modal :header="editando ? 'Editar registro' : 'Nuevo registro'" class="w-full max-w-xl">
      <form class="space-y-4" @submit.prevent="guardar">
        <div class="grid gap-4 md:grid-cols-2">
          <div><label for="c-codigo" class="mb-1 block text-sm font-medium">Código</label><InputText id="c-codigo" v-model="form.codigo" class="w-full" /></div>
          <div><label for="c-nombre" class="mb-1 block text-sm font-medium">Nombre</label><InputText id="c-nombre" v-model="form.nombre" class="w-full" /></div>
        </div>
        <template v-if="['carreras', 'materias'].includes(actual)">
          <div><label for="c-desc" class="mb-1 block text-sm font-medium">Descripción interna</label><Textarea id="c-desc" v-model="form.descripcion" rows="2" class="w-full" /></div>
        </template>
        <template v-if="actual === 'carreras'">
          <div><label for="c-pub" class="mb-1 block text-sm font-medium">Descripción pública (visible a aspirantes)</label><Textarea id="c-pub" v-model="form.descripcion_publica" rows="3" class="w-full" /></div>
          <div class="grid gap-4 md:grid-cols-2">
            <div><label for="c-clas" class="mb-1 block text-sm font-medium">Clasificación</label><Select inputId="c-clas" v-model="form.clasificacion" :options="['general', 'profesorado']" class="w-full" /></div>
            <div><label for="c-fac" class="mb-1 block text-sm font-medium">Facultad</label><Select inputId="c-fac" v-model="form.id_facultad" :options="facultades" optionLabel="nombre" optionValue="id_facultad" showClear class="w-full" /></div>
          </div>
          <div><label for="c-sedes" class="mb-1 block text-sm font-medium">Sedes</label><MultiSelect inputId="c-sedes" v-model="form.sedes" :options="sedes" optionLabel="nombre" optionValue="id_sede" class="w-full" /></div>
        </template>
        <p v-if="errorForm" role="alert" class="text-sm text-red-600">{{ errorForm }}</p>
        <div class="flex justify-end gap-2"><Button type="button" text label="Cancelar" @click="dialogo = false" /><Button type="submit" label="Guardar" :loading="ocupado" /></div>
      </form>
    </Dialog>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Button, Dialog, InputText, MultiSelect, Select, Textarea } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoTag from '../components/EstadoTag.vue'
import TablaPaginada from '../components/TablaPaginada.vue'
import { api, mensajeError } from '../services/academico'
import { useValidatePermission } from '@/shared/composables/useValidatePermission'

const route = useRoute()
const { can } = useValidatePermission()
const puedeGestionar = computed(() => can('academico_catalogo_gestionar'))
const catalogos = computed(() => route.meta.catalogos)
const nombres = { carreras: 'Carreras', materias: 'Materias', sedes: 'Sedes', facultades: 'Facultades' }
const singular = { carreras: 'carrera', materias: 'materia', sedes: 'sede', facultades: 'facultad' }
const titulo = computed(() => catalogos.value.map((c) => nombres[c]).join(' y '))
const descripcion = computed(() => 'Códigos normalizados únicos. Archivar conserva el historial e impide nuevos usos; los registros con operaciones vigentes no se archivan.')
const filtros = reactive({ carreras: { buscar: '', estado: null }, materias: { buscar: '', estado: null }, sedes: { buscar: '', estado: null }, facultades: { buscar: '', estado: null } })
const tablas = reactive({})
const dialogo = ref(false)
const editando = ref(null)
const actual = ref('carreras')
const ocupado = ref(false)
const errorForm = ref('')
const sedes = ref([])
const facultades = ref([])
const form = reactive({ codigo: '', nombre: '', descripcion: '', descripcion_publica: '', clasificacion: 'general', id_facultad: null, sedes: [] })

function columnas(cat) {
  const base = [{ campo: 'codigo', titulo: 'Código' }, { campo: 'nombre', titulo: 'Nombre' }]
  if (cat === 'carreras') base.push({ campo: 'facultad', titulo: 'Facultad' }, { campo: 'sedes', titulo: 'Sedes' })
  return [...base, { campo: 'estado', titulo: 'Estado' }]
}

async function abrir(fila = null, cat = catalogos.value[0]) {
  actual.value = cat
  editando.value = fila
  Object.assign(form, { codigo: fila?.codigo ?? '', nombre: fila?.nombre ?? '', descripcion: fila?.descripcion ?? '', descripcion_publica: fila?.descripcion_publica ?? '', clasificacion: fila?.clasificacion ?? 'general', id_facultad: fila?.id_facultad ?? null, sedes: (fila?.sedes ?? []).map((s) => s.id_sede) })
  errorForm.value = ''
  if (cat === 'carreras') {
    sedes.value = (await api.get('/academico/sedes', { per_page: 100, estado: 'activa' })).data
    facultades.value = (await api.get('/academico/facultades', { per_page: 100, estado: 'activa' })).data
  }
  dialogo.value = true
}

async function guardar() {
  ocupado.value = true
  errorForm.value = ''
  const body = { codigo: form.codigo, nombre: form.nombre }
  if (['carreras', 'materias'].includes(actual.value)) body.descripcion = form.descripcion || null
  if (actual.value === 'carreras') Object.assign(body, { descripcion_publica: form.descripcion_publica || null, clasificacion: form.clasificacion, id_facultad: form.id_facultad, sedes: form.sedes })
  try {
    if (editando.value) {
      const id = editando.value[`id_${singular[actual.value]}`]
      if (body.codigo === editando.value.codigo) delete body.codigo
      await api.patch(`/academico/${actual.value}/${id}`, { version: editando.value.version, ...body })
    } else {
      await api.post(`/academico/${actual.value}`, body)
    }
    dialogo.value = false
    tablas[actual.value]?.cargar()
  } catch (e) {
    errorForm.value = mensajeError(e)
  } finally {
    ocupado.value = false
  }
}

async function archivar(fila, cat) {
  try {
    await api.patch(`/academico/${cat}/${fila[`id_${singular[cat]}`]}`, { version: fila.version, estado: fila.estado === 'activa' ? 'archivada' : 'activa' })
  } catch (e) {
    window.alert(mensajeError(e))
  }
  tablas[cat]?.cargar()
}
</script>
