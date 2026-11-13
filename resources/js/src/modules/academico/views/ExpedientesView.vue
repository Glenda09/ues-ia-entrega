<template>
  <div>
    <Encabezado contexto="EXP-01" titulo="Expedientes estudiantiles" descripcion="Consulta expedientes y registra admisiones directas.">
      <Button v-if="can('academico_solicitud_decidir')" label="Admisión directa" icon="pi pi-user-plus" @click="abrir" />
    </Encabezado>
    <TablaPaginada ref="tabla" url="/academico/expedientes" clave="id_expediente" :filtros="filtros" :columnas="[{ campo: 'carnet', titulo: 'Carnet' }, { campo: 'nombre', titulo: 'Nombre' }, { campo: 'carrera', titulo: 'Carrera' }, { campo: 'pensum', titulo: 'Pensum' }, { campo: 'estado_etiqueta', titulo: 'Estado' }]">
      <template #filtros="{ recargar }">
        <InputText v-model="filtros.buscar" placeholder="Carnet o nombre" aria-label="Buscar" @keyup.enter="recargar" />
        <Select v-model="filtros.estado" :options="ESTADOS" optionLabel="n" optionValue="v" showClear placeholder="Estado" aria-label="Estado" @change="recargar" />
        <Button label="Filtrar" outlined @click="recargar" />
      </template>
      <template #celda-carrera="{ fila }">{{ fila.carrera?.nombre ?? fila.carrera ?? '—' }}</template>
      <template #celda-pensum="{ fila }">{{ fila.pensum?.codigo ?? fila.pensum?.nombre ?? fila.pensum ?? '—' }}</template>
      <template #celda-estado_etiqueta="{ fila }"><EstadoTag :valor="fila.estado ?? ''" :texto="fila.estado_etiqueta" /></template>
      <template #acciones="{ fila }"><RouterLink :to="`/academico/expedientes/${fila.id_expediente}`"><Button text size="small" label="Abrir" /></RouterLink></template>
    </TablaPaginada>

    <Dialog v-model:visible="dialogo" modal header="Admisión directa" class="w-full max-w-lg">
      <form class="space-y-3" @submit.prevent="guardar">
        <div v-for="c in CAMPOS" :key="c.k"><label :for="`ad-${c.k}`" class="mb-1 block text-sm font-medium">{{ c.t }}</label><InputText :id="`ad-${c.k}`" v-model="form[c.k]" :type="c.type ?? 'text'" class="w-full" /></div>
        <div><label for="ad-carrera" class="mb-1 block text-sm font-medium">Carrera</label><Select v-model="form.id_carrera" inputId="ad-carrera" :options="carreras" optionLabel="nombre" optionValue="id_carrera" filter placeholder="Selecciona una carrera" class="w-full" /></div>
        <div><label for="ad-fecha" class="mb-1 block text-sm font-medium">Fecha de ingreso</label><input id="ad-fecha" v-model="form.fecha_ingreso" type="date" class="w-full rounded border border-secondary-100 p-2" /></div>
        <p v-if="errorForm" role="alert" class="text-sm text-red-600">{{ errorForm }}</p>
        <div class="flex justify-end gap-2"><Button type="button" text label="Cancelar" @click="dialogo = false" /><Button type="submit" label="Registrar" :loading="ocupado" /></div>
      </form>
    </Dialog>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Button, Dialog, InputText, Select } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoTag from '../components/EstadoTag.vue'
import TablaPaginada from '../components/TablaPaginada.vue'
import { api, mensajeError } from '../services/academico'
import { useValidatePermission } from '@/shared/composables/useValidatePermission'

const { can } = useValidatePermission()
const router = useRouter()
const ESTADOS = [{ v: 'ACTIVO', n: 'Activo' }, { v: 'INACTIVO', n: 'Inactivo' }, { v: 'EGRESADO', n: 'Egresado' }, { v: 'RETIRADO', n: 'Retirado' }]
const CAMPOS = [{ k: 'nombres', t: 'Nombres' }, { k: 'primer_apellido', t: 'Primer apellido' }, { k: 'segundo_apellido', t: 'Segundo apellido' }, { k: 'email', t: 'Correo electrónico', type: 'email' }]
const tabla = ref()
const filtros = reactive({ buscar: '', estado: null })
const dialogo = ref(false)
const ocupado = ref(false)
const errorForm = ref('')
const carreras = ref([])
const form = reactive({ nombres: '', primer_apellido: '', segundo_apellido: '', email: '', id_carrera: null, fecha_ingreso: '' })

async function abrir() {
  Object.assign(form, { nombres: '', primer_apellido: '', segundo_apellido: '', email: '', id_carrera: null, fecha_ingreso: new Date().toISOString().slice(0, 10) })
  errorForm.value = ''
  dialogo.value = true
  if (!carreras.value.length) {
    try {
      carreras.value = (await api.get('/academico/mis-carreras-gestionadas', { per_page: 100 })).data
    } catch (e) {
      errorForm.value = mensajeError(e)
    }
  }
}

async function guardar() {
  ocupado.value = true
  errorForm.value = ''
  try {
    const r = await api.post('/academico/expedientes', { ...form })
    dialogo.value = false
    const id = r.data?.id_expediente
    if (id) router.push(`/academico/expedientes/${id}`)
    else tabla.value.cargar()
  } catch (e) {
    errorForm.value = mensajeError(e)
  } finally {
    ocupado.value = false
  }
}
</script>
