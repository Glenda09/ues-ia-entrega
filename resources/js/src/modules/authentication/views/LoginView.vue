<template>
  <AuthenticationLayout>
    <form class="flex authentication-form gap-4" @submit.prevent="onSubmit">
      <h1 class="authentication-title">Iniciar sesión</h1>

      <p v-if="showExpiredMessage" role="status" class="authentication-expired-message">
        Tu sesión expiró. Inicia sesión nuevamente
      </p>
      <p v-else-if="showClosedMessage" role="status" class="authentication-closed-message">
        Sesión cerrada correctamente
      </p>

      <div class="authentication-fields">
        <div>
          <label for="identifier" class="authentication-label">Usuario o correo</label>
          <InputTextWithIcon
            id="identifier"
            v-model="form.identifier"
            :invalid="v$.identifier.$error"
            :aria-describedby="v$.identifier.$error ? 'identifier-error' : undefined"
            @blur="v$.identifier.$touch()"
            placeholder="Usuario / Correo"
            :left-icon="{ name: 'account_circle', color: 'text-secondary' }"
            root-class="authentication-field"
            input-class="authentication-field-input"
            autocomplete="username"
            :maxlength="255"
          />
          <small v-if="v$.identifier.$error" id="identifier-error" class="text-danger block mt-1" role="alert">
            {{ identifierError }}
          </small>
        </div>

        <div>
          <label for="password" class="authentication-label">Contraseña</label>
          <div class="flex authentication-field gap-4">
            <Icon
              name="lock"
              color="text-secondary"
              class="authentication-field-icon"
              aria-hidden="true"
            />
            <InputText
              id="password"
              v-model="form.password"
              :invalid="v$.password.$error"
              :aria-describedby="v$.password.$error ? 'password-error' : undefined"
              @blur="v$.password.$touch()"
              :type="showPassword ? 'text' : 'password'"
              placeholder="Contraseña"
              name="password"
              autocomplete="current-password"
              class="authentication-field-input login-password-input"
            />
            <button
              type="button"
              class="login-password-toggle cursor-pointer"
              @click="showPassword = !showPassword"
              :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            >
              <Icon :name="showPassword ? 'visibility_off' : 'visibility'" color="text-secondary" />
            </button>
          </div>
          <small v-if="v$.password.$error" id="password-error" class="text-danger block mt-1" role="alert">
            {{ passwordError }}
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
          {{ submitting ? 'Iniciando sesión' : 'Iniciar sesión' }}
        </Button>
      </div>

      <RouterLink :to="{ name: 'auth.forgot-password' }" class="authentication-link">
        ¿Olvidaste tu contraseña?
      </RouterLink>
    </form>
  </AuthenticationLayout>
</template>

<script setup>
import { reactive, computed, ref } from 'vue'

import { Button, InputText } from 'primevue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'

import useVuelidate from '@vuelidate/core'
import { required, helpers, email } from '@vuelidate/validators'

import { useAuthStore } from '@/shared/stores/auth'
import { useRouter } from 'vue-router'
import { toast } from 'vue3-toastify'
import AuthenticationLayout from '@/modules/authentication/components/AuthenticationLayout.vue'

const form = reactive({
  identifier: '',
  password: ''
})

const showPassword = ref(false)
const submitting = ref(false)

const auth = useAuthStore()
const router = useRouter()

const showExpiredMessage = computed(() => router.currentRoute.value.query.expired === '1')
const showClosedMessage = computed(() => router.currentRoute.value.query.closed === '1')

const usernameOrEmail = helpers.withMessage('Ingresa un correo válido o usuario', (value) => {
  const texto = String(value ?? '').trim()
  return texto.includes('@')
    ? email.$validator(texto) && texto.length <= 255
    : texto.length > 0 && texto.length <= 150
})

const rules = {
  identifier: {
    required: helpers.withMessage('Ingresa tu usuario o correo', required),
    usernameOrEmail
  },
  password: {
    required: helpers.withMessage('Ingresa tu contraseña', required)
  }
}

const v$ = useVuelidate(rules, form)

const identifierError = computed(() => {
  if (!v$.value.identifier.$dirty) return ''
  if (v$.value.identifier.required?.$invalid) return 'Ingresa tu usuario o correo'
  if (v$.value.identifier.usernameOrEmail?.$invalid) return 'Ingresa un correo válido o usuario'
  return ''
})

const passwordError = computed(() => {
  if (!v$.value.password.$dirty) return ''
  if (v$.value.password.required?.$invalid) return 'Ingresa tu contraseña'
  return ''
})

const onSubmit = async () => {
  const valid = await v$.value.$validate()
  if (!valid || submitting.value) return

  submitting.value = true
  try {
    const result = await auth.login({ identifier: form.identifier, password: form.password })

    const rawRedirect = router.currentRoute.value.query.redirect
    const redirect =
      typeof rawRedirect === 'string' &&
      rawRedirect.startsWith('/') &&
      !rawRedirect.startsWith('//') &&
      !rawRedirect.includes('\\') &&
      rawRedirect !== '/'
        ? rawRedirect
        : undefined

    if (result?.twoFactorRequired && result?.success) {
      const identifier = form.identifier
      await router.push({
        name: 'auth.two-factor',
        query: { identifier, ...(redirect ? { redirect } : {}) }
      })
    } else if (!result?.twoFactorRequired && result?.success) {
      await router.replace({ path: redirect ?? '/' })
    } else {
      toast.error('No se pudo iniciar sesión. Verifica tus credenciales')
    }
  } catch (e) {
    console.error('Error al iniciar sesión', e)
    toast.error(e?.message ?? 'No se pudo iniciar sesión. Verifica tus credenciales')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.authentication-expired-message,
.authentication-closed-message {
  margin: -0.5rem 0 0;
  padding: 0.625rem 0.875rem;
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 500;
}

.authentication-expired-message {
  background-color: color-mix(in srgb, var(--color-warning) 15%, transparent);
  color: var(--color-warning);
}

.authentication-closed-message {
  background-color: color-mix(in srgb, var(--color-success) 15%, transparent);
  color: var(--color-success);
}

:deep(.login-password-input) {
  padding-right: 2rem !important;
}

.login-password-toggle {
  display: grid;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  place-items: center;
  border: 0;
  background: transparent;
  cursor: pointer;
}
</style>
