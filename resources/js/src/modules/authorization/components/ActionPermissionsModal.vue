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
    pt:root="w-full md:!w-6/12 lg:!w-4/12 z-50 bg-surface-card border-surface-0 dark:border-surface-900 mx-4 md:mx-0"
  >
    <template #header>
      <div>
        <h3 class="text-xl font-semibold">Permisos del paquete</h3>
        <p class="text-sm text-secondary">
          {{ actionLabel }}
        </p>
      </div>
    </template>

    <div class="mb-3">
      <div class="relative">
        <InputTextWithIcon
          v-model="search"
          placeholder="Buscar permiso o descripción"
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

    <DataTable :value="filteredPermisos" :scrollable="false" :tableStyle="{ width: '100%' }">
      <Column field="permiso" header="Permiso" :style="{ width: '45%' }" />
      <Column field="descripcion" header="Descripción" />
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
  action: { type: Object, default: null },
  permisos: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:open'])

const visible = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
})

const actionLabel = computed(() => {
  const nombre = props.action?.accion || '-'
  const grupo = props.action?.grupo?.nombre || props.action?.grupo?.id_grupo || '-'
  return `${nombre} · Grupo ${grupo}`
})

const search = ref('')
const filteredPermisos = computed(() => {
  const term = (search.value || '').toLowerCase()
  if (!term) return props.permisos || []
  return (props.permisos || []).filter(
    (perm) =>
      perm.permiso?.toLowerCase().includes(term) ||
      perm.descripcion?.toLowerCase().includes(term)
  )
})

function onClose() {
  emit('update:open', false)
}
</script>
