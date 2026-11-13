<template>
  <div>
    <div class="flex flex-col lg:flex-row gap-8 lg:gap-4">
      <div class="w-full lg:w-4/6">
        <h2 class="text-2xl font-bold mb-2">Logs de peticiones API</h2>
        <p class="text-secondary">
          Consulta el historial de solicitudes procesadas por el middleware de auditoría
        </p>
      </div>
      <div class="w-full lg:w-2/6">
        <div class="flex justify-end">
          <Button
            class="mb-4 w-full lg:w-1/2"
            outlined
            @click="showFilters = showFilters === '1' ? '0' : '1'"
            :aria-expanded="showFilters.toString()"
            aria-controls="request-logs-filters"
          >
            <Icon name="filter_list" class="mr-2" />
            Filtros
          </Button>
        </div>
      </div>
    </div>

    <Accordion v-model:value="showFilters" class="mt-6" id="request-logs-filters">
      <AccordionPanel value="1" class="border-0!">
        <AccordionContent pt:content="p-0!">
          <div class="flex flex-wrap lg:flex-nowrap gap-4 mb-4">
            <div class="w-full lg:w-3/12">
              <InputTextWithIcon
                v-model="filters.uri"
                placeholder="URI"
                :left-icon="{ name: 'travel_explore', color: 'text-secondary' }"
                input-class="pr-3! py-4!"
              />
            </div>
            <div class="w-full lg:w-2/12">
              <Select
                v-model="filters.type"
                :options="httpMethodOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Método"
                showClear
                fluid
              />
            </div>
            <div class="w-full lg:w-2/12">
              <DatePicker
                v-model="filters.creation_date"
                showIcon
                iconDisplay="input"
                placeholder="Fecha"
                dateFormat="yy-mm-dd"
                fluid
              />
            </div>
            <div class="w-full lg:w-2/12">
              <Select
                v-model="filters.order"
                :options="orderOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Orden"
                fluid
              />
            </div>
            <div class="flex w-full lg:w-3/12 gap-4 lg:justify-end">
              <Button class="btn btn-primary w-1/2" @click="applyFilters">
                <Icon name="filter_alt" class="mr-2" />
                Aplicar
              </Button>
              <Button class="w-1/2" outlined @click="onClearFilters">
                <Icon name="mop" class="mr-2" />
                Limpiar
              </Button>
            </div>
          </div>
        </AccordionContent>
      </AccordionPanel>
    </Accordion>

    <DataViewerComponent class="mt-6" :items="requestLogs" :columns="columns" rowKey="uuid">
      <template #cell-method="{ row }">
        <span
          class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium uppercase"
          :class="getMethodBadgeClasses(row.method)"
        >
          {{ row.method || 'No definido' }}
        </span>
      </template>

      <template #cell-status_code="{ row }">
        <span
          class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold"
          :class="getStatusBadgeClasses(row.status_code)"
        >
          {{ row.status_code ?? 'No definido' }}
        </span>
      </template>

      <template #cell-creation_date="{ row }">
        <span>{{ formatDateTime(row.creation_date) }}</span>
      </template>

      <template #cell-query="{ row }">
        <span class="font-mono text-xs text-secondary break-words">
          {{ formatStructuredData(row.query) }}
        </span>
      </template>

      <template #cell-payload="{ row }">
        <span class="font-mono text-xs text-secondary break-words">
          {{ formatStructuredData(row.payload) }}
        </span>
      </template>

      <template #cell-headers="{ row }">
        <div class="flex flex-col gap-2">
          <span class="font-mono text-xs text-secondary break-words">
            {{ getHeadersPreview(row).preview }}
          </span>
          <Button
            v-if="getHeadersPreview(row).truncated"
            class="self-start px-0"
            text
            severity="secondary"
            @click="openHeadersModal(row)"
            :aria-label="`Ver headers completos de ${row.uri || 'registro'}`"
          >
            <Icon name="open_in_full" class="mr-1" />
            Ver completo
          </Button>
        </div>
      </template>

      <template #cell-response="{ row }">
        <div class="flex flex-col gap-2">
          <span class="font-mono text-xs text-secondary break-words">
            {{ getResponsePreview(row).preview }}
          </span>
          <Button
            v-if="getResponsePreview(row).truncated"
            class="self-start px-0"
            text
            severity="secondary"
            @click="openResponseModal(row)"
            :aria-label="`Ver respuesta completa de ${row.uri || 'registro'}`"
          >
            <Icon name="open_in_full" class="mr-1" />
            Ver completo
          </Button>
        </div>
      </template>
    </DataViewerComponent>

    <TablePagination
      class="mt-6"
      :totalItems="pagination.total"
      v-model:page="pagination.current_page"
      v-model:pageSize="filters.per_page"
      @change="onPaginate"
    />

    <Dialog
      v-model:visible="isResponseModalOpen"
      modal
      dismissableMask
      pt:root="w-full lg:max-w-[50vw]!"
      @hide="onHideResponseModal"
    >
      <template #header>
        <div class="flex items-center gap-2">
          <Icon name="visibility" class="text-xl text-brand" />
          <span class="font-semibold text-lg text-text">Respuesta completa</span>
        </div>
      </template>

      <pre
        class="bg-secondary-100 border border-secondary-100 rounded-md p-3 text-xs text-text whitespace-pre-wrap break-words max-h-80 overflow-auto"
      >
        {{ responseModalDetails.content }}
      </pre>
    </Dialog>

    <Dialog
      v-model:visible="isHeadersModalOpen"
      modal
      dismissableMask
      pt:root="w-full lg:max-w-[50vw]!"
      @hide="onHideHeadersModal"
    >
      <template #header>
        <div class="flex items-center gap-2">
          <Icon name="inventory_2" class="text-xl text-brand" />
          <span class="font-semibold text-lg text-text">Headers completos</span>
        </div>
      </template>
      <pre
        class="bg-secondary-100 border border-secondary-100 rounded-md p-3 text-xs text-text whitespace-pre-wrap break-words max-h-80 overflow-auto"
      >
        {{ headersModalContent }}
      </pre>
    </Dialog>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import {
  Button,
  DatePicker,
  Select,
  Accordion,
  AccordionPanel,
  AccordionContent,
  Dialog
} from 'primevue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'
import DataViewerComponent from '@/shared/components/DataViewerComponent.vue'
import TablePagination from '@/shared/components/TablePagination.vue'
import { useRequestLogsStore } from '@/modules/root/stores/logs'

