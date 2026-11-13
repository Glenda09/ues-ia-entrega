<template>
  <AuthenticationLayout>
    <form class="authentication-form" @submit.prevent="confirmar">
      <h1 class="authentication-title">Configura el doble factor</h1>
      <p class="authentication-description">Google Authenticator es obligatorio. Configúralo para continuar usando tu cuenta.</p>
      <p v-if="error" class="text-sm text-danger" role="alert">{{ error }}</p>
      <TwoFactorAuthenticatorSetup v-if="!errorCarga" v-model:token="token" :secret="secret" :otpauth-url="otpauthUrl" :loading="cargando || guardando" />
      <Button type="submit" class="authentication-submit" label="Confirmar" :loading="guardando" :disabled="cargando || guardando || errorCarga" />
      <Button v-if="errorCarga" type="button" label="Volver a cargar la clave" :disabled="cargando" @click="cargar" />
      <Button type="button" text severity="secondary" label="Cerrar sesión" :disabled="guardando" @click="auth.logout()" />
    </form>
  </AuthenticationLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Button } from 'primevue'
import AuthenticationLayout from '@/modules/authentication/components/AuthenticationLayout.vue'
import TwoFactorAuthenticatorSetup from '@/modules/personal/components/TwoFactorAuthenticatorSetup.vue'
import cuentaService from '@/modules/authentication/services/cuenta'
import { useAuthStore } from '@/shared/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const token = ref('')
const secret = ref('')
const otpauthUrl = ref('')
const cargando = ref(false)
const guardando = ref(false)
const errorCarga = ref(false)
const error = ref('')

async function cargar() {
  cargando.value = true
  error.value = ''
  errorCarga.value = false
  try {
    const { data } = await cuentaService.setup2FA()
    secret.value = data.data.secret
    otpauthUrl.value = data.data.otpauth_url
  } catch (e) {
    errorCarga.value = true
    error.value = e.message || 'No se pudo cargar la clave'
  } finally {
    cargando.value = false
  }
}

async function confirmar() {
  if (cargando.value || guardando.value) return
  if (!/^\d{6}$/.test(token.value)) {
    error.value = 'Ingresa los 6 dígitos del código'
    return
  }
  guardando.value = true
  error.value = ''
  try {
    await cuentaService.update2FA({ mode: 'authenticator', token: token.value })
    const cuentaCargada = await auth.obtenerCuenta()
    if (!cuentaCargada) throw new Error('No se pudo actualizar la cuenta. Vuelve a iniciar sesión')
    secret.value = ''
    otpauthUrl.value = ''
    const destino = route.query.redirect
    await router.replace(typeof destino === 'string' && destino.startsWith('/') && !destino.startsWith('//') && !destino.includes('\\') && !destino.startsWith('/configurar-doble-factor') ? destino : '/')
  } catch (e) {
    error.value = e.message || 'No se pudo confirmar el código'
  } finally {
    guardando.value = false
  }
}

onMounted(cargar)
</script>
