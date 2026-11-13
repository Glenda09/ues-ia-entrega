<template>
  <div>
    <div class="flex flex-col lg:flex-row gap-8 lg:gap-4">
      <div class="w-full lg:w-4/6">
        <h2 class="text-2xl font-bold mb-2">Logs del sistema</h2>
        <p class="text-secondary">Consulta los eventos registrados por el sistema</p>
      </div>
      <div class="w-full lg:w-2/6">
        <div class="flex justify-end">
          <Button
            class="mb-4 w-full lg:w-1/2"
            outlined
            @click="showFilters = showFilters === '1' ? '0' : '1'"
            :aria-expanded="showFilters.toString()"
            aria-controls="logs-filters"
          >
            <Icon name="filter_list" class="mr-2" />
            Filtros
          </Button>
        </div>
      </div>
    </div>

    <Accordion v-model:value="showFilters" class="mt-6" id="logs-filters">
      <AccordionPanel value="1" class="border-0!">
        <AccordionContent pt:content="p-0!">
          <div class="flex flex-wrap gap-4 mb-4">
            <div class="w-full lg:w-1/12">
              <Select
                v-model="filters.level"
                :options="levelOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Nivel"
                showClear
                fluid
              />
            </div>
            <div class="w-full lg:w-2/12">
              <InputTextWithIcon
                v-model="filters.context"
                placeholder="Contexto"
                :left-icon="{ name: 'search', color: 'text-secondary' }"
                input-class="pr-3! py-4!"
              />
            </div>
            <div class="w-full lg:w-2/12">
              <InputTextWithIcon
                v-model="filters.channel"
                placeholder="Canal"
                :left-icon="{ name: 'hub', color: 'text-secondary' }"
                input-class="pr-3! py-4!"
              />
            </div>
            <div class="w-full lg:w-2/12">
              <DatePicker
                v-model="filters.datetime"
                showIcon
                iconDisplay="input"
                placeholder="Fecha"
                dateFormat="yy-mm-dd"
                fluid
              />
            </div>
            <div class="w-full lg:w-1/12">
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

    <DataViewerComponent class="mt-6" :items="logs" :columns="columns" rowKey="datetime">
      <template #cell-level_name="{ row }">
        <span
          class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium uppercase"
          :class="getLevelBadgeClasses(row.level_name || row.level)"
        >
          <span>{{ row.level_name || 'No definido' }}</span>
          <span v-if="row.level" class="text-xs font-normal text-secondary">({{ row.level }})</span>
        </span>
      </template>
      <template #cell-datetime="{ row }">
        <span>{{ formatDateTime(row.datetime) }}</span>
      </template>
      <template #cell-context="{ row }">
        <div class="flex flex-col gap-2">
          <span class="font-mono text-xs text-secondary break-words">
            {{ getContextPreview(row).preview }}
          </span>
          <Button
            v-if="getContextPreview(row).truncated"
            class="self-start px-0"
            text
            severity="secondary"
            @click="openContextModal(row)"
            :aria-label="`Ver contexto completo de ${row.channel || 'registro'}`"
          >
            <Icon name="open_in_full" class="mr-1" />
            Ver completo
          </Button>
        </div>
      </template>
      <template #cell-message="{ row }">
        <div class="flex flex-col gap-1">
          <span class="font-medium text-text">{{ row.message || 'Sin mensaje' }}</span>
          <span
            v-if="row.extra && Object.keys(row.extra || {}).length"
            class="text-xs text-secondary"
          >
            Extra: {{ formatContext(row.extra) }}
          </span>
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
      v-model:visible="isContextModalOpen"
      modal
      dismissableMask
      pt:root="w-full lg:max-w-[50vw]!"
      @hide="onHideContextModal"
    >
      <template #header>
        <div class="flex items-center gap-2">
          <Icon name="article" class="text-xl text-brand" />
          <span class="font-semibold text-lg text-text">Contexto completo</span>
        </div>
      </template>
      <pre
        class="bg-secondary-100 border border-secondary-100 rounded-md p-3 text-xs text-text whitespace-pre-wrap break-words max-h-80 overflow-auto"
      >
        {{ contextModalContent }}
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
  Select,
  Accordion,
  AccordionPanel,
  AccordionContent,
  Dialog
} from 'primevue'
import { useLogsStore } from '@/modules/root/stores/logs'
import DataViewerComponent from '@/shared/components/DataViewerComponent.vue'
import TablePagination from '@/shared/components/TablePagination.vue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'

