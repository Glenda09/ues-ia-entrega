<template>
  <transition name="fade">
    <div
      v-if="visible"
      class="fixed inset-0 z-40 bg-overlay backdrop-blur-xs transition-all duration-300"
      @click="onNoClick"
      aria-hidden="true"
    ></div>
  </transition>

  <Dialog
    v-model:visible="visible"
    :modal="true"
    :draggable="false"
    :closable="false"
    pt:root="!w-full md:!w-6/12 lg:!w-4/12 z-50 bg-surface-card border-0 mx-4 md:mx-0"
    aria-modal="true"
    :aria-label="ariaLabel"
    @keydown.enter.prevent.stop="onYesClick"
    @keydown.escape.prevent.stop="onNoClick"
  >
    <template #header>
      <h3 class="text-xl font-semibold">Confirmación</h3>
    </template>

    <p class="text-text" data-testid="confirm-message">{{ message }}</p>

    <template #footer>
      <div class="w-full flex gap-4">
        <Button
          type="button"
          severity="secondary"
          outlined
          class="w-full md:!w-3/6"
          :label="noText"
          :disabled="busy"
          ref="noBtnRef"
          @click="onNoClick"
          data-testid="confirm-no"
        />
        <Button
          type="button"
          class="text-brand-contrast w-full md:!w-3/6"
          :label="yesText"
          :disabled="busy"
          @click="onYesClick"
          data-testid="confirm-yes"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { computed, ref, watch, nextTick } from 'vue'
import { Dialog, Button } from 'primevue'

const props = defineProps({
  open: { type: Boolean, default: false },
  message: {
    type: String,
    default: '¿Estás seguro de realizar la acción?'
  },
  yesText: { type: String, required: true },
  noText: { type: String, required: true },
  onYes: { type: Function, required: true },
  onNo: { type: Function, required: true },
  busy: { type: Boolean, default: false },
  ariaLabel: { type: String, default: 'Ventana de confirmación' }
})

const emit = defineEmits(['update:open'])

const visible = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
})

const noBtnRef = ref(null)

watch(
  () => props.open,
  async (open) => {
    if (open) {
      await nextTick()
      // Intentar enfocar el botón "No" para evitar confirmaciones accidentales
      const el = noBtnRef.value?.$el ?? noBtnRef.value
      if (el && typeof el.focus === 'function') el.focus()
      else if (el && typeof el.querySelector === 'function') el.querySelector('button')?.focus?.()
    }
  }
)

function onYesClick() {
  if (props.busy) return
  try {
    props.onYes?.()
  } finally {
    emit('update:open', false)
  }
}

function onNoClick() {
  if (props.busy) return
  try {
    props.onNo?.()
  } finally {
    emit('update:open', false)
  }
}
</script>
