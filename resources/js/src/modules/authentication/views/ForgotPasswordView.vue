<template>
  <AuthenticationLayout>
    <form class="flex authentication-form gap-4" @submit.prevent="onSubmit">
      <h1 class="authentication-title">Recuperar cuenta</h1>

      <div class="authentication-fields">
        <div>
          <label for="email" class="authentication-label">Correo electrónico</label>
          <InputTextWithIcon
            id="email"
            v-model="form.email"
            type="email"
            :maxlength="255"
            :invalid="v$.email.$error"
            :aria-describedby="v$.email.$error ? 'email-error' : undefined"
            @blur="v$.email.$touch()"
            placeholder="Correo electrónico"
            :left-icon="{ name: 'email', color: 'text-secondary' }"
            root-class="authentication-field"
            input-class="authentication-field-input"
            autocomplete="username"
          />
          <small v-if="v$.email.$error" id="email-error" class="text-danger block mt-1" role="alert">
            {{ emailError }}
          </small>
        </div>

        <div v-if="mostrarOtp">
          <span id="token-label" class="authentication-label">Código de recuperación</span>
          <InputOtp
            id="token"
            role="group"
            aria-labelledby="token-label"
            v-model="form.token"
            :invalid="v$.token.$error"
            @blur="v$.token.$touch()"
            :length="6"
            class="authentication-otp"
            integerOnly
            size="large"
          />
          <small v-if="v$.token.$error" class="text-danger block mt-1" role="alert">
            {{ tokenError }}
          </small>

          <div class="flex mt-6 text-sm text-secondary gap-4">
            <template v-if="!canResend">
              Puedes reenviar el código en
              <span class="font-semibold">{{ formattedResendIn }}</span>
            </template>
            <template v-else>
              <Button
                :disabled="resendLoading"
                @click="onResend"
                text
                class="!bg-transparent !text-link !shadow-none !px-0 !py-0 hover:underline"
              >
                {{ resendLoading ? 'Enviando...' : 'Reenviar código' }}
              </Button>
            </template>
          </div>
        </div>
      </div>

      <div class="flex gap-4">
        <Button type="submit" class="authentication-submit" :loading="submitting" :disabled="submitting">
          {{ mostrarOtp ? 'Restablecer contraseña' : 'Validar cuenta' }}
        </Button>
      </div>

      <RouterLink :to="{ name: 'auth.login' }" class="authentication-link">
        Iniciar sesión
      </RouterLink>
    </form>
  </AuthenticationLayout>
</template>

<script setup>
import { reactive, computed, ref, onBeforeUnmount } from 'vue'
import { Button, InputOtp } from 'primevue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'

import useVuelidate from '@vuelidate/core'
import { required, helpers, email, maxLength } from '@vuelidate/validators'

import authService from '@/modules/authentication/services/auth'
import { useRouter } from 'vue-router'
import { toast } from 'vue3-toastify'
import AuthenticationLayout from '@/modules/authentication/components/AuthenticationLayout.vue'

const form = reactive({
  email: '',
  token: ''
})

const mostrarOtp = ref(false)
const submitting = ref(false)

const RESEND_COOLDOWN_SECONDS = 60
const canResend = ref(false)
const resendIn = ref(RESEND_COOLDOWN_SECONDS)
const resendLoading = ref(false)
let resendIntervalId = null

const router = useRouter()

const correoValido = helpers.withMessage('Ingresa un correo electrónico válido', email)

const rules = computed(() => ({
  email: {
    required: helpers.withMessage('Por favor, ingresa un valor', required),
    correoValido,
    maxLength: helpers.withMessage('Máximo 255 caracteres', maxLength(255))
  },
  token: mostrarOtp.value
    ? {
        required: helpers.withMessage('Por favor, ingresa el código', required),
        length: helpers.withMessage('El código debe tener 6 dígitos', (value) =>
          /^\d{6}$/.test(value ?? '')
        )
      }
    : {}
}))

const v$ = useVuelidate(rules, form)

const emailError = computed(() => {
  if (!v$.value.email.$dirty) return ''
  if (v$.value.email.required?.$invalid) return 'Por favor, ingresa un valor'
  if (v$.value.email.correoValido?.$invalid) return 'Ingresa un correo electrónico válido'
  if (v$.value.email.maxLength?.$invalid) return 'Máximo 255 caracteres'
  return ''
})

const tokenError = computed(() => {
  if (!mostrarOtp.value) return ''
  if (!v$.value.token.$dirty) return ''
  if (v$.value.token.required?.$invalid) return 'Por favor, ingresa el código'
  if (v$.value.token.length?.$invalid) return 'El código debe tener 6 dígitos'
  return ''
})

const formattedResendIn = computed(() => {
  const m = Math.floor(resendIn.value / 60)
  const s = resendIn.value % 60
  const mm = String(m).padStart(2, '0')
  const ss = String(s).padStart(2, '0')
  return `${mm}:${ss}`
})

function startResendCooldown() {
  canResend.value = false
  resendIn.value = RESEND_COOLDOWN_SECONDS
  if (resendIntervalId) {
    clearInterval(resendIntervalId)
    resendIntervalId = null
  }
  resendIntervalId = setInterval(() => {
    if (resendIn.value > 0) {
      resendIn.value -= 1
    }
    if (resendIn.value <= 0) {
      canResend.value = true
      clearInterval(resendIntervalId)
      resendIntervalId = null
    }
  }, 1000)
}

onBeforeUnmount(() => {
  if (resendIntervalId) clearInterval(resendIntervalId)
})

const onSubmit = async () => {
  if (submitting.value) return

  if (!mostrarOtp.value) {
    const valid = await v$.value.email.$validate()
    if (!valid) return

    submitting.value = true
    try {
      await authService.solicitarRecuperacionCuenta({ email: form.email })
      mostrarOtp.value = true
      startResendCooldown()
    } catch (e) {
      console.error('Error al solicitar recuperación', e)
      toast.error(e?.message ?? 'No se pudo solicitar la recuperación. Inténtalo de nuevo')
    } finally {
      submitting.value = false
    }
  } else {
    const valid = await v$.value.$validate()
    if (!valid) return

    submitting.value = true
    try {
      await authService.validarCodigoRecuperacion({ email: form.email, token: form.token })

      setTimeout(() => {
        router.push({ name: 'auth.login' })
      }, 3000)
    } catch (e) {
      console.error('Error al validar token', e)
      toast.error(e?.message ?? 'Código inválido. Verifica e intenta de nuevo')
    } finally {
      submitting.value = false
    }
  }
}

const onResend = async () => {
  if (!canResend.value || resendLoading.value) return
  resendLoading.value = true
  try {
    await authService.solicitarRecuperacionCuenta({ email: form.email })
    startResendCooldown()
  } catch (e) {
    console.error('Error al reenviar código', e)
    toast.error(e?.message ?? 'No se pudo reenviar el código. Inténtalo de nuevo')
  } finally {
    resendLoading.value = false
  }
}
</script>
