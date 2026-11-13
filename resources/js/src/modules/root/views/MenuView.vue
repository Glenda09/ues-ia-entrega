<template>
  <div class="flex flex-col gap-4">
    <section class="flex flex-col lg:flex-row gap-8 lg:gap-4">
      <div class="w-full lg:w-5/6">
        <h2 class="text-2xl font-bold mb-2">Opciones de menú</h2>
        <p class="text-secondary">Administra el árbol de navegación y sus jerarquías.</p>
      </div>
      <div class="w-full lg:w-1/6 flex items-end">
        <div class="w-full">
          <Button
            class="w-full"
            outlined
            @click="toggleFilters"
            :aria-expanded="showFilters === '1'"
            aria-controls="menu-filters-accordion"
          >
            <Icon name="filter_list" class="mr-2" />
            Filtros
          </Button>
        </div>
      </div>
    </section>

    <Accordion id="menu-filters-accordion" v-model:value="showFilters" class="mt-8">
      <AccordionPanel value="1" class="border-0!">
        <AccordionContent pt:content="p-0!">
          <div class="flex flex-wrap gap-4 mb-4">
            <div class="w-full lg:w-3/12">
              <Dropdown
                inputId="module-filter"
                v-model="moduleFilter"
                :options="moduleOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Módulo"
                showClear
                class="w-full"
                :disabled="loadingModules"
              />
            </div>
            <div class="w-full lg:w-3/12">
              <InputTextWithIcon
                id="search-filter"
                v-model="searchFilter"
                placeholder="Ruta"
                :left-icon="{ name: 'search', color: 'text-secondary' }"
                input-class="pr-3! py-4!"
                @keyup.enter="applyFilters"
              />
            </div>
            <div class="w-full lg:w-1/12">
              <Button
                class="btn btn-primary w-full h-full"
                :loading="menuLoading"
                :disabled="menuLoading"
                @click="applyFilters"
              >
                <Icon name="filter_list" /> Filtrar
              </Button>
            </div>
            <div class="w-full lg:w-1/12">
              <Button
                class="w-full h-full"
                variant="outlined"
                :disabled="menuLoading"
                @click="onClearFilters"
              >
                <Icon name="mop" />
              </Button>
            </div>
          </div>
        </AccordionContent>
      </AccordionPanel>
    </Accordion>

    <section>
      <Card class="bg-surface-card! rounded-md! shadow-sm! border border-secondary-100!">
        <template #content>
          <TreeTable
            :value="treeNodes"
            dataKey="key"
            :loading="menuLoading"
            :expandedKeys="expandedKeys"
            class="w-full"
            :pt="treeTablePt"
          >
            <Column header="Ícono" :style="{ width: '150px' }">
              <template #body="{ node }">
                <Icon v-if="node.data.icono" :name="node.data.icono" />
                <span v-else class="text-secondary">-</span>
              </template>
            </Column>
            <Column header="Nombre" :expander="true" :style="{ width: '220px' }">
              <template #body="{ node }">
                <span>{{ node.data.nombre || 'No definido' }}</span>
              </template>
            </Column>
            <Column header="Ruta">
              <template #body="{ node }">
                <span v-if="!isDesplegable(node)">{{ node.data.ruta || 'No definido' }}</span>
                <span v-else>&nbsp;</span>
              </template>
            </Column>
            <Column header="Módulo">
              <template #body="{ node }">
                <span>{{ node.data.id_modulo || 'No definido' }}</span>
              </template>
            </Column>
            <Column header="Acciones" :style="{ width: '110px' }">
              <template #body="{ node }">
                <div class="flex items-center gap-2">
                  <Button
                    type="button"
                    variant="text"
                    class="!p-2"
                    :disabled="!node.data.id_opcion"
                    :aria-label="`Editar ${node.data.nombre || ''}`"
                    @click="onEdit(node)"
                  >
                    <Icon name="edit" />
                  </Button>
                </div>
              </template>
            </Column>
          </TreeTable>
        </template>
      </Card>
    </section>

    <MenuOptionModal v-model:open="modal.open" :option-id="modal.optionId" @saved="onSaved" />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import {
  TreeTable,
  Column,
  Button,
  Dropdown,
  Accordion,
  AccordionPanel,
  AccordionContent,
  Card
} from 'primevue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'
import MenuOptionModal from '@/modules/root/components/MenuOptionModal.vue'
import { useRootMenuStore } from '@/modules/root/stores/menu'

