<template>
  <div>
    <div class="flex flex-col lg:flex-row gap-8 lg:gap-4">
      <div class="w-full lg:w-4/6">
        <h2 class="text-2xl font-bold mb-2">API Keys</h2>
        <p class="text-secondary">
          Gestiona la creación y permisos de las API Keys para integraciones externas.
        </p>
      </div>
      <div class="w-full lg:w-2/6 flex items-end">
        <div class="flex w-full gap-4">
          <div :class="canCreateApiKey ? 'w-1/2' : 'w-full'">
            <Button
              class="w-full"
              outlined
              @click="showFilters = showFilters === '1' ? '0' : '1'"
              :aria-expanded="showFilters === '1'"
              aria-controls="api-key-filters"
            >
              <Icon name="filter_list" class="mr-2" />
              Filtros
            </Button>
          </div>
          <div v-if="canCreateApiKey" class="w-1/2">
            <Button class="btn btn-primary w-full" @click="createModalOpen = true">
              <Icon name="add" /> Nueva API Key
            </Button>
          </div>
        </div>
      </div>
    </div>

    <Accordion v-model:value="showFilters" class="mt-8" id="api-key-filters">
      <AccordionPanel value="1" class="border-0!">
        <AccordionContent pt:content="p-0!">
          <div class="flex flex-wrap gap-4 mb-4">
            <div class="w-full lg:w-3/12">
              <InputTextWithIcon
                v-model="filters.cliente"
                placeholder="Nombre del cliente"
                :left-icon="{ name: 'search', color: 'text-secondary' }"
                input-class="pr-3! py-4!"
              />
            </div>
            <div class="w-full lg:w-1/6">
              <Dropdown
                v-model="filters.estado"
                :options="estadoOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Estado"
                class="w-full"
                showClear
              />
            </div>
            <div class="w-full lg:w-1/6">
              <Button
                class="btn btn-primary w-full h-full"
                title="Aplicar filtros"
                :disabled="loading"
                @click="applyFilters"
              >
                <Icon name="filter_list" /> Filtrar
              </Button>
            </div>
            <div class="w-full lg:w-1/6">
              <Button
                class="w-full h-full"
                variant="outlined"
                title="Limpiar filtros"
                aria-label="Limpiar filtros"
                :disabled="loading"
                @click="clearFilters"
              >
                <Icon name="mop" />
              </Button>
            </div>
          </div>
        </AccordionContent>
      </AccordionPanel>
    </Accordion>

    <section class="mt-6">
      <DataViewerComponent
        :items="items"
        :columns="columns"
        :rowActions="getRowActions"
        :rowKey="rowKey"
        :tableStyle="{ tableLayout: 'fixed', width: '100%' }"
      >
        <template #cell-id_estado="{ row }">
          <span
            :class="stateBadgeClass(row.id_estado)"
            class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold"
          >
            <Icon :name="row.id_estado === 'ACTIVO' ? 'check_circle' : 'block'" fontSize="18" />
            {{ stateLabel(row.id_estado) }}
          </span>
        </template>
        <template #cell-api_key="{ row }">
          <div class="flex items-center gap-2">
            <span class="font-mono text-sm break-all">{{ displayApiKey(row.api_key) }}</span>
            <Button
              variant="text"
              type="button"
              :disabled="!row.api_key"
              :aria-label="keyVisibility[row.api_key] ? 'Ocultar API Key' : 'Mostrar API Key'"
              @click="toggleKeyVisibility(row.api_key)"
            >
              <Icon :name="keyVisibility[row.api_key] ? 'visibility_off' : 'visibility'" />
            </Button>
          </div>
        </template>
        <template #cell-created_at="{ value }">
          <span>{{ formatDate(value) }}</span>
        </template>
      </DataViewerComponent>
    </section>

    <TablePagination
      v-model:page="pagination.current_page"
      v-model:pageSize="filters.per_page"
      :totalItems="pagination.total"
      @change="onPaginate"
    />

    <ApiKeyCreateModal
      v-model:open="createModalOpen"
      :permisos="permissions"
      @created="onApiKeyCreated"
    />

    <ApiKeyPermissionsModal
      v-model:open="permissionsModal.open"
      :apiKey="permissionsModal.apiKey"
      :permisos="permissions"
      @updated="onPermissionsUpdated"
    />

    <ConfirmModal
      v-model:open="confirm.open"
      :message="confirm.message"
      yes-text="Sí"
      no-text="No"
      :busy="confirm.busy"
      :on-yes="confirmYes"
      :on-no="confirmNo"
    />

    <Dialog
      v-model:visible="lastCreated.open"
      :modal="true"
      :draggable="false"
      :closable="true"
      pt:root="w-full md:!w-5/12 lg:!w-4/12"
    >
      <template #header>
        <div>
          <h3 class="text-xl font-semibold">API Key creada</h3>
          <p class="text-sm text-secondary">Comparte y almacena la llave de forma segura.</p>
        </div>
      </template>

      <div class="flex flex-col gap-4">
        <div>
          <span class="block text-xs uppercase text-secondary tracking-wide">Cliente</span>
          <span class="text-base font-medium">{{ lastCreated.cliente || 'No definido' }}</span>
        </div>
        <div
          class="bg-brand text-brand-contrast rounded-lg p-4 flex items-center justify-between gap-4"
        >
          <span class="font-mono text-sm break-all">{{ lastCreated.value }}</span>
          <Button
            variant="outlined"
            class="hover:bg-transparent!"
            @click="copyApiKey(lastCreated.value)"
          >
            <Icon name="content_copy" class="text-brand-contrast" />
          </Button>
        </div>
        <div v-if="lastCreated.permisos.length" class="flex flex-wrap gap-2">
          <span
            v-for="perm in lastCreated.permisos"
            :key="perm"
            class="px-2 py-1 bg-secondary-100 text-text rounded text-xs"
          >
            {{ perm }}
          </span>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end">
          <Button type="button" class="btn btn-primary" @click="lastCreated.open = false">
            Entendido
          </Button>
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { storeToRefs } from 'pinia'
import { Button, Dropdown, Accordion, AccordionPanel, AccordionContent, Dialog } from 'primevue'
import { toast } from 'vue3-toastify'
import DataViewerComponent from '@/shared/components/DataViewerComponent.vue'
import TablePagination from '@/shared/components/TablePagination.vue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'
import ConfirmModal from '@/shared/components/ConfirmModal.vue'
import ApiKeyCreateModal from '@/modules/api/components/ApiKeyCreateModal.vue'
import ApiKeyPermissionsModal from '@/modules/api/components/ApiKeyPermissionsModal.vue'
import { useApiKeysStore } from '@/modules/api/stores/api-keys'
import permisosService from '@/modules/authorization/services/permisos'
import { useValidatePermission } from '@/shared/composables/useValidatePermission'