const requestLogsStore = useRequestLogsStore()
const { items: requestLogs, filters, pagination } = storeToRefs(requestLogsStore)

const showFilters = ref('0')

const httpMethodOptions = [
  { label: 'GET', value: 'GET' },
  { label: 'POST', value: 'POST' },
  { label: 'PUT', value: 'PUT' },
  { label: 'PATCH', value: 'PATCH' },
  { label: 'DELETE', value: 'DELETE' }
]

const orderOptions = [
  { label: 'Descendente', value: 'desc' },
  { label: 'Ascendente', value: 'asc' }
]

const RESPONSE_PREVIEW_LIMIT = 200
const HEADERS_PREVIEW_LIMIT = 150

const columns = [
  { field: 'method', header: 'Método', style: { width: '150px', minWidth: '150px' } },
  { field: 'uri', header: 'URI', style: { width: '250px', minWidth: '250px' } },
  { field: 'status_code', header: 'Código', style: { width: '150px', minWidth: '150px' } },
  { field: 'creation_date', header: 'Creación', style: { width: '250px', minWidth: '250px' } },
  { field: 'query', header: 'Query', style: { width: '250px', minWidth: '250px' } },
  { field: 'payload', header: 'Payload', style: { width: '250px', minWidth: '250px' } },
  { field: 'headers', header: 'Headers', style: { width: '300px', minWidth: '300px' } },
  { field: 'response', header: 'Respuesta', style: { width: '300px', minWidth: '300px' } }
]

function getMethodBadgeClasses(method) {
  const normalized = String(method || '').toUpperCase()
  const map = {
    GET: 'border-success/30 bg-success/10 text-success',
    POST: 'border-info/30 bg-info/10 text-info',
    PUT: 'border-info/30 bg-info/10 text-info',
    PATCH: 'border-warning/30 bg-warning/10 text-warning',
    DELETE: 'border-danger/30 bg-danger/10 text-danger'
  }
  return map[normalized] || 'border-secondary-100 bg-secondary-100 text-secondary'
}

function getStatusBadgeClasses(statusCode) {
  const code = Number(statusCode)
  if (Number.isNaN(code)) return 'border-secondary-100 bg-secondary-100 text-secondary'
  if (code >= 200 && code < 300) return 'border-success/30 bg-success/10 text-success'
  if (code >= 300 && code < 400) return 'border-warning/30 bg-warning/10 text-warning'
  if (code >= 400 && code < 500) return 'border-danger/30 bg-danger/10 text-danger'
  if (code >= 500) return 'border-danger/30 bg-danger/10 text-danger'
  return 'border-secondary-100 bg-secondary-100 text-secondary'
}

