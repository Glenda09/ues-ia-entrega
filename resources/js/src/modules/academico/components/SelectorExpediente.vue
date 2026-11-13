<template>
  <div v-if="expedientes.length > 1" class="flex items-center gap-2">
    <label for="selector-expediente" class="text-sm font-medium">Expediente</label>
    <Select inputId="selector-expediente" :modelValue="store.idExpediente" :options="opciones" optionLabel="etiqueta" optionValue="id" class="min-w-64" @update:modelValue="store.seleccionar($event)" />
  </div>
  <p v-else-if="expedientes.length === 1" class="text-sm text-secondary">{{ etiqueta(expedientes[0]) }}</p>
</template>

<script setup>
/**
 * Selector del expediente activo; usa el store compartido.
 */
import { computed } from 'vue'
import { Select } from 'primevue'
import { useExpedienteSeleccionado } from '../stores/expedienteSeleccionado'

const store = useExpedienteSeleccionado()
const expedientes = computed(() => store.perfil?.expedientes ?? [])
const etiqueta = (e) => `${e.carrera} (${e.pensum})`
const opciones = computed(() => expedientes.value.map((e) => ({ id: e.id_expediente, etiqueta: etiqueta(e) })))
</script>
