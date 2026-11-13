<template>
  <AuthenticationLayout>
    <div class="mx-auto w-full max-w-md rounded-2xl bg-surface-card p-6 shadow">
      <h1 class="text-2xl font-semibold">Cambia tu contraseña inicial</h1>
      <p class="mt-2 text-sm text-secondary">
        Por seguridad, debes reemplazar la contraseña temporal recibida por correo antes de usar la plataforma.
        <span v-if="vence">Tu contraseña temporal vence el {{ vence }}.</span>
      </p>
      <p v-if="vencida" role="alert" class="mt-4 rounded border border-red-300 p-3 text-sm text-red-700">
        Tu contraseña temporal venció. Usa <RouterLink to="/forgot-password" class="underline">la recuperación de cuenta</RouterLink> para obtener una nueva.
      </p>
      <form v-else class="mt-6 space-y-4" @submit.prevent="cambiar">
        <div><label for="actual" class="mb-1 block text-sm font-medium">Contraseña temporal</label><Password inputId="actual" v-model="form.current_password" :feedback="false" toggleMask class="w-full" inputClass="w-full" autocomplete="current-password" /></div>
        <div><label for="nueva" class="mb-1 block text-sm font-medium">Nueva contraseña</label><Password inputId="nueva" v-model="form.new_password" toggleMask class="w-full" inputClass="w-full" autocomplete="new-password" /></div>
        <div><label for="confirmacion" class="mb-1 block text-sm font-medium">Confirmar contraseña</label><Password inputId="confirmacion" v-model="form.password_confirmation" :feedback="false" toggleMask class="w-full" inputClass="w-full" autocomplete="new-password" /></div>
        <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
        <Button type="submit" label="Cambiar contraseña" class="w-full" :loading="ocupado" :disabled="form.new_password.length < 8 || form.new_password !== form.password_confirmation" />
        <Button type="button" text label="Cerrar sesión" class="w-full" @click="auth.logout()" />
      </form>
    </div>
  </AuthenticationLayout>
</template>

<script setup>
/**
 * EST-01: cambio controlado de la contraseña temporal. Hasta completarlo, el backend
 * responde 403 CAMBIO_PASSWORD_INICIAL_PENDIENTE en las rutas restringidas.
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Button, Password } from 'primevue'
import AuthenticationLayout from '@/modules/authentication/components/AuthenticationLayout.vue'
import http from '@/shared/services/http'
import { useAuthStore } from '@/shared/stores/auth'
import { fecha, mensajeError } from '../services/academico'

const auth = useAuthStore()
const router = useRouter()
const form = reactive({ current_password: '', new_password: '', password_confirmation: '' })
const error = ref('')
const ocupado = ref(false)
const expira = ref(null)
const vence = computed(() => (expira.value ? fecha(expira.value) : ''))
const vencida = computed(() => expira.value && new Date(expira.value) < new Date())

onMounted(async () => {
  try {
    const { data } = await http.get('/cuenta', { toastSuccess: false })
    if (!data.password_change_required) return router.replace('/')
    expira.value = data.temporary_password_expires_at
  } catch (e) {
    error.value = mensajeError(e)
  }
})

async function cambiar() {
  ocupado.value = true
  error.value = ''
  try {
    await http.post('/auth/change-password', form)
    await auth.obtenerCuenta({ redirigir: false })
    const destino = router.currentRoute.value.query.redirect
    router.replace(typeof destino === 'string' && destino.startsWith('/') ? destino : '/')
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    ocupado.value = false
  }
}
</script>
