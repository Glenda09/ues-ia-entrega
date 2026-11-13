<template>
  <div>
    <Encabezado contexto="ADM-02" titulo="Solicitudes de admisión" descripcion="Revisa y decide las solicitudes de las carreras que gestionas." />
    <TablaPaginada url="/academico/solicitudes" clave="id_solicitud" :filtros="filtros" :columnas="[{ campo: 'folio', titulo: 'Folio' }, { campo: 'nombre', titulo: 'Aspirante' }, { campo: 'carrera', titulo: 'Carrera' }, { campo: 'estado', titulo: 'Estado' }, { campo: 'revision_actual', titulo: 'Revisión' }, { campo: 'creado_en', titulo: 'Creada' }]">
      <template #filtros="{ recargar }">
        <Select v-model="filtros.estado" :options="['recibida', 'observada', 'aceptada']" showClear placeholder="Estado" aria-label="Estado" @change="recargar" />
        <Button label="Filtrar" outlined @click="recargar" />
      </template>
      <template #celda-carrera="{ fila }">{{ fila.carrera?.nombre ?? fila.carrera ?? '—' }}</template>
      <template #celda-estado="{ fila }"><EstadoTag :valor="fila.estado" /></template>
      <template #celda-creado_en="{ fila }">{{ fecha(fila.creado_en) }}</template>
      <template #acciones="{ fila }"><RouterLink :to="`/academico/solicitudes/${fila.id_solicitud ?? fila.id}`"><Button text size="small" label="Revisar" /></RouterLink></template>
    </TablaPaginada>
  </div>
</template>

<script setup>
import { reactive } from 'vue'
import { Button, Select } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoTag from '../components/EstadoTag.vue'
import TablaPaginada from '../components/TablaPaginada.vue'
import { fecha } from '../services/academico'

const filtros = reactive({ estado: null })
</script>
