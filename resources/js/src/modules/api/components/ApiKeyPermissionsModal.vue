<template>
  <transition name="fade">
    <div
      v-if="visible"
      class="fixed inset-0 z-40 bg-overlay backdrop-blur-xs transition-all duration-300"
      @click="handleClose"
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
      <div>
        <h3 class="text-xl font-semibold">Editar permisos</h3>
        <p class="text-sm text-secondary">{{ apiKeyLabel }}</p>
      </div>
    </template>

    <div class="flex flex-col gap-6">
      <div
        class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-secondary-100 border border-secondary-100 rounded-lg p-4"
      >
        <div>
          <span class="block text-xs uppercase text-secondary tracking-wide">Cliente</span>
          <span class="text-base font-medium text-text">{{
            props.apiKey?.cliente || 'No definido'
          }}</span>
        </div>
        <div>
          <span class="block text-xs uppercase text-secondary tracking-wide">Estado</span>
          <span class="text-base font-medium" :class="stateClass">{{ estadoLabel }}</span>
        </div>
        <div class="md:col-span-2">
          <span class="block text-xs uppercase text-secondary tracking-wide">API Key</span>
          <span class="font-mono text-sm break-all">{{
            props.apiKey?.api_key || 'No definido'
          }}</span>
        </div>
      </div>

      <div>
        <label for="permisos" class="block text-sm text-secondary mb-2">Permisos</label>
        <Dropdown
          id="permisos"
          v-model="selectedPermission"
          :options="permissionOptions"
          optionLabel="label"
          optionValue="permiso"
          placeholder="Selecciona un permiso"
          class="w-full"
          showClear
          :filter="true"
          filterPlaceholder="Buscar permiso"
          @change="onSelectPermission($event.value)"
        />
        <p v-if="errors.permisos" class="text-danger text-xs mt-1">{{ errors.permisos }}</p>
      </div>

      <div>
        <h4 class="text-sm font-semibold text-secondary mb-3">Permisos seleccionados</h4>
        <div v-if="selectedPermissions.length" class="border border-secondary-100 rounded-lg">
          <DataTable
            :value="selectedPermissions"
            dataKey="permiso"
            :virtualScrollerOptions="{ itemSize: 44 }"
          >
            <Column field="permiso" header="Permiso" :style="{ width: '45%' }" />
            <Column field="descripcion" header="Descripción" />
            <Column header="" :style="{ width: '80px' }">
              <template #body="{ data }">
                <Button
                  type="button"
                  variant="text"
                  aria-label="Remover permiso"
                  @click="removePermission(data.permiso)"
                >
                  <Icon name="delete" />
                </Button>
              </template>
            </Column>
          </DataTable>
        </div>
        <p v-else class="text-sm text-secondary">Sin permisos asociados.</p>
      </div>
    </div>

    <template #footer>
      <div class="flex flex-col lg:flex-row gap-3 w-full justify-end">
        <Button
          type="button"
          severity="secondary"
          outlined
          class="w-full lg:w-1/6"
          :disabled="submitting"
          @click="handleClose"
        >
          Cancelar
        </Button>
        <Button
          type="button"
          class="btn btn-primary w-full lg:w-2/6"
          :disabled="submitting"
          @click="handleSubmit"
        >
          <template v-if="submitting">
            <Icon name="progress_activity" class="animate-spin" />
            <span class="ml-2">Guardando...</span>
          </template>
          <template v-else>
            <span class="ml-2">Guardar cambios</span>
          </template>
        </Button>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Dialog, Button, Dropdown, DataTable, Column } from 'primevue'
import { toast } from 'vue3-toastify'
import Icon from '@/shared/components/Icon.vue'
import { useApiKeysStore } from '@/modules/api/stores/api-keys'

const props = defineProps({
  open: { type: Boolean, default: false },
  apiKey: { type: Object, default: null },
  permisos: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:open', 'updated'])

const visible = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value)
})

const form = reactive({ permisos: [] })
const errors = reactive({ permisos: '' })
const submitting = ref(false)
const store = useApiKeysStore()

const permissionsCatalog = computed(() => {
  if (!Array.isArray(props.permisos)) return []
  return props.permisos
    .map((permiso) => ({
      permiso: permiso?.permiso ?? '',
      descripcion: permiso?.descripcion ?? '',
      label: permiso?.permiso
        ? `${permiso.permiso} · ${permiso.descripcion || 'Sin descripción'}`
        : ''
    }))
    .filter((item) => !!item.permiso)
})

const permissionOptions = computed(() => {
  const selected = new Set(Array.isArray(form.permisos) ? form.permisos : [])
  return permissionsCatalog.value.filter((item) => !selected.has(item.permiso))
})

const permissionsMap = computed(
  () => new Map(permissionsCatalog.value.map((item) => [item.permiso, item]))
)

const selectedPermission = ref(null)

const selectedPermissions = computed(() => {
  if (!form.permisos?.length) return []
  const map = permissionsMap.value
  return form.permisos
    .map((code) => map.get(code) || { permiso: code, descripcion: 'Sin descripción' })
    .filter(
      (item, index, arr) =>
        !!item.permiso && arr.findIndex((entry) => entry.permiso === item.permiso) === index
    )
})

const estadoLabel = computed(() => props.apiKey?.id_estado || 'No definido')
const stateClass = computed(() =>
  estadoLabel.value === 'ACTIVO'
    ? 'text-success'
    : estadoLabel.value === 'INACTIVO'
    ? 'text-danger'
    : 'text-secondary'
)

const apiKeyLabel = computed(() => {
  if (!props.apiKey) return 'API key no seleccionada'
  return `${props.apiKey.cliente || 'Sin cliente'} · ${props.apiKey.api_key || ''}`
})

function removePermission(code) {
  form.permisos = form.permisos.filter((permiso) => permiso !== code)
}

function hydrateForm() {
  form.permisos = Array.isArray(props.apiKey?.permisos) ? props.apiKey.permisos.filter(Boolean) : []
  errors.permisos = ''
  selectedPermission.value = null
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      hydrateForm()
    }
  }
)

watch(
  () => props.apiKey,
  () => {
    if (props.open) hydrateForm()
  },
  { deep: true }
)

function handleClose() {
  emit('update:open', false)
}

function onSelectPermission(value) {
  if (!value) {
    selectedPermission.value = null
    return
  }
  if (!Array.isArray(form.permisos)) form.permisos = []
  if (!form.permisos.includes(value)) {
    form.permisos.push(value)
  }
  selectedPermission.value = null
}

async function handleSubmit() {
  errors.permisos = ''
  submitting.value = true
  try {
    const apiKeyId = props.apiKey?.api_key
    if (!apiKeyId) {
      toast.error('No se encontró la API Key seleccionada')
      submitting.value = false
      return
    }
    const response = await store.sincronizarPermisos(
      apiKeyId,
      Array.isArray(form.permisos) ? form.permisos : []
    )
    if (response?.message) {
      toast.success(response.message)
    }
    emit('updated', response?.data || { permisos: form.permisos })
    emit('update:open', false)
  } catch (error) {
    if (error?.details?.permisos?.length) {
      errors.permisos = error.details.permisos[0]
    }
    if (error?.message && !errors.permisos) {
      toast.error(error.message)
    }
  } finally {
    submitting.value = false
  }
}
</script>
