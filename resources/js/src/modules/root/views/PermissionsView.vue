<template>
  <div>
    <header class="flex flex-col lg:flex-row gap-6 lg:gap-4">
      <div class="w-full lg:w-4/6">
        <h2 class="text-2xl font-bold mb-2">Permisos</h2>
        <p class="text-secondary">
          Consulta los permisos configurados en el sistema y su estado actual.
        </p>
      </div>
      <div class="w-full lg:w-2/6 flex items-end justify-end">
        <Button
          class="w-full lg:w-auto"
          outlined
          :aria-expanded="showFilters === '1'"
          aria-controls="permissions-filters"
          @click="toggleFilters"
        >
          <Icon name="filter_list" class="mr-2" />
          Filtros
        </Button>
      </div>
    </header>

    <Accordion v-model:value="showFilters" class="mt-8" id="permissions-filters">
      <AccordionPanel value="1" class="border-0!">
        <AccordionContent pt:content="p-0!">
          <div class="flex flex-wrap gap-4 mb-4">
            <div class="w-full md:w-4/12">
              <InputTextWithIcon
                v-model="filters.search"
                placeholder="Buscar por código o descripción"
                :left-icon="{ name: 'search', color: 'text-secondary' }"
                input-class="pr-3! py-4!"
                @keydown.enter="applyFilters"
              />
            </div>
            <div class="w-full md:w-3/12 flex items-center gap-3">
              <Checkbox
                v-model="filters.solo_activos"
                binary
                input-id="filter-solo-activos"
                aria-label="Mostrar solo permisos activos"
              />
              <label for="filter-solo-activos" class="text-sm text-text">Solo activos</label>
            </div>
            <div class="w-full md:w-2/12 flex gap-4">
              <Button
                class="btn btn-primary w-full"
                title="Aplicar filtros"
                :disabled="loading"
                @click="applyFilters"
              >
                <Icon name="filter_list" /> Filtrar
              </Button>
              <Button
                class="w-full"
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
        rowKey="permiso"
        card-title="Permiso"
        :tableStyle="{ tableLayout: 'fixed', width: '100%' }"
      >
        <template #cell-activo="{ value }">
          <span
            v-if="value === true"
            class="inline-flex items-center gap-1 px-2 py-1 rounded border text-xs font-medium whitespace-nowrap text-success border-success"
          >
            <Icon name="check_circle" fontSize="18" />
            <span>Activo</span>
          </span>
          <span
            v-else
            class="inline-flex items-center gap-1 px-2 py-1 rounded border text-xs font-medium whitespace-nowrap text-danger border-danger"
          >
            <Icon name="block" fontSize="18" />
            <span>Inactivo</span>
          </span>
        </template>
        <template #cell-descripcion="{ value }">
          <span class="block truncate" :title="value || 'Sin descripción'">
            {{ value || 'Sin descripción' }}
          </span>
        </template>
      </DataViewerComponent>
    </section>

    <TablePagination
      v-model:page="pagination.current_page"
      v-model:pageSize="filters.per_page"
      :totalItems="pagination.total"
      @change="onPaginate"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { Button, Accordion, AccordionPanel, AccordionContent, Checkbox } from 'primevue'
import DataViewerComponent from '@/shared/components/DataViewerComponent.vue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'
import TablePagination from '@/shared/components/TablePagination.vue'
import { useRootPermisosStore } from '@/modules/root/stores/permisos'
import { toast } from 'vue3-toastify'

const showFilters = ref('0')

const permisosStore = useRootPermisosStore()
const { items, filters, pagination, loading } = storeToRefs(permisosStore)

const columns = [
  { field: 'permiso', header: 'Código', style: { width: '35%' } },
  { field: 'descripcion', header: 'Descripción' },
  { field: 'activo', header: 'Estado', style: { width: '140px' } }
]

function toggleFilters() {
  showFilters.value = showFilters.value === '1' ? '0' : '1'
}

async function refreshList() {
  try {
    await permisosStore.listar()
  } catch (error) {
    console.error(error)
    toast.error(error?.message || 'No fue posible obtener los permisos.')
  }
}

function applyFilters() {
  permisosStore.setPage(1)
  refreshList()
}

function clearFilters() {
  permisosStore.resetFilters()
  refreshList()
}

function onPaginate({ page, pageSize }) {
  if (pageSize !== filters.value.per_page) {
    permisosStore.setPerPage(pageSize)
  }
  if (page !== pagination.value.current_page) {
    permisosStore.setPage(page)
  }
  refreshList()
}

onMounted(() => {
  refreshList()
})
</script>
