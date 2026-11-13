<template>
  <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between py-3 md:py-4">
    <div
      v-if="showCurrentPageReport"
      class="text-sm text-secondary order-2 md:order-1 text-center md:text-left hidden md:block"
    >
      {{ pageReportText }}
    </div>

    <div class="order-1 md:order-2 flex justify-center md:justify-end">
      <Paginator
        :totalRecords="totalItems"
        :rows="pageSize_"
        :first="first"
        :rowsPerPageOptions="rowsPerPageOptions"
        :template="responsiveTemplate"
        :pageLinkSize="pageLinkSize"
        :currentPageReportTemplate="currentPageReportTemplate"
        @page="onPage"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Paginator } from 'primevue'

const props = defineProps({
  // Total de registros disponibles
  totalItems: {
    type: Number,
    required: true
  },
  // Página actual (1-based)
  page: {
    type: Number,
    default: 1
  },
  // Registros por página
  pageSize: {
    type: Number,
    default: 10
  },
  // Opciones del selector de registros por página
  rowsPerPageOptions: {
    type: Array,
    default: () => [10, 20, 50, 100]
  },
  // Plantilla de secciones del paginador de PrimeVue
  template: {
    type: String,
    default: 'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown'
  },
  // Texto del reporte de página actual
  currentPageReportTemplate: {
    type: String,
    default: 'Mostrando {first}–{last} de {totalRecords}'
  },
  // Mostrar/ocultar reporte de página (texto a la izquierda)
  showCurrentPageReport: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits([
  // v-model:page
  'update:page',
  // v-model:pageSize
  'update:pageSize',
  // Evento de conveniencia con payload completo
  'change'
])

// Derivados 0-based para PrimeVue
const first = computed(() =>
  Math.max(0, (Number(props.page || 1) - 1) * Number(props.pageSize || 10))
)
const pageSize_ = computed(() => Number(props.pageSize || 10))

const pageReportText = computed(() => {
  if (!props.totalItems) return 'Mostrando 0 de 0'
  const start = props.totalItems ? first.value + 1 : 0
  const end = Math.min(first.value + pageSize_.value, props.totalItems)
  return `Mostrando ${start}–${end} de ${props.totalItems}`
})

// Responsividad: adaptar plantilla y tamaño de links por breakpoint
const width = ref(typeof window !== 'undefined' ? window.innerWidth : 0)
function updateWidth() {
  if (typeof window === 'undefined') return
  width.value = window.innerWidth
}

onMounted(() => {
  updateWidth()
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', updateWidth, { passive: true })
  }
})
onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', updateWidth)
  }
})

const breakpoint = computed(() => {
  const w = width.value
  if (w >= 1280) return 'xl'
  if (w >= 1024) return 'lg'
  if (w >= 768) return 'md'
  return 'sm'
})

const responsiveTemplate = computed(() => {
  // Móviles: solo anterior, páginas, siguiente
  if (breakpoint.value === 'sm') {
    return 'PrevPageLink PageLinks NextPageLink'
  }
  // Tablets: incluye primero/último, oculta selector de filas
  if (breakpoint.value === 'md') {
    return 'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink'
  }
  // Pantallas grandes: usa plantilla completa provista (incluye RowsPerPageDropdown por defecto)
  return props.template
})

const PAGE_LINK_SIZE = { sm: 3, md: 5, lg: 7, xl: 7 }
const pageLinkSize = computed(() => PAGE_LINK_SIZE[breakpoint.value] || PAGE_LINK_SIZE.lg)

function onPage(e) {
  // PrimeVue entrega page 0-based
  const newPage = Number(e.page) + 1
  const newPageSize = Number(e.rows)
  const payload = {
    page: newPage,
    pageSize: newPageSize,
    first: Number(e.first),
    pageCount: Number(e.pageCount)
  }

  emit('update:page', newPage)
  if (newPageSize !== props.pageSize) emit('update:pageSize', newPageSize)
  emit('change', payload)
}
</script>

<style scoped>
/* Ajustes menores para alinear con el estilo general */
:deep(.p-paginator) {
  background: transparent;
  border: none;
  padding: 0;
}

/* Compacidad en móviles */
@media (max-width: 767px) {
  :deep(.p-paginator .p-paginator-pages .p-paginator-page) {
    min-width: 2rem;
    height: 2rem;
    line-height: 2rem;
    padding: 0 0.25rem;
  }
  :deep(.p-paginator .p-paginator-first),
  :deep(.p-paginator .p-paginator-last) {
    display: none;
  }
}
</style>
