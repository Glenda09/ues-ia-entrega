<template>
  <div class="flex flex-col gap-3 max-w-md">
    <p class="text-sm text-secondary">Doble factor de autenticación</p>
    <p v-if="auth.usuario?.two_factor_required" class="text-sm text-secondary">La verificación en dos pasos es obligatoria y no se puede desactivar.</p>

    <SelectButton
      :options="modeOptions"
      optionLabel="label"
      optionValue="value"
      :modelValue="selectedMode"
      :disabled="isProcessing || isConfirming"
      @update:modelValue="onModeChange"
    />

    <p class="text-sm text-secondary">{{ modeDescription }}</p>

    <TwoFactorSetupDialog
      v-model:visible="setupDialogVisible"
      :secret="setupSecret"
      :otpauth-url="setupOtpauthUrl"
      :loading="isConfirming"
      @confirm="confirmTwoFactor"
      @cancel="onSetupCancel"
    />
  </div>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import { SelectButton } from 'primevue'
import TwoFactorSetupDialog from '@/modules/personal/components/TwoFactorSetupDialog.vue'
import { useAuthStore } from '@/shared/stores/auth'
import { useTwoFactorStore } from '@/modules/personal/stores/twoFactor'

const auth = useAuthStore()
const twoFactorStore = useTwoFactorStore()

const {
  modeOptions,
  selectedMode,
  modeDescription,
  isProcessing,
  isConfirming,
  setupDialogVisible,
  setupSecret,
  setupOtpauthUrl,
} = storeToRefs(twoFactorStore)


function onModeChange(mode) {
  twoFactorStore.changeMode(mode)
}

function confirmTwoFactor(token) {
  twoFactorStore.confirmAuthenticator(token)
}

function onSetupCancel() {
  twoFactorStore.cancelSetup()
}
</script>
