<template>
  <div class="mx-auto max-w-4xl p-4">
    <header class="mb-6 flex flex-wrap items-center justify-between gap-2 border-b border-secondary-100 pb-3">
      <nav aria-label="Navegación pública" class="flex flex-wrap gap-4 text-sm">
        <RouterLink to="/carreras" class="underline">Carreras</RouterLink>
        <RouterLink to="/asistente-aspirantes" class="underline">Asistente de aspirantes</RouterLink>
        <RouterLink to="/login" class="underline">Iniciar sesión</RouterLink>
      </nav>
    </header>
    <h1 class="mb-1 text-2xl font-semibold">Registro de aspirante</h1>
    <p class="mb-6 text-secondary">Crea tu cuenta para completar tu solicitud de admisión.</p>

    <div class="max-w-md">
      <form v-if="paso === 1" class="space-y-4" @submit.prevent="verificar">
        <div><label for="email" class="mb-1 block text-sm font-medium">Correo electrónico</label><InputText id="email" v-model="email" type="email" autocomplete="email" class="w-full" required /></div>
        <p v-if="mensaje" role="status" class="text-sm text-green-700">{{ mensaje }}</p>
        <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
        <Button type="submit" label="Enviar código de verificación" :loading="ocupado" />
      </form>

      <form v-else-if="paso === 2" class="space-y-4" @submit.prevent="registrar">
        <p v-if="mensaje" role="status" class="text-sm text-green-700">{{ mensaje }}</p>
        <div><label for="codigo" class="mb-1 block text-sm font-medium">Código de verificación (6 dígitos)</label><InputText id="codigo" v-model="form.codigo_verificacion" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="w-full" /></div>
        <div><label for="pw" class="mb-1 block text-sm font-medium">Contraseña</label><InputText id="pw" v-model="form.password" type="password" autocomplete="new-password" class="w-full" /></div>
        <div><label for="pw2" class="mb-1 block text-sm font-medium">Confirmar contraseña</label><InputText id="pw2" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="w-full" /></div>
        <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
        <div class="flex gap-2"><Button type="button" text label="Cambiar correo" @click="paso = 1" /><Button type="submit" label="Crear cuenta" :loading="ocupado" /></div>
      </form>

      <div v-else role="status" class="space-y-3">
        <p class="rounded-lg border border-green-300 bg-green-50 p-3 text-green-900">{{ mensaje || 'Tu cuenta fue creada.' }}</p>
        <p class="text-sm">Inicia sesión con tu correo y contraseña para completar tu solicitud.</p>
        <RouterLink to="/login?redirect=/admision"><Button label="Iniciar sesión" /></RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Button, InputText } from 'primevue'
import { api, mensajeError } from '../services/academico'

const paso = ref(1)
const email = ref('')
const form = reactive({ codigo_verificacion: '', password: '', password_confirmation: '' })
const ocupado = ref(false)
const error = ref('')
const mensaje = ref('')

async function verificar() {
  ocupado.value = true
  error.value = ''
  try {
    const r = await api.post('/publico/admision/verificaciones', { email: email.value })
    mensaje.value = r.message ?? 'Si el correo es válido, recibirás un código de verificación.'
    paso.value = 2
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    ocupado.value = false
  }
}

async function registrar() {
  ocupado.value = true
  error.value = ''
  try {
    const r = await api.post('/publico/admision/registros', { email: email.value, ...form })
    mensaje.value = r.message ?? ''
    form.password = ''
    form.password_confirmation = ''
    paso.value = 3
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    ocupado.value = false
  }
}
</script>
