<template>
  <div>
    <p v-if="cargando" role="status" class="py-10 text-center text-secondary">
      <i class="pi pi-spin pi-spinner mr-2" aria-hidden="true"></i>Cargando…
    </p>
    <div v-else-if="error" role="alert" class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-800 dark:bg-red-950 dark:text-red-200">
      <p class="font-semibold">{{ titulo }}</p>
      <p class="mt-1 text-sm">{{ error }}</p>
      <Button v-if="reintentar" class="mt-3" size="small" outlined label="Reintentar" @click="reintentar" />
    </div>
    <p v-else-if="vacio" class="rounded-lg border border-dashed border-secondary-100 py-10 text-center text-secondary">{{ textoVacio }}</p>
    <slot v-else />
  </div>
</template>

<script setup>
/**
 * Estados de vista: carga, error (incluye acceso denegado), vacío y contenido.
 */
import { computed } from 'vue'
import { Button } from 'primevue'

const props = defineProps({
  cargando: Boolean,
  error: { type: [String, Object], default: '' },
  vacio: Boolean,
  textoVacio: { type: String, default: 'No hay registros para mostrar.' },
  reintentar: { type: Function, default: null }
})
const titulo = computed(() => (/permis|403|denegad/i.test(String(props.error)) ? 'Acceso denegado' : 'No se pudo completar la operación'))
</script>
