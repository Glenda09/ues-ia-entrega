<template>
  <div class="flex items-start gap-3" role="status" aria-live="polite">
    <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand text-brand-contrast" aria-hidden="true">
      <i class="pi pi-sparkles text-sm"></i>
    </span>
    <div class="rounded-2xl rounded-tl-sm border border-secondary-100 bg-surface-card px-4 py-3 shadow-sm">
      <span class="sr-only">El asistente está respondiendo…</span>
      <span class="puntos" aria-hidden="true"><span></span><span></span><span></span></span>
    </div>
  </div>
</template>

<script setup>
/** Indicador de escritura del asistente (accesible y respetuoso con reduced-motion). */
</script>

<style scoped>
.puntos { display: inline-flex; gap: 0.3rem; align-items: center; height: 1.25rem; }
.puntos span { width: 0.45rem; height: 0.45rem; border-radius: 999px; background: var(--color-secondary); opacity: 0.35; animation: latido 1.2s infinite ease-in-out; }
.puntos span:nth-child(2) { animation-delay: 0.15s; }
.puntos span:nth-child(3) { animation-delay: 0.3s; }
@keyframes latido { 0%, 80%, 100% { opacity: 0.35; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-2px); } }
@media (prefers-reduced-motion: reduce) { .puntos span { animation: none; opacity: 0.7; } }
</style>