const menuStore = useRootMenuStore()
const { items, loading, modules } = storeToRefs(menuStore)

const menuLoading = computed(() => loading.value)
const loadingModules = ref(false)

const moduleFilter = ref(menuStore.filters.id_modulo || '')
const searchFilter = ref(menuStore.filters.search || '')
const showFilters = ref('0')

const modal = reactive({
  open: false,
  optionId: null
})

const moduleOptions = computed(() =>
  modules.value.map((item) => ({
    label: item.nombre || item.codigo || 'No definido',
    value: item.codigo || item.id_modulo || ''
  }))
)

const treeNodes = ref([])
const expandedKeys = ref({})

const treeTablePt = {
  root: { class: 'bg-transparent' },
  table: { class: 'w-full bg-surface-card!' },
  thead: { class: 'bg-surface-card!' },
  tbody: { class: 'bg-surface-card!' }
}

function mapChildren(option) {
  if (Array.isArray(option.childrens)) return option.childrens
  if (Array.isArray(option.hijos)) return option.hijos
  if (Array.isArray(option.children)) return option.children
  return []
}

function toTreeNodes(options = [], parentKey = 'root') {
  return options.map((option, index) => mapOptionToNode(option, parentKey, index))
}

function mapOptionToNode(option, parentKey = 'root', index = 0) {
  const children = mapChildren(option)
  const key = option.id_opcion || `${parentKey}-${index}`

  return {
    key,
    data: {
      ...option,
      parentKey
    },
    children: toTreeNodes(children, key),
    leaf: children.length === 0
  }
}

function isDesplegable(node) {
  if (!node) return false
  if (typeof node.data?.desplegable === 'boolean') return node.data.desplegable
  return Array.isArray(node.children) && node.children.length > 0
}

function formatPath(node) {
  if (isDesplegable(node)) return ''
  return node.data?.ruta || ''
}

function expandAll(nodes = []) {
  const keys = {}
  const traverse = (list) => {
    list.forEach((node) => {
      if (Array.isArray(node.children) && node.children.length > 0) {
        keys[node.key] = true
        traverse(node.children)
      }
    })
  }

  traverse(nodes)
  expandedKeys.value = keys
}

watch(
  () => items.value,
  (list) => {
    const safeList = Array.isArray(list) ? list : []
    const nodes = toTreeNodes(safeList)
    treeNodes.value = nodes
    expandAll(nodes)
  },
  { immediate: true }
)

function toggleFilters() {
  showFilters.value = showFilters.value === '1' ? '0' : '1'
}

async function applyFilters() {
  menuStore.setModule(moduleFilter.value)
  menuStore.setSearch(searchFilter.value)
  await menuStore.listar()
}

async function onClearFilters() {
  menuStore.resetFilters()
  moduleFilter.value = ''
  searchFilter.value = ''
  await menuStore.listar()
}

function onEdit(node) {
  if (!node?.data?.id_opcion) return
  modal.optionId = node.data.id_opcion
  modal.open = true
}

async function onSaved() {
  modal.open = false
  modal.optionId = null
  await menuStore.listar()
}

async function loadInitialData() {
  loadingModules.value = true
  try {
    await Promise.all([menuStore.fetchModules(), menuStore.listar()])
  } finally {
    loadingModules.value = false
  }
}

onMounted(() => {
  loadInitialData()
})
</script>
