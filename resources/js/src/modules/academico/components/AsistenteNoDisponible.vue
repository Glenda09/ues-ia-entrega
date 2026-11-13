<template>
  <section role="status" aria-live="polite" class="mx-auto flex max-w-xl flex-col items-center gap-3 rounded-xl border border-secondary-100 bg-surface-card px-6 py-12 text-center">
    <i class="pi pi-comments text-4xl text-secondary" aria-hidden="true"></i>
    <h2 class="text-lg font-semibold">El asistente no está disponible por ahora</h2>
    <p class="text-secondary">{{ mensaje }}</p>
    <div v-if="$slots.default" class="mt-2 flex flex-wrap justify-center gap-3">
      <slot />
    </div>
  </section>
</template>

<script setup>
/**
 * Mensaje amigable que reemplaza la interfaz de chat cuando la IA no está disponible
 * (presupuesto agotado o configuración incompleta). No revela montos ni motivos internos.
 */
defineProps({
  mensaje: { type: String, default: 'Estamos trabajando para habilitarlo de nuevo. Vuelve a intentarlo más tarde.' }
})
</script>
