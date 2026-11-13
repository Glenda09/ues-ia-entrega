<template>
  <div>
    <div class="flex flex-col lg:flex-row gap-8 lg:gap-4">
      <div class="w-full lg:w-4/6">
        <h2 class="text-2xl font-bold mb-2">Colas de trabajo</h2>
        <p class="text-secondary">Monitorea los jobs pendientes</p>
      </div>
      <div class="w-full lg:w-2/6">
        <div class="flex flex-col lg:flex-row justify-end gap-3">
          <Button
            class="mb-4 w-full lg:w-1/2"
            outlined
            @click="showFilters = showFilters === '1' ? '0' : '1'"
            :aria-expanded="showFilters.toString()"
            aria-controls="jobs-filters"
          >
            <Icon name="filter_list" class="mr-2" />
            Filtros
          </Button>
          <Button
            class="mb-4 w-full lg:w-1/2"
            :loading="testing"
            :aria-busy="testing"
            @click="onQueueTest"
          >
            <Icon name="science" class="mr-2" />
            Test
          </Button>
        </div>
      </div>
    </div>

    <Accordion v-model:value="showFilters" class="mt-6" id="jobs-filters">
      <AccordionPanel value="1" class="border-0!">
        <AccordionContent pt:content="p-0!">
          <div class="flex flex-wrap gap-4 mb-4">
            <div class="w-full lg:w-3/12">
              <InputTextWithIcon
                v-model="filters.queue"
                placeholder="Cola"
                :left-icon="{ name: 'storage', color: 'text-secondary' }"
                input-class="pr-3! py-4!"
              />
            </div>
            <div class="w-full lg:w-2/12">
              <DatePicker
                v-model="filters.created_at"
                showIcon
                iconDisplay="input"
                placeholder="Fecha de creación"
                dateFormat="yy-mm-dd"
                fluid
              />
            </div>
            <div class="w-full lg:w-2/12">
              <Dropdown
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

    <DataViewerComponent class="mt-6" :items="jobs" :columns="columns" rowKey="id">
      <template #cell-queue="{ row }">
        <span>{{ row.queue || 'No definido' }}</span>
      </template>
      <template #cell-created_at="{ row }">
        <span>{{ formatDateTime(row.created_at) }}</span>
      </template>
      <template #cell-id="{ row }">
        <span class="font-mono text-xs text-secondary break-all">
          {{ row.id || 'No definido' }}
        </span>
      </template>
      <template #cell-payload="{ row }">
        <div class="flex flex-col gap-2">
          <span class="block font-mono text-xs text-secondary whitespace-pre-wrap break-words">
            {{ previewPayload(row.payload) }}
          </span>
          <Button
            v-if="shouldShowExpand(row.payload)"
            text
            size="small"
            class="self-start"
            @click="openPayloadModal(row.payload)"
          >
            <Icon name="open_in_full" class="mr-2 text-xs" />
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
      v-model:visible="payloadModal.open"
      modal
      header="Payload completo"
      pt:root="w-full lg:max-w-[50vw]!"
    >
      <template #header>
        <div class="flex items-center gap-2">
          <Icon name="article" class="text-xl text-brand" />
          <span class="font-semibold text-lg text-text">Payload completo</span>
        </div>
      </template>
      <pre
        class="bg-secondary-100 border border-secondary-100 rounded-md p-3 max-h-[60vh] overflow-auto whitespace-pre-wrap break-words font-mono text-xs text-text"
      >
        {{ payloadModal.content }}
      </pre>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import {
  Button,
  DatePicker,
  Dropdown,
  Dialog,
  Accordion,
  AccordionPanel,
  AccordionContent
} from 'primevue'
import { useJobsStore } from '@/modules/root/stores/jobs'
import DataViewerComponent from '@/shared/components/DataViewerComponent.vue'
import TablePagination from '@/shared/components/TablePagination.vue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'

const jobsStore = useJobsStore()
const { items: jobs, filters, pagination, testing } = storeToRefs(jobsStore)

const showFilters = ref('0')

const orderOptions = [
  { label: 'Descendente', value: 'desc' },
  { label: 'Ascendente', value: 'asc' }
]

