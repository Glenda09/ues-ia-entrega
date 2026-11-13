<template>
  <AuthenticationLayout>
    <form class="flex authentication-form gap-4" @submit.prevent="onSubmit">
      <h1 class="authentication-title">Verificación en dos pasos</h1>
      <p class="authentication-description">
        Ingresa el código de 6 dígitos que recibiste por correo o el generado por Google
        Authenticator para completar tu ingreso
      </p>

      <div class="authentication-fields">
        <div>
          <p id="two-factor-identifier-label" class="authentication-label">Usuario o correo</p>
          <div class="authentication-field" role="group" aria-labelledby="two-factor-identifier-label">
            <Icon name="account_circle" color="text-secondary" class="authentication-field-icon" />
            <span>{{ form.identifier }}</span>
          </div>
        </div>

        <div>
          <p id="token-label" class="authentication-label">Código de verificación</p>
          <InputOtp
            id="token"
            ref="otp"
            role="group"
            aria-labelledby="token-label"
            v-model="form.token"
            :invalid="v$.token.$error"
            :aria-describedby="v$.token.$error ? 'token-error' : undefined"
            @blur="v$.token.$touch()"
            :length="6"
            :pt="{ pcInputText: { root: { autocomplete: 'one-time-code' } } }"
            class="authentication-otp"
            pt:root="flex-wrap justify-center"
            integerOnly
            size="large"
          />
          <small v-if="v$.token.$error" id="token-error" class="text-danger block mt-1" role="alert">
            {{ tokenError }}
          </small>
        </div>
      </div>

      <div class="flex gap-4">
        <Button
          type="submit"
          class="authentication-submit"
          :loading="submitting"
          :disabled="submitting"
          :aria-busy="submitting"
        >
          {{ submitting ? 'Validando código' : 'Validar código' }}
        </Button>
      </div>

      <RouterLink :to="{ name: 'auth.login' }" class="authentication-link">
        Volver a iniciar sesión
      </RouterLink>
    </form>
  </AuthenticationLayout>
</template>

<script setup>
import { reactive, computed, onMounted, ref, nextTick } from 'vue'

import { Button, InputOtp } from 'primevue'

import useVuelidate from '@vuelidate/core'
import { required, helpers, email, or, minLength } from '@vuelidate/validators'

import authService from '@/modules/authentication/services/auth'
import { useAuthStore } from '@/shared/stores/auth'
import { useRouter } from 'vue-router'
import { toast } from 'vue3-toastify'
import AuthenticationLayout from '@/modules/authentication/components/AuthenticationLayout.vue'

const form = reactive({
  identifier: '',
  token: ''
})

const submitting = ref(false)
const otp = ref(null)
const DIGITOS_OTP = 6

/**
 * Da a cada casilla del código un nombre propio para que los lectores de pantalla indiquen su posición.
 */
async function nombrarCasillas() {
  await nextTick()
  const casillas = otp.value?.$el?.querySelectorAll('input') ?? []
  casillas.forEach((casilla, indice) => {
    casilla.setAttribute('aria-label', `Dígito ${indice + 1} de ${DIGITOS_OTP}`)
  })
}

onMounted(nombrarCasillas)

const auth = useAuthStore()
const router = useRouter()

const usernameOrEmail = helpers.withMessage('Ingresa un correo válido o usuario', (value) => {
  const texto = String(value ?? '').trim()
  return texto.includes('@')
    ? email.$validator(texto) && texto.length <= 255
    : texto.length > 0 && texto.length <= 150
})

const rules = {
  identifier: {
    required: helpers.withMessage('Por favor, ingresa un valor', required),
    usernameOrEmail
  },
  token: {
    required: helpers.withMessage('Por favor, ingresa el código', required),
    length: helpers.withMessage('El código debe tener 6 dígitos', (value) =>
      /^\d{6}$/.test(value ?? '')
    )
  }
}

const v$ = useVuelidate(rules, form)

const tokenError = computed(() => {
  if (!v$.value.token.$dirty) return ''
  if (v$.value.token.required?.$invalid) return 'Por favor, ingresa el código'
  if (v$.value.token.length?.$invalid) return 'El código debe tener 6 dígitos'
  return ''
})

onMounted(() => {
  const q = router.currentRoute.value.query
  if (q?.identifier) form.identifier = String(q.identifier)
  else if (q?.email) form.identifier = String(q.email)
})

function resolverDestino(redirect) {
  if (typeof redirect === 'string') {
    const limpio = redirect.trim()
    if (limpio.startsWith('/') && !limpio.startsWith('//') && !limpio.includes('\\')) {
      return limpio === '/' ? { path: '/' } : { path: limpio }
    }
    if (router.hasRoute(limpio)) {
      return { name: limpio }
    }
  }
  return { path: '/' }
}

const onSubmit = async () => {
  const valid = await v$.value.$validate()
  if (!valid || submitting.value) return

  submitting.value = true
  try {
    const res = await authService.validarCodigoDobleFactor({
      identifier: form.identifier,
      token: form.token
    })
    if (res?.status === 200) {
      const data = res.data
      auth.setTokens({ access: data?.access_token, refresh: data?.refresh_token })
      const redirect = router.currentRoute.value.query.redirect
      await router.replace(resolverDestino(redirect))
      return
    }
    toast.info('Código validado, completa el ingreso')
  } catch (e) {
    console.error('Error al validar 2FA', e)
    toast.error(e?.message ?? 'Código inválido. Verifica e intenta de nuevo')
  } finally {
    submitting.value = false
  }
}
</script>
