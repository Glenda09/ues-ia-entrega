<template>
  <div>
    <Encabezado contexto="Portal docente" titulo="Mis grupos" descripcion="Grupos asignados, estado del período de notas y del acta." />
    <TablaPaginada url="/academico/mis-grupos" clave="id_grupo" textoVacio="No tienes grupos asignados." :columnas="[{ campo: 'materia.codigo', titulo: 'Código' }, { campo: 'materia.nombre', titulo: 'Materia' }, { campo: 'codigo', titulo: 'Grupo' }, { campo: 'ciclo.nombre', titulo: 'Ciclo' }, { campo: 'carrera.nombre', titulo: 'Carrera' }, { campo: 'ocupacion', titulo: 'Inscritos' }, { campo: 'ventana_notas.estado', titulo: 'Período de notas' }, { campo: 'acta.estado', titulo: 'Acta' }]">
      <template #celda-ocupacion="{ fila }">{{ fila.inscritos ?? 0 }} / {{ fila.cupo ?? '—' }}</template>
      <template #celda-ventana_notas.estado="{ fila }"><EstadoTag v-if="fila.ventana_notas?.estado" :valor="fila.ventana_notas.estado" /><span v-else>—</span></template>
      <template #celda-acta.estado="{ fila }"><EstadoTag v-if="fila.acta?.estado" :valor="fila.acta.estado" /><span v-else>—</span></template>
      <template #acciones="{ fila }">
        <RouterLink :to="`/docente/grupos/${fila.id_grupo}`" class="mr-3 text-sm font-medium underline">Ver grupo</RouterLink>
        <RouterLink :to="`/docente/grupos/${fila.id_grupo}/notas`" class="text-sm font-medium underline">Notas</RouterLink>
      </template>
    </TablaPaginada>
  </div>
</template>

<script setup>
import Encabezado from '../components/Encabezado.vue'
import EstadoTag from '../components/EstadoTag.vue'
import TablaPaginada from '../components/TablaPaginada.vue'
</script>
