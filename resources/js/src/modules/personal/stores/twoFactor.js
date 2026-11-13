import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import cuentaService from '@/modules/authentication/services/cuenta'
import { useAuthStore } from '@/shared/stores/auth'
import { toast } from 'vue3-toastify'

const MODE_OPTIONS = Object.freeze([
  { label: 'Desactivado', value: 'disabled' },
  { label: 'Correo electrónico', value: 'email' },
  { label: 'Google Authenticator', value: 'authenticator' },
])

const resolveMode = (user) => {
  if (!user?.two_factor_status) return 'disabled'
  return user?.two_factor_type === 'authenticator' ? 'authenticator' : 'email'
}

export const useTwoFactorStore = defineStore('personalTwoFactor', () => {
  const authStore = useAuthStore()

  const modeOptions = computed(() => MODE_OPTIONS.filter((opcion) => {
    if (opcion.value === 'disabled') return authStore.usuario?.two_factor_can_disable !== false
    if (opcion.value === 'email') return !authStore.usuario?.two_factor_required || authStore.usuario?.two_factor_required_method !== 'authenticator'
    return true
  }))

  const selectedMode = ref('disabled')
  const lastPersistedMode = ref('disabled')
  const isProcessing = ref(false)
  const isConfirming = ref(false)
  const setupDialogVisible = ref(false)
  const setupData = ref(null)

  const modeDescription = computed(() => {
    switch (selectedMode.value) {
      case 'authenticator':
        return 'Escanea el código QR y confirma con Google Authenticator para completar la activación.'
      case 'email':
        return 'Recibirás un código de verificación por correo cada vez que inicies sesión.'
      default:
        if (authStore.usuario?.two_factor_required) return 'La verificación es obligatoria. Selecciona uno de los métodos permitidos.'
        return 'El inicio de sesión solo requerirá usuario y contraseña.'
    }
  })

  const setupSecret = computed(() => setupData.value?.secret ?? '')
  const setupOtpauthUrl = computed(() => setupData.value?.otpauth_url ?? '')

  watch(
    () => authStore.usuario,
    (user) => {
      const mode = resolveMode(user)
      selectedMode.value = mode
      lastPersistedMode.value = mode
    },
    { immediate: true, deep: true }
  )

  async function changeMode(mode) {
    if (!modeOptions.value.some((opcion) => opcion.value === mode)) {
      selectedMode.value = lastPersistedMode.value
      return
    }
    if (mode === lastPersistedMode.value) {
      selectedMode.value = mode
      return
    }

    if (isProcessing.value || isConfirming.value) {
      selectedMode.value = lastPersistedMode.value
      return
    }

    if (mode === 'authenticator') {
      selectedMode.value = 'authenticator'
      await startTwoFactorSetup()
      return
    }

    if (mode === 'email') {
      await enableEmailMode()
      return
    }

    await disableTwoFactor()
  }

  async function startTwoFactorSetup() {
    try {
      isProcessing.value = true
      setupData.value = null
      const { data } = await cuentaService.setup2FA()
      setupData.value = data?.data ?? null
      setupDialogVisible.value = true
    } catch (e) {
      console.error('Error iniciando configuración 2FA', e)
      toast.error(e?.message ?? 'No se pudo generar la llave de doble factor')
      selectedMode.value = lastPersistedMode.value
    } finally {
      isProcessing.value = false
    }
  }

  async function confirmAuthenticator(token) {
    if (isConfirming.value) return

    try {
      isConfirming.value = true
      await cuentaService.update2FA({ mode: 'authenticator', token })
      updateAuthState('authenticator')
      lastPersistedMode.value = 'authenticator'
      selectedMode.value = 'authenticator'
      setupDialogVisible.value = false
      setupData.value = null
    } catch (e) {
      console.error('Error confirmando doble factor', e)
      toast.error(e?.message ?? 'Código inválido, intenta nuevamente')
    } finally {
      isConfirming.value = false
    }
  }

  async function enableEmailMode() {
    try {
      isProcessing.value = true
      await cuentaService.update2FA({ mode: 'email' })
      updateAuthState('email')
      lastPersistedMode.value = 'email'
      selectedMode.value = 'email'
    } catch (e) {
      console.error('Error habilitando 2FA por correo', e)
      selectedMode.value = lastPersistedMode.value
      toast.error(e?.message ?? 'No se pudo habilitar el doble factor por correo')
    } finally {
      isProcessing.value = false
    }
  }

  async function disableTwoFactor() {
    try {
      isProcessing.value = true
      await cuentaService.update2FA({ mode: 'disabled' })
      updateAuthState('disabled')
      lastPersistedMode.value = 'disabled'
      selectedMode.value = 'disabled'
    } catch (e) {
      console.error('Error deshabilitando 2FA', e)
      selectedMode.value = lastPersistedMode.value
      toast.error(e?.message ?? 'No se pudo deshabilitar el doble factor')
    } finally {
      isProcessing.value = false
    }
  }

  function updateAuthState(mode) {
    if (!authStore.usuario) return

    authStore.usuario.two_factor_setup_pending = false
    if (mode === 'disabled') {
      authStore.usuario.two_factor_status = false
      authStore.usuario.two_factor_type = null
    } else {
      authStore.usuario.two_factor_status = true
      authStore.usuario.two_factor_type = mode
    }
  }

  function cancelSetup() {
    setupDialogVisible.value = false
    setupData.value = null
    selectedMode.value = lastPersistedMode.value
  }

  return {
    modeOptions,
    selectedMode,
    modeDescription,
    isProcessing,
    isConfirming,
    setupDialogVisible,
    setupSecret,
    setupOtpauthUrl,
    changeMode,
    confirmAuthenticator,
    cancelSetup,
  }
})