const logsStore = useLogsStore()
const { items: logs, filters, pagination } = storeToRefs(logsStore)

const showFilters = ref('0')

const levelOptions = [
  { label: '200 (INFO)', value: 200 },
  { label: '300 (WARNING)', value: 300 },
  { label: '400 (ERROR)', value: 400 },
  { label: '500 (CRITICAL)', value: 500 }
]

const orderOptions = [
  { label: 'Descendente', value: 'desc' },
  { label: 'Ascendente', value: 'asc' }
]

const CONTEXT_PREVIEW_LIMIT = 200

const columns = [
  { field: 'message', header: 'Mensaje', style: { width: '250px', minWidth: '250px' } },
  { field: 'level_name', header: 'Nivel', style: { width: '170px', minWidth: '170px' } },
  { field: 'channel', header: 'Canal', style: { width: '170px', minWidth: '170px' } },
  { field: 'datetime', header: 'Fecha', style: { width: '240px', minWidth: '240px' } },
  { field: 'context', header: 'Contexto', style: { width: '600px', minWidth: '600px' } }
]

const contextPreviewCache = new WeakMap()
const isContextModalOpen = ref(false)
const contextModalContent = ref('No definido')

function getLevelBadgeClasses(level) {
  const normalized = typeof level === 'string' ? level.toUpperCase() : String(level ?? '')
  const map = {
    INFO: 'border-success/30 bg-success/10 text-success',
    200: 'border-success/30 bg-success/10 text-success',
    WARNING: 'border-warning/30 bg-warning/10 text-warning',
    300: 'border-warning/30 bg-warning/10 text-warning',
    ERROR: 'border-danger/30 bg-danger/10 text-danger',
    400: 'border-danger/30 bg-danger/10 text-danger',
    CRITICAL: 'border-danger/30 bg-danger/10 text-danger',
    500: 'border-danger/30 bg-danger/10 text-danger'
  }
  return map[normalized] || 'border-secondary-100 bg-secondary-100 text-secondary'
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

function formatContext(context, { pretty = false } = {}) {
  if (!context) return 'No definido'
  try {
    if (typeof context === 'string') {
      const trimmed = context.trim()
      if (!trimmed.length) return 'No definido'
      if (pretty && (trimmed.startsWith('{') || trimmed.startsWith('['))) {
        const parsed = JSON.parse(trimmed)
        return JSON.stringify(parsed, null, 2)
      }
      return trimmed
    }
    if (typeof context === 'object') {
      if (Array.isArray(context)) {
        if (!context.length) return '[]'
        return pretty ? JSON.stringify(context, null, 2) : JSON.stringify(context)
      }
      if (!Object.keys(context).length) return '{}'
      return pretty ? JSON.stringify(context, null, 2) : JSON.stringify(context)
    }
    return String(context)
  } catch (e) {
    console.error(e)
    return String(context)
  }
}

function getContextPreview(row) {
  if (!row) {
    return { preview: 'No definido', truncated: false }
  }
  if (!contextPreviewCache.has(row)) {
    const full = formatContext(row.context)
    const truncated = full.length > CONTEXT_PREVIEW_LIMIT
    const preview = truncated ? `${full.slice(0, CONTEXT_PREVIEW_LIMIT)}…` : full
    contextPreviewCache.set(row, { preview, truncated })
  }
  return contextPreviewCache.get(row)
}

function openContextModal(row) {
  contextModalContent.value = formatContext(row.context, { pretty: true })
  isContextModalOpen.value = true
}

function onHideContextModal() {
  contextModalContent.value = 'No definido'
}

async function applyFilters() {
  logsStore.setPage(1)
  await logsStore.listar()
}

async function onClearFilters() {
  logsStore.resetFilters()
  await logsStore.listar()
}

async function onPaginate({ page, pageSize }) {
  if (pageSize !== filters.value.per_page) {
    logsStore.setPerPage(pageSize)
  }
  if (page !== pagination.value.current_page) {
    logsStore.setPage(page)
  }
  await logsStore.listar()
}

onMounted(() => {
  logsStore.listar()
})
</script>
