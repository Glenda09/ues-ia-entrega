<template>
  <div>
    <Encabezado contexto="GEN-01" titulo="Docentes" descripcion="Vincula cuentas existentes como docentes con código institucional. Desactivar bloquea nuevas asignaciones y la operación del docente.">
      <Button label="Nuevo docente" icon="pi pi-plus" @click="abrir()" />
    </Encabezado>
    <TablaPaginada ref="tabla" url="/academico/docentes" clave="id_docente" :filtros="filtros" :columnas="[{ campo: 'codigo', titulo: 'Código' }, { campo: 'nombre_academico', titulo: 'Nombre académico' }, { campo: 'cuenta.email', titulo: 'Cuenta' }, { campo: 'estado', titulo: 'Estado' }]">
      <template #filtros="{ recargar }">
        <InputText v-model="filtros.buscar" placeholder="Código o nombre" aria-label="Buscar" @keyup.enter="recargar" />
        <Select v-model="filtros.estado" :options="['activo', 'inactivo']" showClear placeholder="Estado" aria-label="Estado" @change="recargar" />
        <Button label="Filtrar" outlined @click="recargar" />
      </template>
      <template #celda-estado="{ fila }"><EstadoTag :valor="fila.estado" /></template>
      <template #acciones="{ fila }">
        <Button text size="small" icon="pi pi-pencil" aria-label="Editar" @click="abrir(fila)" />
        <Button text size="small" :label="fila.estado === 'activo' ? 'Desactivar' : 'Reactivar'" @click="alternar(fila)" />
      </template>
    </TablaPaginada>

    <Dialog v-model:visible="dialogo" modal :header="editando ? 'Editar docente' : 'Nuevo docente'" class="w-full max-w-lg">
      <form class="space-y-4" @submit.prevent="guardar">
        <div v-if="!editando">
          <label for="cuenta" class="mb-1 block text-sm font-medium">Cuenta (correo o usuario)</label>
          <AutoComplete inputId="cuenta" v-model="cuenta" :suggestions="cuentas" optionLabel="email" class="w-full" inputClass="w-full" @complete="buscarCuentas" placeholder="Escribe al menos 2 caracteres" />
          <p class="mt-1 text-xs text-secondary">La cuenta se crea en Administración → Usuarios.</p>
        </div>
        <div v-if="!editando">
          <label for="codigo" class="mb-1 block text-sm font-medium">Código institucional</label>
          <InputText id="codigo" v-model="form.codigo" class="w-full" />
        </div>
        <div>
          <label for="nombre" class="mb-1 block text-sm font-medium">Nombre académico</label>
          <InputText id="nombre" v-model="form.nombre_academico" class="w-full" />
        </div>
        <p v-if="errorForm" role="alert" class="text-sm text-red-600">{{ errorForm }}</p>
        <div class="flex justify-end gap-2">
          <Button type="button" text label="Cancelar" @click="dialogo = false" />
          <Button type="submit" label="Guardar" :loading="ocupado" />
        </div>
      </form>
    </Dialog>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { AutoComplete, Button, Dialog, InputText, Select } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoTag from '../components/EstadoTag.vue'
import TablaPaginada from '../components/TablaPaginada.vue'
import { api, mensajeError } from '../services/academico'

const tabla = ref()
const filtros = reactive({ buscar: '', estado: null })
const dialogo = ref(false)
const editando = ref(null)
const ocupado = ref(false)
const errorForm = ref('')
const cuenta = ref(null)
const cuentas = ref([])
const form = reactive({ codigo: '', nombre_academico: '' })

function abrir(fila = null) {
  editando.value = fila
  form.codigo = fila?.codigo ?? ''
  form.nombre_academico = fila?.nombre_academico ?? ''
  cuenta.value = null
  errorForm.value = ''
  dialogo.value = true
}

async function buscarCuentas(e) {
  if (e.query.length < 2) return
  cuentas.value = (await api.get('/academico/cuentas', { buscar: e.query })).data
}

async function guardar() {
  ocupado.value = true
  errorForm.value = ''
  try {
    if (editando.value) {
      await api.patch(`/academico/docentes/${editando.value.id_docente}`, { version: editando.value.version, nombre_academico: form.nombre_academico })
    } else {
      await api.post('/academico/docentes', { id_usuario: cuenta.value?.id_usuario, codigo: form.codigo, nombre_academico: form.nombre_academico })
    }
    dialogo.value = false
    tabla.value.cargar()
  } catch (e) {
    errorForm.value = mensajeError(e)
  } finally {
    ocupado.value = false
  }
}

async function alternar(fila) {
  try {
    const r = await api.patch(`/academico/docentes/${fila.id_docente}`, { version: fila.version, estado: fila.estado === 'activo' ? 'inactivo' : 'activo' })
    if (r.meta?.grupos_reasignar?.length) window.alert(`Grupos publicados que requieren reasignación: ${r.meta.grupos_reasignar.join(', ')}`)
  } catch (e) {
    window.alert(mensajeError(e))
  }
  tabla.value.cargar()
}
</script>
