<template>
  <div class="mx-auto max-w-5xl p-4">
    <header class="mb-6 flex flex-col gap-3 border-b border-secondary-100 pb-4 md:flex-row md:items-end md:justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Carreras</h1>
        <p class="mt-1 text-secondary">Oferta académica vigente de la universidad.</p>
      </div>
      <nav aria-label="Navegación pública" class="flex flex-wrap gap-4 text-sm font-medium">
        <RouterLink to="/asistente-aspirantes" class="underline">Asistente para aspirantes</RouterLink>
        <RouterLink to="/admision" class="underline">Admisión</RouterLink>
        <RouterLink to="/login" class="underline">Iniciar sesión</RouterLink>
      </nav>
    </header>
    <EstadoVista :cargando="cargando" :error="error" :vacio="!carreras.length" textoVacio="No hay carreras publicadas por ahora." :reintentar="cargar">
      <ul class="grid gap-4 md:grid-cols-2">
        <li v-for="c in carreras" :key="c.id_carrera" class="rounded-xl border border-secondary-100 bg-surface-card p-4">
          <h2 class="text-lg font-semibold"><RouterLink :to="c.url || `/carreras/${c.id_carrera}`" class="underline">{{ c.titulo }}</RouterLink></h2>
          <p v-if="c.resumen" class="mt-1 text-sm text-secondary">{{ c.resumen }}</p>
          <p v-if="c.version" class="mt-2 text-xs text-secondary">Versión {{ c.version }}</p>
        </li>
      </ul>
    </EstadoVista>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import EstadoVista from '../components/EstadoVista.vue'
import { api, mensajeError } from '../services/academico'

const carreras = ref([])
const cargando = ref(true)
const error = ref('')

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    carreras.value = (await api.get('/publico/carreras')).data ?? []
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)
</script>
