<template>
  <div>
    <Encabezado contexto="NOT-01" titulo="Actas de notas" descripcion="Actas de los grupos que tienes asignados o gestionas." />
    <TablaPaginada url="/academico/actas" clave="id_grupo" :filtros="filtros" :columnas="[{ campo: 'materia.codigo', titulo: 'Código' }, { campo: 'materia.nombre', titulo: 'Materia' }, { campo: 'codigo', titulo: 'Grupo' }, { campo: 'ciclo.nombre', titulo: 'Ciclo' }, { campo: 'carrera.nombre', titulo: 'Carrera' }, { campo: 'inscritos', titulo: 'Inscritos' }, { campo: 'situacion', titulo: 'Situación' }]">
      <template #filtros="{ recargar }">
        <Select v-model="filtros.estado" :options="['pendiente', 'calculada', 'publicada']" showClear placeholder="Estado del acta" aria-label="Estado del acta" @change="recargar" />
        <Button label="Filtrar" outlined @click="recargar" />
      </template>
      <template #celda-situacion="{ fila }"><EstadoTag :valor="fila.situacion ?? ''" /></template>
      <template #acciones="{ fila }"><RouterLink :to="`/academico/actas/${fila.id_grupo}`"><Button text size="small" label="Ver acta" /></RouterLink></template>
    </TablaPaginada>
  </div>
</template>

<script setup>
import { reactive } from 'vue'
import { Button, Select } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoTag from '../components/EstadoTag.vue'
import TablaPaginada from '../components/TablaPaginada.vue'

const filtros = reactive({ estado: null })
</script>
