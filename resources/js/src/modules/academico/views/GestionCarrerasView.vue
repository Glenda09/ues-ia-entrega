<template>
  <div>
    <Encabezado contexto="GEN-02" titulo="Gestión de carreras" descripcion="Ciclos, calendarios, ofertas y convocatorias de las carreras que gestionas." />
    <EstadoVista :cargando="cargando" :error="error" :vacio="!carreras.length" textoVacio="No gestionas ninguna carrera." :reintentar="cargar">
      <section v-for="c in carreras" :key="c.id_carrera" class="mb-6 rounded-xl border border-secondary-100 bg-surface-card p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-lg font-semibold">{{ c.codigo }} · {{ c.nombre }} <EstadoTag :valor="c.estado" /></h2>
          <div class="flex gap-2">
            <RouterLink :to="`/academico/gestion-carreras/${c.id_carrera}/convocatorias`"><Button outlined size="small" label="Convocatorias" /></RouterLink>
            <Button size="small" icon="pi pi-plus" label="Nuevo ciclo" @click="abrir(c)" />
          </div>
        </div>
        <p v-if="c.errorCiclos" role="alert" class="text-sm text-red-600">{{ c.errorCiclos }}</p>
        <p v-else-if="!c.ciclos" class="text-sm text-secondary">Cargando ciclos…</p>
        <p v-else-if="!c.ciclos.length" class="text-sm text-secondary">Sin ciclos registrados.</p>
        <ul v-else class="divide-y divide-secondary-100">
          <li v-for="ci in c.ciclos" :key="ci.id_ciclo_carrera" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
            <div>
              <RouterLink class="font-medium text-primary underline" :to="`/academico/gestion-carreras/${c.id_carrera}/ciclos/${ci.id_ciclo_carrera}`">{{ ci.codigo }} · {{ ci.nombre }}</RouterLink>
              <span class="ml-2 text-secondary">{{ fecha(ci.calendario?.inicio, 'DD/MM/YYYY') }} – {{ fecha(ci.calendario?.fin, 'DD/MM/YYYY') }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <EstadoTag :valor="ci.estado" />
              <span>Inscripción:</span><EstadoTag :valor="ci.ventanas_estado?.inscripcion ?? ''" />
              <span>Notas:</span><EstadoTag :valor="ci.ventanas_estado?.notas ?? ''" />
            </div>
          </li>
        </ul>
      </section>
    </EstadoVista>

    <Dialog v-model:visible="dialogo" modal :header="`Nuevo ciclo — ${actual?.nombre ?? ''}`" class="w-full max-w-md">
      <form class="space-y-4" @submit.prevent="crear">
        <div><label for="cc" class="mb-1 block text-sm font-medium">Código</label><InputText id="cc" v-model="form.codigo" class="w-full" /></div>
        <div><label for="cn" class="mb-1 block text-sm font-medium">Nombre</label><InputText id="cn" v-model="form.nombre" class="w-full" /></div>
        <p v-if="errorForm" role="alert" class="text-sm text-red-600">{{ errorForm }}</p>
        <div class="flex justify-end gap-2">
          <Button type="button" text label="Cancelar" @click="dialogo = false" />
          <Button type="submit" label="Crear" :loading="ocupado" />
        </div>
      </form>
    </Dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Button, Dialog, InputText } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import { api, fecha, mensajeError } from '../services/academico'

const router = useRouter()
const carreras = ref([])
const cargando = ref(true)
const error = ref('')
const dialogo = ref(false)
const actual = ref(null)
const ocupado = ref(false)
const errorForm = ref('')
const form = reactive({ codigo: '', nombre: '' })

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    carreras.value = (await api.get('/academico/mis-carreras-gestionadas', { per_page: 100 })).data
    carreras.value.forEach(async (c) => {
      try {
        c.ciclos = (await api.get(`/academico/carreras/${c.id_carrera}/ciclos`)).data
      } catch (e) {
        c.errorCiclos = mensajeError(e)
      }
    })
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

function abrir(c) {
  actual.value = c
  form.codigo = ''
  form.nombre = ''
  errorForm.value = ''
  dialogo.value = true
}

async function crear() {
  ocupado.value = true
  errorForm.value = ''
  try {
    const r = await api.post(`/academico/carreras/${actual.value.id_carrera}/ciclos`, { codigo: form.codigo, nombre: form.nombre })
    dialogo.value = false
    router.push(`/academico/gestion-carreras/${actual.value.id_carrera}/ciclos/${r.data.id_ciclo_carrera}`)
  } catch (e) {
    errorForm.value = mensajeError(e)
  } finally {
    ocupado.value = false
  }
}

onMounted(cargar)
</script>