const apiKeysStore = useApiKeysStore()
const { items, filters, pagination, loading } = storeToRefs(apiKeysStore)

const showFilters = ref('0')
const createModalOpen = ref(false)
const permissions = ref([])

const permissionsModal = reactive({ open: false, apiKey: null })

const confirm = reactive({
  open: false,
  busy: false,
  message: '¿Estás seguro de realizar la acción?',
  apiKey: null,
  onConfirm: null
})

const lastCreated = reactive({ open: false, value: '', cliente: '', permisos: [] })

const estadoOptions = [
  { label: 'Todos los estados', value: null },
  { label: 'Activos', value: 'ACTIVO' },
  { label: 'Inactivos', value: 'INACTIVO' }
]

// Permisos
const { can } = useValidatePermission()
const canCreateApiKey = computed(() => can('auth_api_key_guardar'))
const canToggleApiKeyState = computed(() => can('auth_api_key_estado'))
const canSyncApiKeyPermissions = computed(() => can('auth_api_key_permisos_sincronizar'))

const columns = [
  { field: 'cliente', header: 'Cliente', style: { minWidth: '200px' } },
  { field: 'id_estado', header: 'Estado', style: { width: '160px' } },
  { field: 'api_key', header: 'API Key', style: { minWidth: '280px' } },
  { field: 'created_at', header: 'Creada el', style: { width: '200px' } }
]

const keyVisibility = reactive({})

function rowKey(row) {
  return row?.api_key || row?.cliente || ''
}

function stateLabel(value) {
  if (value === 'ACTIVO') return 'Activo'
  if (value === 'INACTIVO') return 'Inactivo'
  return value || 'No definido'
}

function stateBadgeClass(value) {
  if (value === 'ACTIVO') return 'bg-success/10 text-success border border-success/30'
  if (value === 'INACTIVO') return 'bg-danger/10 text-danger border border-danger/30'
  return 'bg-secondary-100 text-secondary border border-secondary-100'
}

function displayApiKey(apiKey) {
  if (!apiKey) return 'No definido'
  return keyVisibility[apiKey] ? apiKey : apiKey.replace(/[A-Za-z0-9]/g, '*')
}

function toggleKeyVisibility(apiKey) {
  if (!apiKey) return
  keyVisibility[apiKey] = !keyVisibility[apiKey]
}

