<template>
  <div class="mx-auto max-w-4xl p-4">
    <header class="mb-6 flex flex-wrap items-center justify-between gap-2 border-b border-secondary-100 pb-3">
      <nav aria-label="Navegación pública" class="flex flex-wrap gap-4 text-sm">
        <RouterLink to="/carreras" class="underline">Carreras</RouterLink>
        <RouterLink to="/asistente-aspirantes" class="underline">Asistente de aspirantes</RouterLink>
        <RouterLink to="/login" class="underline">Iniciar sesión</RouterLink>
      </nav>
    </header>
    <h1 class="mb-1 text-2xl font-semibold">Corrección de solicitud</h1>
    <p class="mb-6 text-secondary">Actualiza solo lo que se te pidió corregir. Deja vacío lo que no cambia.</p>

    <p v-if="!token" role="alert" class="rounded-lg border border-red-300 bg-red-50 p-3 text-red-800">El enlace de corrección no es válido. Usa el enlace completo que recibiste por correo.</p>
    <div v-else-if="resultado" role="status" class="rounded-lg border border-green-300 bg-green-50 p-4 text-green-900">
      <p class="font-semibold">Corrección recibida.</p>
      <p class="text-sm">Folio {{ resultado.folio }} · revisión {{ resultado.revision }}</p>
    </div>
    <form v-else class="space-y-4" @submit.prevent="enviar">
      <div class="grid gap-3 md:grid-cols-2">
        <div v-for="c in CAMPOS" :key="c.k"><label :for="`c-${c.k}`" class="mb-1 block text-sm font-medium">{{ c.t }}</label><InputText :id="`c-${c.k}`" v-model="datos[c.k]" class="w-full" /></div>
      </div>
      <fieldset class="space-y-2">
        <legend class="mb-1 text-sm font-medium">Documentos</legend>
        <div v-for="a in ARCHIVOS" :key="a.k"><label :for="`a-${a.k}`" class="mb-1 block text-sm">{{ a.t }}</label><input :id="`a-${a.k}`" type="file" accept=".pdf,.jpg,.jpeg,.png" @change="(ev) => (archivos[a.k] = ev.target.files[0] ?? null)" /></div>
      </fieldset>
      <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
      <Button type="submit" label="Enviar corrección" :loading="ocupado" />
    </form>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Button, InputText } from 'primevue'
import { mensajeError } from '../services/academico'
import http from '@/shared/services/http'

const CAMPOS = [{ k: 'telefono_celular', t: 'Teléfono celular' }, { k: 'direccion', t: 'Dirección' }, { k: 'nombres', t: 'Nombres' }, { k: 'primer_apellido', t: 'Primer apellido' }, { k: 'segundo_apellido', t: 'Segundo apellido' }]
const ARCHIVOS = [{ k: 'fotografia', t: 'Fotografía' }, { k: 'dui', t: 'DUI' }, { k: 'pasaporte', t: 'Pasaporte' }, { k: 'titulo_bachiller', t: 'Título de bachiller' }, { k: 'comprobante_pago', t: 'Comprobante de pago' }]

const token = ref(decodeURIComponent((window.location.hash || '').replace(/^#/, '')))
// Se retira el token de la barra de direcciones para no dejarlo en el historial visible.
if (token.value) window.history.replaceState(null, '', window.location.pathname + window.location.search)

const datos = reactive({})
const archivos = reactive({})
const ocupado = ref(false)
const error = ref('')
const resultado = ref(null)

async function enviar() {
  ocupado.value = true
  error.value = ''
  const fd = new FormData()
  fd.append('token', token.value)
  for (const [k, v] of Object.entries(datos)) if (v) fd.append(`datos[${k}]`, v)
  for (const [k, f] of Object.entries(archivos)) if (f) fd.append(`archivos[${k}]`, f)
  try {
    const r = await http.post('/publico/correcciones', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    resultado.value = r.data?.data ?? {}
  } catch (e) {
    error.value = e?.code === 'CORRECCION_INVALIDA' ? 'El enlace de corrección no es válido, venció o ya fue usado.' : mensajeError(e)
  } finally {
    ocupado.value = false
  }
}
</script>
