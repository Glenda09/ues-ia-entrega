<template>
  <div class="mx-auto max-w-5xl p-4">
    <header class="mb-6 flex flex-col gap-3 border-b border-secondary-100 pb-4 md:flex-row md:items-end md:justify-between">
      <div>
        <RouterLink to="/carreras" class="text-sm underline">← Todas las carreras</RouterLink>
        <h1 class="mt-1 text-2xl font-semibold">{{ titulo }}</h1>
      </div>
      <nav aria-label="Navegación pública" class="flex flex-wrap gap-4 text-sm font-medium">
        <RouterLink to="/asistente-aspirantes" class="underline">Asistente para aspirantes</RouterLink>
        <RouterLink to="/admision" class="underline">Admisión</RouterLink>
        <RouterLink to="/login" class="underline">Iniciar sesión</RouterLink>
      </nav>
    </header>
    <EstadoVista :cargando="cargando" :error="error" :vacio="!carrera" textoVacio="No se encontró la carrera solicitada." :reintentar="cargar">
      <template v-if="carrera">
        <section v-if="contacto" aria-labelledby="t-con" class="mb-6 rounded-xl border border-secondary-100 bg-surface-card p-4">
          <h2 id="t-con" class="mb-2 text-lg font-semibold">Contacto</h2>
          <ul class="space-y-1 text-sm">
            <li v-if="contacto.contacto_email">Correo: <a :href="`mailto:${contacto.contacto_email}`" class="underline">{{ contacto.contacto_email }}</a></li>
            <li v-if="contacto.contacto_telefono">Teléfono: {{ contacto.contacto_telefono }}</li>
            <li v-if="contacto.contacto_url">Sitio: <a :href="contacto.contacto_url" class="underline" rel="noopener" target="_blank">{{ contacto.contacto_url }}</a></li>
          </ul>
        </section>
        <p v-if="!carrera.secciones?.length" class="text-secondary">Esta carrera aún no tiene secciones publicadas.</p>
        <section v-for="s in carrera.secciones" :id="s.ancla" :key="s.ancla" :aria-labelledby="`h-${s.ancla}`" class="mb-6 scroll-mt-4 rounded-xl border border-secondary-100 bg-surface-card p-4">
          <h2 :id="`h-${s.ancla}`" class="mb-2 text-lg font-semibold">{{ s.titulo }}</h2>
          <p class="whitespace-pre-line">{{ s.contenido }}</p>
          <p class="mt-2 text-xs text-secondary">Versión {{ s.version }}<span v-if="s.vigente_hasta"> · Vigente hasta {{ fecha(s.vigente_hasta, 'DD/MM/YYYY') }}</span></p>
        </section>
      </template>
    </EstadoVista>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import EstadoVista from '../components/EstadoVista.vue'
import { api, fecha, mensajeError } from '../services/academico'

const route = useRoute()
const carrera = ref(null)
const cargando = ref(true)
const error = ref('')
const contacto = computed(() => {
  const c = carrera.value?.contacto
  return c && (c.contacto_email || c.contacto_telefono || c.contacto_url) ? c : null
})
const titulo = computed(() => carrera.value?.titulo ?? carrera.value?.secciones?.[0]?.titulo ?? 'Carrera')

async function irAncla() {
  await nextTick()
  if (route.hash) document.getElementById(decodeURIComponent(route.hash.slice(1)))?.scrollIntoView()
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    carrera.value = (await api.get(`/publico/carreras/${route.params.carrera}`)).data
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
  irAncla()
}

watch(() => route.hash, irAncla)
watch(() => route.params.carrera, cargar)
onMounted(cargar)
</script>
