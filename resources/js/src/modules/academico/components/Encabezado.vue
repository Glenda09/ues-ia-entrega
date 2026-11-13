<template>
  <header class="mb-6 flex flex-col gap-4 border-b border-secondary-100 pb-5 md:flex-row md:items-end md:justify-between">
    <div>
      <p v-if="contexto" class="mb-1 text-xs font-semibold uppercase tracking-widest text-secondary">{{ contexto }}</p>
      <h1 class="text-2xl font-semibold">{{ titulo }}</h1>
      <p v-if="descripcion" class="mt-1 max-w-3xl text-secondary">{{ descripcion }}</p>
    </div>
    <div class="flex flex-wrap gap-2"><slot /></div>
  </header>
</template>

<script setup>
defineProps({ titulo: { type: String, required: true }, descripcion: { type: String, default: '' }, contexto: { type: String, default: '' } })
</script>