async function copyApiKey(apiKey) {
  if (!apiKey) return
  try {
    const hasClipboard = typeof navigator !== 'undefined' && navigator.clipboard?.writeText
    if (hasClipboard) {
      await navigator.clipboard.writeText(apiKey)
    } else if (typeof document !== 'undefined') {
      const textarea = document.createElement('textarea')
      textarea.value = apiKey
      textarea.setAttribute('readonly', '')
      textarea.style.position = 'absolute'
      textarea.style.left = '-9999px'
      document.body.appendChild(textarea)
      textarea.select()
      document.execCommand('copy')
      document.body.removeChild(textarea)
    } else {
      throw new Error('Clipboard no disponible')
    }
    toast.success('API Key copiada al portapapeles')
  } catch (error) {
    toast.error(error?.message || 'No se pudo copiar la API Key')
  }
}

async function loadPermissions() {
  try {
    const { data } = await permisosService.listar({ per_page: 200 })
    permissions.value = Array.isArray(data) ? data : []
  } catch (error) {
    toast.error(error?.message || 'No se pudieron cargar los permisos disponibles')
    permissions.value = []
  }
}

async function refreshList() {
  try {
    await apiKeysStore.listar()
  } catch (error) {
    toast.error(error?.message || 'No se pudieron cargar las API Keys')
  }
}

async function applyFilters() {
  apiKeysStore.setPage(1)
  await refreshList()
}

async function clearFilters() {
  filters.value.cliente = ''
  filters.value.estado = null
  apiKeysStore.setPage(1)
  await refreshList()
}

async function onPaginate({ page, pageSize }) {
  if (pageSize !== filters.value.per_page) {
    apiKeysStore.setPerPage(pageSize)
  }
  if (page !== pagination.value.current_page) {
    apiKeysStore.setPage(page)
  }
  await refreshList()
}

function openPermissions(row) {
  permissionsModal.apiKey = row
  permissionsModal.open = true
}

async function onPermissionsUpdated() {
  permissionsModal.open = false
  permissionsModal.apiKey = null
  await refreshList()
}

function onToggleEstado(row) {
  const isActive = row?.id_estado === 'ACTIVO'
  confirm.apiKey = row
  confirm.message = isActive
    ? `¿Deseas desactivar la API Key de "${row?.cliente || 'sin cliente'}"?`
    : `¿Deseas activar la API Key de "${row?.cliente || 'sin cliente'}"?`
  confirm.onConfirm = async () => {
    await apiKeysStore.alternarEstado(row.api_key)
    await refreshList()
  }
  confirm.open = true
}

async function confirmYes() {
  if (confirm.busy || typeof confirm.onConfirm !== 'function') return
  try {
    confirm.busy = true
    await confirm.onConfirm()
  } finally {
    confirm.busy = false
    confirm.apiKey = null
    confirm.onConfirm = null
  }
}

function confirmNo() {
  confirm.apiKey = null
  confirm.onConfirm = null
}

function getRowActions(row) {
  const visible = !!keyVisibility[row.api_key]
  const isActive = row?.id_estado === 'ACTIVO'
  const items = []
  items.push({
    label: visible ? 'Ocultar API Key' : 'Mostrar API Key',
    icon: visible ? 'visibility_off' : 'visibility',
    command: () => toggleKeyVisibility(row.api_key)
  })
  items.push({ label: 'Copiar API Key', icon: 'content_copy', command: () => copyApiKey(row.api_key) })
  if (canSyncApiKeyPermissions.value || canToggleApiKeyState.value) items.push({ separator: true })
  if (canSyncApiKeyPermissions.value)
    items.push({ label: 'Editar permisos', icon: 'lock', command: () => openPermissions(row) })
  if (canSyncApiKeyPermissions.value && canToggleApiKeyState.value) items.push({ separator: true })
  if (canToggleApiKeyState.value)
    items.push({
      label: isActive ? 'Desactivar' : 'Activar',
      icon: isActive ? 'block' : 'check_circle',
      command: () => onToggleEstado(row)
    })
  return items.filter((it, idx, arr) => {
    if (!it.separator) return true
    const prev = arr[idx - 1]
    const next = arr[idx + 1]
    return prev && !prev.separator && next && !next.separator
  })
}

function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${day}/${month}/${year} ${hours}:${minutes}`
}

async function onApiKeyCreated(payload) {
  await refreshList()
  if (payload?.apiKey) {
    keyVisibility[payload.apiKey] = true
    lastCreated.value = payload.apiKey
    lastCreated.cliente = payload?.cliente || ''
    lastCreated.permisos = Array.isArray(payload?.permisos) ? payload.permisos : []
    lastCreated.open = true
  }
}

onMounted(async () => {
  await Promise.all([refreshList(), loadPermissions()])
})
</script>
