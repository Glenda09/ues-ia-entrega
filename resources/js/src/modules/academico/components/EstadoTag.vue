<template>
  <Tag :value="etiqueta" :severity="SEVERIDAD[valor] ?? 'secondary'" class="whitespace-nowrap" />
</template>

<script setup>
/**
 * Etiqueta de estado con color consistente en todo el módulo académico.
 */
import { computed } from 'vue'
import { Tag } from 'primevue'
import { SEVERIDAD, ESTADOS_ESTUDIANTE } from '../services/academico'

const props = defineProps({ valor: { type: String, default: '' }, texto: { type: String, default: '' } })
const etiqueta = computed(() => props.texto || ESTADOS_ESTUDIANTE[props.valor] || (props.valor ?? '').replaceAll('_', ' '))
</script>