const MAX_PAYLOAD_PREVIEW_LENGTH = 150

const columns = [
  { field: 'id', header: 'ID', style: { width: '150px', minWidth: '150px' } },
  { field: 'queue', header: 'Cola', style: { width: '150px', minWidth: '150px' } },
  { field: 'created_at', header: 'Creado', style: { width: '150px', minWidth: '150px' } },
  { field: 'payload', header: 'Payload', style: { width: '350px', minWidth: '350px' } }
]

const payloadModal = ref({
  open: false,
  content: ''
})

function formatDateTime(value) {
  if (!value) return 'No definido'
  const parsed = new Date(value)
  if (!Number.isNaN(parsed.getTime())) {
    const day = String(parsed.getDate()).padStart(2, '0')
    const month = String(parsed.getMonth() + 1).padStart(2, '0')
    const year = parsed.getFullYear()
    const hours = String(parsed.getHours()).padStart(2, '0')
    const minutes = String(parsed.getMinutes()).padStart(2, '0')
    const seconds = String(parsed.getSeconds()).padStart(2, '0')
    return `${day}-${month}-${year} ${hours}:${minutes}:${seconds}`
  }
  if (typeof value === 'number') {
    const fromEpoch = new Date(value * 1000)
    if (!Number.isNaN(fromEpoch.getTime())) {
      const day = String(fromEpoch.getDate()).padStart(2, '0')
      const month = String(fromEpoch.getMonth() + 1).padStart(2, '0')
      const year = fromEpoch.getFullYear()
      const hours = String(fromEpoch.getHours()).padStart(2, '0')
      const minutes = String(fromEpoch.getMinutes()).padStart(2, '0')
      const seconds = String(fromEpoch.getSeconds()).padStart(2, '0')
      return `${day}-${month}-${year} ${hours}:${minutes}:${seconds}`
    }
  }
  return String(value)
}

function normalizePayload(payload, { pretty = false } = {}) {
  if (payload === null || payload === undefined || payload === '') return 'No definido'
  try {
    if (typeof payload === 'string') {
      const trimmed = payload.trim()
      if (!trimmed.length) return 'No definido'
      if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
        const parsed = JSON.parse(trimmed)
        return pretty ? JSON.stringify(parsed, null, 2) : JSON.stringify(parsed)
      }
      return trimmed
    }
    if (typeof payload === 'object') {
      if (Array.isArray(payload) && payload.length === 0) return '[]'
      if (!Array.isArray(payload) && !Object.keys(payload).length) return '{}'
      return pretty ? JSON.stringify(payload, null, 2) : JSON.stringify(payload)
    }
    return String(payload)
  } catch (error) {
    console.error(error)
    return String(payload)
  }
}

function previewPayload(payload) {
  const normalized = normalizePayload(payload, { pretty: false })
  if (normalized.length > MAX_PAYLOAD_PREVIEW_LENGTH) {
    return `${normalized.slice(0, MAX_PAYLOAD_PREVIEW_LENGTH)}…`
  }
  return normalized
}

function shouldShowExpand(payload) {
  const normalized = normalizePayload(payload, { pretty: false })
  return normalized.length > MAX_PAYLOAD_PREVIEW_LENGTH
}

function openPayloadModal(payload) {
  payloadModal.value.content = normalizePayload(payload, { pretty: true })
  payloadModal.value.open = true
}

async function onQueueTest() {
  try {
    await jobsStore.testQueue()
    await applyFilters()
  } catch (error) {
    console.error(error)
  }
}

async function applyFilters() {
  jobsStore.setPage(1)
  await jobsStore.listar()
}

async function onClearFilters() {
  jobsStore.resetFilters()
  await jobsStore.listar()
}

async function onPaginate({ page, pageSize }) {
  if (pageSize !== filters.value.per_page) {
    jobsStore.setPerPage(pageSize)
  }
  if (page !== pagination.value.current_page) {
    jobsStore.setPage(page)
  }
  await jobsStore.listar()
}

onMounted(() => {
  jobsStore.listar()
})
</script>
