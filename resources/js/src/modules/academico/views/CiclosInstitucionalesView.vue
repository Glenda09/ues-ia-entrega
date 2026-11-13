<template>
  <div>
    <Encabezado contexto="GEN-02" titulo="Ciclos institucionales" descripcion="Vista consolidada de ciclos de todas las carreras." />
    <TablaPaginada url="/academico/ciclos-institucionales" clave="id_ciclo_carrera" :filtros="filtros" :columnas="[{ campo: 'carrera.nombre', titulo: 'Carrera' }, { campo: 'codigo', titulo: 'Código' }, { campo: 'nombre', titulo: 'Nombre' }, { campo: 'estado', titulo: 'Estado' }, { campo: 'ventanas_estado.inscripcion', titulo: 'Inscripción' }, { campo: 'ventanas_estado.notas', titulo: 'Notas' }, { campo: 'grupos_publicados', titulo: 'Grupos pub.' }, { campo: 'inscripciones_activas', titulo: 'Inscripciones' }, { campo: 'estudiantes_distintos', titulo: 'Estudiantes' }]">
      <template #filtros="{ recargar }">
        <InputText v-model="filtros.codigo" placeholder="Código" aria-label="Código" @keyup.enter="recargar" />
        <Select v-model="filtros.estado" :options="['borrador', 'publicado', 'cerrado']" showClear placeholder="Estado" aria-label="Estado" @change="recargar" />
        <Button label="Filtrar" outlined @click="recargar" />
      </template>
      <template #celda-estado="{ fila }"><EstadoTag :valor="fila.estado" /></template>
      <template #[slotInscripcion]="{ fila }"><EstadoTag :valor="fila.ventanas_estado?.inscripcion ?? ''" /></template>
      <template #[slotNotas]="{ fila }"><EstadoTag :valor="fila.ventanas_estado?.notas ?? ''" /></template>
      <template #acciones="{ fila }"><Button text size="small" label="Detalle" @click="abrir(fila)" /></template>
    </TablaPaginada>

    <Dialog v-model:visible="dialogo" modal :header="detalle ? `Ciclo ${detalle.codigo} · ${detalle.nombre}` : 'Detalle del ciclo'" class="w-full max-w-4xl">
      <EstadoVista :cargando="cargando" :error="error" :vacio="false" :reintentar="() => abrir(seleccion)">
        <template v-if="detalle">
          <p class="mb-3 text-sm text-secondary">{{ detalle.carrera?.nombre }}</p>
          <p v-if="!detalle.grupos?.length" class="text-secondary">Sin grupos.</p>
          <table v-else class="w-full text-sm">
            <thead class="text-left text-secondary"><tr><th scope="col" class="px-2 py-1">Materia</th><th scope="col" class="px-2 py-1">Grupo</th><th scope="col" class="px-2 py-1">Estado</th><th scope="col" class="px-2 py-1">Cupo</th><th scope="col" class="px-2 py-1">Inscripciones activas</th></tr></thead>
            <tbody><tr v-for="(g, i) in detalle.grupos" :key="g.id_grupo ?? i" class="border-t border-secondary-100"><td class="px-2 py-1">{{ g.materia?.codigo ?? '' }} {{ g.materia?.nombre ?? g.materia ?? '' }}</td><td class="px-2 py-1">{{ g.codigo }}</td><td class="px-2 py-1"><EstadoTag :valor="g.estado" /></td><td class="px-2 py-1">{{ g.cupo }}</td><td class="px-2 py-1">{{ g.inscripciones_activas }}</td></tr></tbody>
          </table>
        </template>
      </EstadoVista>
    </Dialog>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Button, Dialog, InputText, Select } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import TablaPaginada from '../components/TablaPaginada.vue'
import { api, mensajeError } from '../services/academico'

const filtros = reactive({ codigo: '', estado: null })
const slotInscripcion = 'celda-ventanas_estado.inscripcion'
const slotNotas = 'celda-ventanas_estado.notas'
const dialogo = ref(false)
const detalle = ref(null)
const cargando = ref(false)
const error = ref('')
const seleccion = ref(null)

async function abrir(fila) {
  seleccion.value = fila
  detalle.value = null
  error.value = ''
  dialogo.value = true
  cargando.value = true
  try {
    detalle.value = (await api.get(`/academico/ciclos-institucionales/${fila.id_ciclo_carrera}`)).data
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}
</script>