function formatDateTime(value) {
  if (!value) return 'No definido'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return String(value)
  const day = String(parsed.getDate()).padStart(2, '0')
  const month = String(parsed.getMonth() + 1).padStart(2, '0')
  const year = parsed.getFullYear()
  const hours = String(parsed.getHours()).padStart(2, '0')
  const minutes = String(parsed.getMinutes()).padStart(2, '0')
  const seconds = String(parsed.getSeconds()).padStart(2, '0')
  return `${day}-${month}-${year} ${hours}:${minutes}:${seconds}`
}

function toStructuredString(value, { pretty = false } = {}) {
  if (value === null || typeof value === 'undefined') return 'No definido'
  if (typeof value === 'string') {
    const trimmed = value.trim()
    if (!trimmed) return 'No definido'
    if (pretty && (trimmed.startsWith('{') || trimmed.startsWith('['))) {
      try {
        const parsed = JSON.parse(trimmed)
        return JSON.stringify(parsed, null, 2)
      } catch (error) {
        console.error(error)
      }
    }
    return trimmed
  }
  try {
    if (Array.isArray(value)) {
      if (value.length === 0) return '[]'
      return pretty ? JSON.stringify(value, null, 2) : JSON.stringify(value)
    }
    if (typeof value === 'object') {
      if (!Object.keys(value).length) return '{}'
      return pretty ? JSON.stringify(value, null, 2) : JSON.stringify(value)
    }
  } catch (error) {
    console.error(error)
  }
  return String(value)
}

function formatStructuredData(value) {
  return toStructuredString(value)
}

async function applyFilters() {
  requestLogsStore.setPage(1)
  await requestLogsStore.listar()
}

async function onClearFilters() {
  requestLogsStore.resetFilters()
  await requestLogsStore.listar()
}

const responsePreviewCache = new WeakMap()
const headersPreviewCache = new WeakMap()
const DEFAULT_RESPONSE_MODAL = {
  method: 'No definido',
  uri: 'No definido',
  statusCode: 'No definido',
  creationDate: 'No definido',
  content: 'No definido'
}

const isResponseModalOpen = ref(false)
const responseModalDetails = ref({ ...DEFAULT_RESPONSE_MODAL })
const isHeadersModalOpen = ref(false)
const headersModalContent = ref('No definido')

function getResponsePreview(row) {
  if (!row) {
    return { preview: 'No definido', full: 'No definido', truncated: false }
  }
  if (!responsePreviewCache.has(row)) {
    const full = toStructuredString(row.response)
    const truncated = full.length > RESPONSE_PREVIEW_LIMIT
    const preview = truncated ? `${full.slice(0, RESPONSE_PREVIEW_LIMIT)}…` : full
    responsePreviewCache.set(row, { preview, full, truncated })
  }
  return responsePreviewCache.get(row)
}

function openResponseModal(row) {
  responseModalDetails.value = {
    method: row.method || 'No definido',
    uri: row.uri || 'No definido',
    statusCode: row.status_code ?? 'No definido',
    creationDate: formatDateTime(row.creation_date),
    content: toStructuredString(row.response, { pretty: true })
  }
  isResponseModalOpen.value = true
}

function onHideResponseModal() {
  responseModalDetails.value = { ...DEFAULT_RESPONSE_MODAL }
}

function getHeadersPreview(row) {
  if (!row) {
    return { preview: 'No definido', truncated: false }
  }
  if (!headersPreviewCache.has(row)) {
    const full = toStructuredString(row.headers)
    const truncated = full.length > HEADERS_PREVIEW_LIMIT
    const preview = truncated ? `${full.slice(0, HEADERS_PREVIEW_LIMIT)}…` : full
    headersPreviewCache.set(row, { preview, truncated })
  }
  return headersPreviewCache.get(row)
}

function openHeadersModal(row) {
  headersModalContent.value = toStructuredString(row.headers, { pretty: true })
  isHeadersModalOpen.value = true
}

function onHideHeadersModal() {
  headersModalContent.value = 'No definido'
}

async function onPaginate({ page, pageSize }) {
  if (pageSize !== filters.value.per_page) {
    requestLogsStore.setPerPage(pageSize)
  }
  if (page !== pagination.value.current_page) {
    requestLogsStore.setPage(page)
  }
  await requestLogsStore.listar()
}

onMounted(() => {
  requestLogsStore.listar()
})
</script>
