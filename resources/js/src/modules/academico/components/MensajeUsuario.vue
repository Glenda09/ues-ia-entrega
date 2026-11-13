<template>
  <article class="flex justify-end" aria-label="Tu mensaje">
    <p class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-tr-sm bg-brand px-4 py-2.5 text-brand-contrast shadow-sm" style="overflow-wrap: anywhere">{{ contenido }}</p>
  </article>
</template>

<script setup>
/** Burbuja del mensaje del usuario (texto plano, sin interpretar Markdown). */
defineProps({ contenido: { type: String, default: '' } })
</script>
