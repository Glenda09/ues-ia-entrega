<template>
  <div class="mx-auto max-w-4xl p-4">
    <header class="mb-6 flex flex-wrap items-center justify-between gap-2 border-b border-secondary-100 pb-3">
      <nav aria-label="Navegación pública" class="flex flex-wrap gap-4 text-sm">
        <RouterLink to="/carreras" class="underline">Carreras</RouterLink>
        <RouterLink to="/asistente-aspirantes" class="underline">Asistente de aspirantes</RouterLink>
        <RouterLink to="/login" class="underline">Iniciar sesión</RouterLink>
      </nav>
    </header>
    <h1 class="mb-1 text-2xl font-semibold">Admisión</h1>
    <p class="mb-6 text-secondary">Convocatorias abiertas. Crea tu cuenta de aspirante y completa tu solicitud en línea.</p>
    <RouterLink to="/admision/registro"><Button label="Crear cuenta de aspirante" icon="pi pi-user-plus" class="mb-6" /></RouterLink>
    <EstadoVista :cargando="cargando" :error="error" :vacio="!lista.length" textoVacio="Por ahora no hay convocatorias abiertas." :reintentar="cargar">
      <div class="grid gap-4 md:grid-cols-2">
        <article v-for="c in lista" :key="c.id_convocatoria" class="rounded-xl border border-secondary-100 bg-surface-card p-4">
          <h2 class="text-lg font-semibold">{{ c.titulo_publico }}</h2>
          <p class="text-sm text-secondary">{{ c.carrera?.nombre }}</p>
          <p class="my-2 text-sm">{{ c.descripcion_publica }}</p>
          <p class="text-xs text-secondary">Del {{ fecha(c.inicio) }} al {{ fecha(c.fin) }} ({{ c.zona }})</p>
          <p class="text-xs text-secondary">{{ c.requiere_pago ? `Costo de inscripción: $${c.monto_pago}` : 'Sin costo de inscripción' }}</p>
          <RouterLink :to="`/admision/convocatorias/${c.id_convocatoria}`"><Button class="mt-3" size="small" label="Ver requisitos y aplicar" /></RouterLink>
        </article>
      </div>
    </EstadoVista>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { Button } from 'primevue'
import EstadoVista from '../components/EstadoVista.vue'
import { api, fecha, mensajeError } from '../services/academico'

const lista = ref([])
const cargando = ref(true)
const error = ref('')

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    lista.value = (await api.get('/publico/convocatorias')).data
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)
</script>
