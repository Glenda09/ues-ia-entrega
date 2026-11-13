<template>
  <transition name="fade">
    <div
      v-if="visible"
      class="fixed inset-0 z-40 bg-overlay backdrop-blur-xs transition-all duration-300"
      @click="onCancelChangePassword"
    ></div>
  </transition>

  <Dialog
    v-model:visible="visible"
    :modal="true"
    :draggable="false"
    :closable="false"
    pt:root="!w-full md:!w-6/12 lg:!w-4/12 z-50 bg-surface-card border-0 mx-4 md:mx-0"
  >
    <template #header>
      <h3 class="text-xl font-semibold">Cambiar contraseña</h3>
    </template>

    <div class="flex flex-col gap-4">
      <Message v-if="errorMessage" severity="error" class="mb-2">
        {{ errorMessage }}
      </Message>

      <div>
        <InputTextWithIcon
          v-model="form.current_password"
          :invalid="v$.current_password.$error"
          @blur="v$.current_password.$touch()"
          :type="showPassword ? 'text' : 'password'"
          placeholder="Contraseña actual"
          :left-icon="{ name: 'lock', color: 'text-secondary' }"
          input-class="pr-10! py-4!"
          :pt="{ input: { name: 'password', autocomplete: 'current-password' } }"
        />

        <small v-if="v$.current_password.$error" class="text-danger block mt-1">
          {{ currentPasswordError }}
        </small>
      </div>

      <div>
        <InputTextWithIcon
          v-model="form.new_password"
          :invalid="v$.new_password.$error"
          @blur="v$.new_password.$touch()"
          :type="showPassword ? 'text' : 'password'"
          placeholder="Nueva contraseña"
          :left-icon="{ name: 'lock', color: 'text-secondary' }"
          input-class="pr-10! py-4!"
          :pt="{ input: { name: 'password', autocomplete: 'new_password' } }"
        />

        <small v-if="v$.new_password.$error" class="text-danger block mt-1">
          {{ newPasswordError }}
        </small>
      </div>

      <div>
        <InputTextWithIcon
          v-model="form.password_confirmation"
          :invalid="v$.password_confirmation.$error"
          @blur="v$.password_confirmation.$touch()"
          :type="showPassword ? 'text' : 'password'"
          placeholder="Confirmación de contraseña"
          :left-icon="{ name: 'lock', color: 'text-secondary' }"
          input-class="pr-10! py-4!"
          :pt="{ input: { name: 'password', autocomplete: 'password_confirmation' } }"
        />

        <small v-if="v$.password_confirmation.$error" class="text-danger block mt-1">
          {{ confirmPasswordError }}
        </small>
      </div>
    </div>

    <div class="flex items-center gap-3 mt-4">
      <Checkbox
        v-model="showPassword"
        :binary="true"
        variant="filled"
        inputId="toggleShowPassword"
      />
      <label for="toggleShowPassword" class="text-sm cursor-pointer select-none">
        Mostrar contraseña
      </label>
    </div>

    <template #footer>
      <div class="flex justify-end gap-4 mt-4">
        <Button
          type="button"
          severity="secondary"
          outlined
          class="mr-2 w-full lg:w-2/6 2xl:w-1/6"
          @click="onCancelChangePassword"
        >
          Cancelar
        </Button>
        <Button
          type="button"
          class="w-full lg:w-3/6 2xl:w-2/6"
          :disabled="submitting"
          @click="onSubmitChangePassword"
        >
          {{ submitting ? 'Actualizando...' : 'Actualizar' }}
        </Button>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Dialog, Message, Checkbox, Button } from 'primevue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'

import useVuelidate from '@vuelidate/core'
import { required, minLength, helpers, sameAs } from '@vuelidate/validators'
import { password as passwordRegex } from '@/shared/utils/regex'

import authService from '@/modules/authentication/services/auth'

const props = defineProps({
  open: { type: Boolean, default: false }
})
const emit = defineEmits(['update:open'])

const visible = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
})

const submitting = ref(false)
const errorMessage = ref('')
const showPassword = ref(false)

const form = reactive({
  current_password: '',
  new_password: '',
  password_confirmation: ''
})

const rules = {
  current_password: {
    required: helpers.withMessage('Por favor, ingresa tu contraseña actual', required)
  },
  new_password: {
    required: helpers.withMessage('Por favor, ingresa la nueva contraseña', required),
    minLength: helpers.withMessage('La contraseña debe tener al menos 8 caracteres', minLength(8)),
    strong: helpers.withMessage(
      'Debe incluir mayúsculas, minúsculas y números',
      (val) => !!val && passwordRegex.test(val)
    )
  },
  password_confirmation: {
    required: helpers.withMessage('Por favor, confirma la contraseña', required),
    sameAs: helpers.withMessage(
      'Las contraseñas no coinciden',
      sameAs(computed(() => form.new_password))
    )
  }
}

const v$ = useVuelidate(rules, form)

const currentPasswordError = computed(() => {
  if (!v$.value.current_password.$dirty) return ''
  if (v$.value.current_password.required?.$invalid) return 'Por favor, ingresa tu contraseña actual'
  return ''
})

const newPasswordError = computed(() => {
  if (!v$.value.new_password.$dirty) return ''
  if (v$.value.new_password.required?.$invalid) return 'Por favor, ingresa la nueva contraseña'
  if (v$.value.new_password.minLength?.$invalid)
    return 'La contraseña debe tener al menos 8 caracteres'
  if (v$.value.new_password.strong?.$invalid) return 'Debe incluir mayúsculas, minúsculas y números'
  return ''
})

const confirmPasswordError = computed(() => {
  if (!v$.value.password_confirmation.$dirty) return ''
  if (v$.value.password_confirmation.required?.$invalid) return 'Por favor, confirma la contraseña'
  if (v$.value.password_confirmation.sameAs?.$invalid) return 'Las contraseñas no coinciden'
  return ''
})

function resetChangePasswordState() {
  form.current_password = ''
  form.new_password = ''
  form.password_confirmation = ''
  errorMessage.value = ''
  v$.value.$reset()
}

function onCancelChangePassword() {
  emit('update:open', false)
  resetChangePasswordState()
}

async function onSubmitChangePassword() {
  errorMessage.value = ''
  const valid = await v$.value.$validate()
  if (!valid) return
  try {
    submitting.value = true
    await authService.cambiarPassword({
      current_password: form.current_password,
      new_password: form.new_password,
      password_confirmation: form.password_confirmation
    })
    emit('update:open', false)
    resetChangePasswordState()
  } catch (e) {
    console.error(e)
  } finally {
    submitting.value = false
  }
}
</script>
