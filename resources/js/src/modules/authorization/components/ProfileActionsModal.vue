<template>
  <transition name="fade">
    <div
      v-if="visible"
      class="fixed inset-0 z-40 bg-overlay backdrop-blur-xs transition-all duration-300"
      @click="onClose"
    />
  </transition>

  <Dialog
    v-model:visible="visible"
    :modal="true"
    :draggable="false"
    :closable="false"
    pt:root="w-full md:!w-7/12 lg:!w-5/12 z-50 bg-surface-card border-surface-0 dark:border-surface-900 mx-4 md:mx-0"
  >
    <template #header>
      <h3 class="text-xl font-semibold">Acciones del perfil</h3>
    </template>

    <div class="mb-3 text-sm text-secondary">
      <span>Código:</span>
      <span class="ml-1 text-text">{{ profile?.codigo || '-' }}</span>
      <span class="ml-4">Nombre:</span>
      <span class="ml-1 text-text">{{ profile?.nombre || '-' }}</span>
    </div>

    <!-- Buscador -->
    <div class="mb-3">
      <div class="relative">
        <InputTextWithIcon
          v-model="search"
          placeholder="Buscar acción o grupo"
          :left-icon="{ name: 'search', color: 'text-secondary' }"
          input-class="pr-3! py-3!"
        />
        <Button
          v-if="search"
          type="button"
          variant="text"
          class="!p-0 absolute! top-1/2 -translate-y-1/2 right-2"
          aria-label="Limpiar búsqueda"
          title="Limpiar búsqueda"
          @click="search = ''"
        >
          <Icon name="close" />
        </Button>
      </div>
    </div>

    <DataTable :value="filteredAcciones" :scrollable="false" :tableStyle="{ width: '100%' }">
      <Column field="grupo" header="Grupo" :style="{ width: '35%' }" />
      <Column field="accion" header="Acción" />
    </DataTable>

    <template #footer>
      <div class="flex justify-end">
        <Button
          type="button"
          severity="secondary"
          outlined
          class="w-full lg:w-2/6"
          @click="onClose"
        >
          Cerrar
        </Button>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Dialog, Button, DataTable, Column } from 'primevue'
import Icon from '@/shared/components/Icon.vue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  profile: { type: Object, default: null },
  acciones: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:open'])

const visible = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
})

const accionesAsociadas = computed(() => {
  const ids = Array.isArray(props.profile?.acciones)
    ? props.profile.acciones.map((a) => (typeof a === 'string' ? a : a?.id_accion)).filter(Boolean)
    : []
  const map = new Map((props.acciones || []).map((a) => [a.id_accion, a]))
  return ids
    .map((id) => map.get(id))
    .filter(Boolean)
    .map((a) => ({
      grupo: a?.grupo?.nombre || a?.grupo?.id_grupo || '-',
      accion: a?.accion || '-'
    }))
})

const search = ref('')
const filteredAcciones = computed(() => {
  const term = (search.value || '').toLowerCase()
  if (!term) return accionesAsociadas.value
  return accionesAsociadas.value.filter(
    (x) => x.grupo?.toLowerCase().includes(term) || x.accion?.toLowerCase().includes(term)
  )
})

function onClose() {
  emit('update:open', false)
}
</script>
