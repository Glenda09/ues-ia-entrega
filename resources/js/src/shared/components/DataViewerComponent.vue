<template>
  <div>
    <!-- Tabla -->
    <div class="hidden md:block">
      <Card class="bg-surface-card! rounded-md!">
        <template #content>
          <DataTable :value="items" :scrollable="scrollable" :tableStyle="mergedTableStyle">
            <template v-for="col in columns" :key="col.field">
              <Column
                :field="col.field"
                :frozen="col.frozen ? true : false"
                :alignFrozen="col.alignFrozen || undefined"
                :header="col.header"
                :style="col.style || {}"
              >
                <template #body="{ data }">
                  <!-- Slot específico por campo -->
                  <slot :name="`cell-${col.field}`" :row="data" :value="getValue(data, col.field)">
                    <!-- Slot genérico para celdas -->
                    <slot name="cell" :row="data" :column="col" :value="getValue(data, col.field)">
                      <span class="block" :title="getValue(data, col.field)">{{
                        getValue(data, col.field)
                      }}</span>
                    </slot>
                  </slot>
                </template>
              </Column>
            </template>

            <!-- Columna de acciones -->
            <Column
              v-if="hasRowActions"
              class="text-center"
              :frozen="frozenActions"
              alignFrozen="right"
              :style="{ width: actionsWidth }"
            >
              <template #body="slotProps">
                <div class="flex justify-center">
                  <template v-if="hasActions(slotProps.data)">
                    <Button
                      variant="text"
                      type="button"
                      aria-haspopup="true"
                      :aria-controls="`row-actions-${slotProps.index}`"
                      @click="(event) => toggleTableRowMenu(event, slotProps.index)"
                      title="Acciones"
                    >
                      <Icon name="more_vert" />
                    </Button>

                    <Menu
                      :ref="(el) => setTableRowMenuRef(el, slotProps.index)"
                      :id="`row-actions-${slotProps.index}`"
                      :model="resolveRowActions(slotProps.data)"
                      :popup="true"
                      class="mt-2 border border-secondary-100 shadow-md"
                    >
                      <template #item="{ item, props }">
                        <a v-ripple class="flex items-center" v-bind="props.action">
                          <Icon v-if="item.icon" :name="item.icon" class="mr-2" />
                          <span>{{ item.label }}</span>
                        </a>
                      </template>
                    </Menu>
                  </template>
                </div>
              </template>
            </Column>
          </DataTable>
        </template>
      </Card>
    </div>

    <!-- Cards -->
    <div class="md:hidden flex flex-col gap-4">
      <Card v-for="(row, idx) in items" :key="rowKeyValue(row, idx)" class="bg-surface-card! rounded-md!">
        <template #title>
          <div class="flex items-start justify-between">
            <div>
              <slot name="card-title" :row="row">
                <!-- Por defecto: primer encabezado -->
                <span class="font-semibold">{{ cardTitle }}</span>
              </slot>
            </div>
            <div v-if="hasRowActions && hasActions(row)">
              <Button
                variant="text"
                type="button"
                aria-haspopup="true"
                :aria-controls="`card-row-actions-${idx}`"
                @click="(event) => toggleCardRowMenu(event, idx)"
                title="Acciones"
              >
                <Icon name="more_vert" />
              </Button>
              <Menu
                :ref="(el) => setCardRowMenuRef(el, idx)"
                :id="`card-row-actions-${idx}`"
                :model="resolveRowActions(row)"
                :popup="true"
                class="mt-2 border border-secondary-100 shadow-md"
              >
                <template #item="{ item, props }">
                  <a v-ripple class="flex items-center" v-bind="props.action">
                    <Icon v-if="item.icon" :name="item.icon" class="mr-2" />
                    <span>{{ item.label }}</span>
                  </a>
                </template>
              </Menu>
            </div>
          </div>
        </template>

        <template #content>
          <dl class="divide-y divide-secondary-100">
            <div v-for="col in columns" :key="col.field" class="flex py-2 text-sm">
              <dt class="w-2/5 text-secondary pr-3">{{ col.header }}</dt>
              <dd class="w-3/5 text-text">
                <slot :name="`cell-${col.field}`" :row="row" :value="getValue(row, col.field)">
                  <slot name="cell" :row="row" :column="col" :value="getValue(row, col.field)">
                    <span class="block" :title="getValue(row, col.field)">{{
                      getValue(row, col.field)
                    }}</span>
                  </slot>
                </slot>
              </dd>
            </div>
          </dl>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Card, DataTable, Column, Button, Menu } from 'primevue'
import Icon from '@/shared/components/Icon.vue'

const props = defineProps({
  // Registros a mostrar
  items: {
    type: Array,
    required: true
  },
  // Permite a la tabla tener scroll
  scrollable: {
    type: Boolean,
    default: true
  },
  // Definición de columnas: [{ field, header, style? }]
  columns: {
    type: Array,
    required: true
  },
  // Función para acciones por fila: (row) => PrimeVue MenuModel
  rowActions: {
    type: Function,
    default: null
  },
  // Clave única por fila (string) o función (row) => any
  rowKey: {
    type: [String, Function],
    default: 'id'
  },
  // Ancho de la columna de acciones en tabla
  actionsWidth: {
    type: String,
    default: '50px'
  },
  // Estilos para la tabla
  tableStyle: {
    type: Object,
    default: () => ({ tableLayout: 'fixed', width: '100%' })
  },
  // Titulo de cada tarjeta
  cardTitle: {
    type: String,
    default: ''
  },
  // Permite descongelar la columna de acciones
  frozenActions: {
    type: Boolean,
    default: true
  }
})

const mergedTableStyle = computed(() => ({
  tableLayout: 'fixed',
  width: '100%',
  ...(props.tableStyle || {})
}))
const hasRowActions = computed(() => typeof props.rowActions === 'function')

function getValue(row, field) {
  // Soporte para paths simples (sin notación profunda)
  return row?.[field] ?? ''
}

function rowKeyValue(row, idx) {
  if (typeof props.rowKey === 'function') return props.rowKey(row)
  return row?.[props.rowKey] ?? idx
}

// Refs y control para menús por fila (tabla y tarjetas separados)
const tableRowMenus = ref([])
const cardRowMenus = ref([])
const setTableRowMenuRef = (el, index) => {
  if (el) tableRowMenus.value[index] = el
}
const setCardRowMenuRef = (el, index) => {
  if (el) cardRowMenus.value[index] = el
}
const toggleTableRowMenu = (event, index) => {
  tableRowMenus.value[index]?.toggle(event)
}
const toggleCardRowMenu = (event, index) => {
  cardRowMenus.value[index]?.toggle(event)
}
function resolveRowActions(row) {
  if (!hasRowActions.value) return []
  const res = props.rowActions(row)
  return Array.isArray(res) ? res : []
}
function hasActions(row) {
  return resolveRowActions(row).length > 0
}
</script>
