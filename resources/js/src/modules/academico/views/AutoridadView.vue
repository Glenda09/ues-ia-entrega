<template>
  <div>
    <Encabezado contexto="DOC-01" titulo="Autoridad documental" descripcion="Titular que firma certificaciones y constancias institucionales." />
    <EstadoVista :cargando="cargando" :error="error" :reintentar="cargar">
      <section class="mb-6 rounded-xl border border-secondary-100 bg-surface-card p-4">
        <h2 class="mb-2 text-lg font-semibold">Titular vigente</h2>
        <template v-if="d?.titular">
          <p class="text-sm">{{ d.titular.nombre }} · {{ d.titular.cargo }} · {{ d.titular.email }} <Tag :value="d.valida ? 'Válida' : 'No válida'" :severity="d.valida ? 'success' : 'danger'" /></p>
          <p v-if="!d.valida && d.error" role="alert" class="mt-1 text-sm text-red-600">{{ d.error }}</p>
        </template>
        <p v-else class="text-sm text-secondary">No hay titular configurado. No se podrán emitir documentos.</p>
      </section>

      <section class="mb-6 rounded-xl border border-secondary-100 bg-surface-card p-4">
        <h2 class="mb-2 text-lg font-semibold">{{ d?.titular ? 'Cambiar titular' : 'Designar titular' }}</h2>
        <form class="space-y-3" @submit.prevent="guardar">
          <div>
            <label for="cuenta" class="mb-1 block text-sm font-medium">Cuenta</label>
            <AutoComplete v-model="cuenta" inputId="cuenta" :suggestions="cuentas" optionLabel="email" class="w-full" inputClass="w-full" placeholder="Escribe al menos 2 caracteres" @complete="buscar" />
          </div>
          <div><label for="nombre" class="mb-1 block text-sm font-medium">Nombre que aparecerá en el documento</label><InputText id="nombre" v-model="form.nombre" class="w-full" /></div>
          <div><label for="cargo" class="mb-1 block text-sm font-medium">Cargo</label><InputText id="cargo" v-model="form.cargo" class="w-full" /></div>
          <p v-if="errorForm" role="alert" class="text-sm text-red-600">{{ errorForm }}</p>
          <Button type="submit" label="Guardar autoridad" :loading="ocupado" :disabled="!cuenta?.id_usuario" />
        </form>
      </section>

      <section v-if="d?.historial?.length" class="rounded-xl border border-secondary-100 bg-surface-card p-4">
        <h2 class="mb-2 text-lg font-semibold">Historial</h2>
        <ul class="space-y-1 text-sm"><li v-for="(h, i) in d.historial" :key="i">{{ h.nombre }} · {{ h.cargo }} <span v-if="h.email" class="text-secondary">({{ h.email }})</span> <span class="text-secondary">{{ fecha(h.desde ?? h.instante ?? h.creado_en) }}</span></li></ul>
      </section>
    </EstadoVista>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { AutoComplete, Button, InputText, Tag } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import { api, fecha, mensajeError } from '../services/academico'

const d = ref(null)
const cargando = ref(true)
const error = ref('')
const errorForm = ref('')
const ocupado = ref(false)
const cuenta = ref(null)
const cuentas = ref([])
const form = reactive({ nombre: '', cargo: '' })

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    d.value = (await api.get('/academico/documentos/autoridad')).data
    form.nombre = d.value.titular?.nombre ?? ''
    form.cargo = d.value.titular?.cargo ?? ''
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

async function buscar(ev) {
  if (ev.query.length < 2) return
  try {
    cuentas.value = (await api.get('/academico/cuentas', { buscar: ev.query })).data
  } catch (e) {
    errorForm.value = mensajeError(e)
  }
}

async function guardar() {
  ocupado.value = true
  errorForm.value = ''
  try {
    await api.put('/academico/documentos/autoridad', { id_usuario: cuenta.value.id_usuario, nombre: form.nombre, cargo: form.cargo })
    cuenta.value = null
    await cargar()
  } catch (e) {
    errorForm.value = mensajeError(e)
  } finally {
    ocupado.value = false
  }
}

onMounted(cargar)
</script>
