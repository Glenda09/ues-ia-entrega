<template>
  <section class="min-h-screen flex items-center justify-center bg-surface p-6">
    <div class="bg-surface-card rounded-xl shadow-lg max-w-md w-full p-8 text-center">
      <div class="text-6xl font-extrabold tracking-wider text-text">404</div>
      <h1 class="mt-2 text-2xl font-bold text-text">Página no encontrada</h1>
      <p class="mt-2 text-secondary">Lo sentimos, no pudimos encontrar la ruta que buscabas.</p>
      <div class="mt-5">
        <RouterLink
          :to="{ name: routeName }"
          class="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-brand-contrast hover:bg-brand-button-hover active:translate-y-px transition"
        >
          {{ routeTitle && !token ? `Ir a ${routeTitle}` : 'Regresar' }}
        </RouterLink>
      </div>
    </div>
  </section>
</template>

<script setup>
const routeName = import.meta.env.VITE_PROJECT_BASE_URL_NAME || 'landing'
const routeTitle = import.meta.env.VITE_PROJECT_BASE_URL_TITLE || 'Inicio'
const token = localStorage.getItem('access_token') || null
</script>
