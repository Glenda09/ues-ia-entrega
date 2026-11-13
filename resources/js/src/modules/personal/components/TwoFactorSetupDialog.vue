<template>
  <Dialog
    v-model:visible="dialogVisible"
    modal
    header="Configurar doble factor"
    :style="{ width: '32rem', maxWidth: '95vw' }"
    @hide="emitCancel"
  >
    <div class="space-y-4">
      <p class="text-sm text-secondary">
        Escanea el código QR con Google Authenticator o ingresa la clave manualmente para agregar la cuenta.
      </p>

      <div class="flex flex-col items-center gap-3">
        <div
          v-if="qrImage"
          class="w-48 h-48 border border-secondary-100 rounded-lg overflow-hidden flex items-center justify-center bg-surface-card"
        >
          <img :src="qrImage" alt="Código QR 2FA" class="w-full h-full object-contain" />
        </div>
        <div v-else class="w-48 h-48 flex items-center justify-center bg-secondary-100 rounded-lg text-sm text-secondary">
          Generando código QR…
        </div>
        <div class="w-full text-center">
          <span class="text-xs uppercase tracking-wide text-secondary block">Clave manual</span>
          <code class="text-base font-semibold break-words">{{ secret }}</code>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-text mb-2">Código de verificación</label>
        <InputOtp
          v-model="token"
          :length="6"
          integerOnly
          size="large"
          :disabled="loading"
          pt:root="flex-wrap justify-center"
        />
        <small v-if="tokenError" class="text-danger block mt-1">{{ tokenError }}</small>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Cancelar"
          severity="secondary"
          outlined
          :disabled="loading"
          @click="onCancel"
        />
        <Button
          label="Confirmar"
          :loading="loading"
          :disabled="token.length !== 6"
          @click="onConfirm"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import QRCode from 'qrcode'
import { Dialog, Button, InputOtp } from 'primevue'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  secret: {
    type: String,
    default: '',
  },
  otpauthUrl: {
    type: String,
    default: '',
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:visible', 'confirm', 'cancel'])

const token = ref('')
const qrImage = ref(null)
const dialogVisible = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val),
})

const tokenError = computed(() => {
  if (token.value.length === 0) return ''
  return token.value.length !== 6 ? 'Ingresa los 6 dígitos del código' : ''
})

watch(
  () => props.visible,
  async (val) => {
    if (val) {
      token.value = ''
      await generateQr()
    } else {
      qrImage.value = null
    }
  },
  { immediate: false }
)

watch(
  () => props.otpauthUrl,
  async () => {
    if (props.visible) await generateQr()
  }
)

async function generateQr() {
  if (!props.otpauthUrl) {
    qrImage.value = null
    return
  }
  try {
    qrImage.value = await QRCode.toDataURL(props.otpauthUrl)
  } catch (e) {
    console.error('Error generando QR 2FA', e)
    qrImage.value = null
  }
}

function onConfirm() {
  if (token.value.length === 6 && !props.loading) emit('confirm', token.value)
}

function onCancel() {
  emitCancel()
}

function emitCancel() {
  emit('cancel')
  emit('update:visible', false)
}
</script>
