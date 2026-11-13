<template>
  <div class="space-y-4">
    <p class="text-sm text-secondary">
      Escanea el código QR con Google Authenticator o ingresa la clave manualmente para agregar la
      cuenta
    </p>

    <div class="flex flex-col items-center gap-3">
      <div
        v-if="qrImage"
        class="w-48 h-48 rounded-lg overflow-hidden flex items-center justify-center bg-surface-card"
      >
        <img :src="qrImage" alt="Código QR 2FA" class="w-full h-full object-contain" />
      </div>
      <p v-else-if="qrError" class="text-sm text-danger text-center" role="alert">
        No se pudo generar el código QR. Usa la clave manual para configurar la cuenta
      </p>
      <div v-else class="animate-pulse bg-secondary-100 h-48 w-48 rounded-lg" role="status" aria-label="Generando código QR" />
      <div class="w-full text-center">
        <span class="text-xs uppercase tracking-wide text-secondary block">Clave manual</span>
        <code v-if="secret" class="text-base font-semibold break-words">{{ secret }}</code>
        <div v-else class="animate-pulse bg-secondary-100 mx-auto mt-1 h-5 w-56 max-w-full rounded-md" />
      </div>
    </div>

    <div>
      <span :id="idEtiqueta" class="block text-sm font-medium text-text mb-2">Código de verificación</span>
      <InputOtp
        ref="otp"
        role="group"
        :aria-labelledby="idEtiqueta"
        :aria-describedby="mensajeError ? idError : undefined"
        v-model="token"
        :invalid="Boolean(mensajeError)"
        :length="DIGITOS_OTP"
        :pt="{ pcInputText: { root: { autocomplete: 'one-time-code' } } }"
        integerOnly
        size="large"
        :disabled="loading"
        pt:root="flex-wrap justify-center"
      />
      <small v-if="mensajeError" :id="idError" class="text-danger block mt-1" role="alert">{{ mensajeError }}</small>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, nextTick, useId } from 'vue'
import QRCode from 'qrcode'
import { InputOtp } from 'primevue'

const DIGITOS_OTP = 6

const props = defineProps({
  secret: {
    type: String,
    default: ''
  },
  otpauthUrl: {
    type: String,
    default: ''
  },
  loading: {
    type: Boolean,
    default: false
  },
  /** Mensaje de error externo (p. ej. código rechazado por el servidor) */
  error: {
    type: String,
    default: ''
  }
})

/** Código de 6 dígitos ingresado; se expone con `v-model:token` */
const token = defineModel('token', { type: String, default: '' })

const idBase = useId()
const idEtiqueta = `${idBase}-token-label`
const idError = `${idBase}-token-error`

const otp = ref(null)
const qrImage = ref(null)
const qrError = ref(false)

const tokenError = computed(() => {
  if (token.value.length === 0) return ''
  return token.value.length !== DIGITOS_OTP ? 'Ingresa los 6 dígitos del código' : ''
})

const mensajeError = computed(() => tokenError.value || props.error)

watch(() => props.otpauthUrl, generateQr, { immediate: true })

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

/**
 * Genera la imagen del QR a partir de la URL otpauth; si falla marca el error
 * para que la vista deje de mostrar el skeleton.
 */
async function generateQr() {
  qrError.value = false
  if (!props.otpauthUrl) {
    qrImage.value = null
    return
  }
  try {
    qrImage.value = await QRCode.toDataURL(props.otpauthUrl)
  } catch (e) {
    console.error('Error generando QR 2FA', e)
    qrImage.value = null
    qrError.value = true
  }
}
</script>
