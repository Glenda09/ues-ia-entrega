<template>
  <div>
    <!-- Título y botones -->
    <div class="flex flex-col lg:flex-row gap-8 lg:gap-4">
      <div class="w-full lg:w-4/6 content-center">
        <h2 class="text-2xl font-bold mb-2">Perfiles</h2>
        <p class="text-secondary">Gestión de perfiles del sistema</p>
      </div>
      <div class="w-full lg:w-2/6 content-center">
        <div class="flex justify-end gap-4">
          <div :class="canCreateProfile ? 'w-1/2' : 'w-full'">
            <Button
              class="mb-4 w-full"
              outlined
              @click="showFilters = showFilters == '1' ? '0' : '1'"
              :aria-expanded="showFilters.toString()"
              :aria-controls="'filter-accordion'"
            >
              <Icon name="filter_list" class="mr-2" />
              Filtros
            </Button>
          </div>
          <div v-if="canCreateProfile" class="w-1/2">
            <Button class="btn btn-primary w-full" title="Agregar perfil" @click="onAdd">
              <Icon name="add" /> Crear
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Filtros -->
    <Accordion v-model:value="showFilters" class="mt-8">
      <AccordionPanel value="1" class="border-0!">
        <AccordionContent pt:content="p-0!">
          <div class="flex flex-wrap gap-4 mb-4">
            <!-- Búsqueda -->
            <div class="w-full lg:w-4/12">
              <InputTextWithIcon
                v-model="filters.search"
                placeholder="Buscar por código o nombre"
                :left-icon="{ name: 'search', color: 'text-secondary' }"
                input-class="pr-3! py-4!"
              />
            </div>
            <div class="w-full lg:w-1/6">
              <Button
                class="btn btn-primary w-full h-full"
                title="Aplicar filtros"
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
                @click="onClearFilters"
              >
                <Icon name="mop" />
              </Button>
            </div>
          </div>
        </AccordionContent>
      </AccordionPanel>
    </Accordion>

    <!-- Datos -->
    <DataViewerComponent :items="items" :columns="columns" :rowActions="getRowActions">
      <!-- Estado Activo/Inactivo -->
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
    </DataViewerComponent>

    <!-- Modal crear/editar perfil -->
    <ProfileComponent
      v-model:open="modal.open"
      :profile="modal.profile"
      :acciones="acciones"
      @saved="onSavedProfile"
    />

    <!-- Modal ver acciones asociadas -->
    <ProfileActionsModal
      v-model:open="actionsModal.open"
      :profile="actionsModal.profile"
      :acciones="acciones"
    />

    <!-- Confirmación eliminar perfil -->
    <ConfirmModal
      v-model:open="confirm.open"
      :message="confirm.message"
      yes-text="Si"
      no-text="No"
      :busy="confirm.busy"
      :on-yes="confirmYes"
      :on-no="confirmNo"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { Button, Accordion, AccordionPanel, AccordionContent } from 'primevue'
import Icon from '@/shared/components/Icon.vue'
import DataViewerComponent from '@/shared/components/DataViewerComponent.vue'
import ConfirmModal from '@/shared/components/ConfirmModal.vue'
import ProfileComponent from '@/modules/authorization/components/ProfileComponent.vue'
import ProfileActionsModal from '@/modules/authorization/components/ProfileActionsModal.vue'
import { useValidatePermission } from '@/shared/composables/useValidatePermission'
import { computed } from 'vue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'
import { usePerfilesStore } from '@/modules/authorization/stores/perfiles'
import perfilesService from '@/modules/authorization/services/perfiles'
import accionesService from '@/modules/authorization/services/acciones'
import { toast } from 'vue3-toastify'

const pStore = usePerfilesStore()
const { items, filters } = storeToRefs(pStore)
const showFilters = ref('0')
const acciones = ref([])

// Permisos
const { can } = useValidatePermission()
const canCreateProfile = computed(() => can('seguridad_perfil_guardar'))
const canEditProfile = computed(() => can('seguridad_perfil_actualizar'))
const canToggleProfileState = computed(() => can('seguridad_perfil_alternar_estado'))
const canShowProfile = computed(() => can('seguridad_perfil_mostrar'))

async function loadAcciones() {
  try {
    const res = await accionesService.listar()
    acciones.value = Array.isArray(res) ? res : []
  } catch (e) {
    console.error(e)
    acciones.value = []
  }
}

async function applyFilters() {
  await pStore.listar()
}

async function onClearFilters() {
  filters.value.search = ''
  await pStore.listar()
}

// Columnas
const columns = [
  { field: 'codigo', header: 'Código', style: { width: '200px' } },
  { field: 'nombre', header: 'Nombre', style: { width: '220px' } },
  { field: 'activo', header: 'Estado', style: { width: '140px' } },
  { field: 'descripcion', header: 'Descripción', style: { width: '200px' } }
]

// Acciones por fila
const modal = reactive({ open: false, profile: null })
const actionsModal = reactive({ open: false, profile: null })

function onAdd() {
  modal.profile = null
  loadAcciones()
  modal.open = true
}

async function onEdit(row) {
  try {
    // cargar detalles del perfil (incluye acciones)
    const det = await perfilesService.mostrar(row.codigo)
    const profile = det?.data ?? det ?? row
    modal.profile = profile
  } catch (e) {
    console.error(e)
    modal.profile = row
  } finally {
    loadAcciones()
    modal.open = true
  }
}

function getRowActions(row) {
  const activo = !!row?.activo
  const items = []
  if (canShowProfile.value) items.push({ label: 'Ver acciones', icon: 'visibility', command: () => onViewActions(row) })
  if (canShowProfile.value && canEditProfile.value) items.push({ separator: true })
  if (canEditProfile.value) items.push({ label: 'Editar', icon: 'edit', command: () => onEdit(row) })
  if ((canShowProfile.value || canEditProfile.value) && canToggleProfileState.value) items.push({ separator: true })
  if (canToggleProfileState.value)
    items.push({
      label: activo ? 'Desactivar' : 'Activar',
      icon: activo ? 'block' : 'check_circle',
      command: () => onToggleEstado(row)
    })
  return items.filter((it, idx, arr) => {
    if (!it.separator) return true
    const prev = arr[idx - 1]
    const next = arr[idx + 1]
    return prev && !prev.separator && next && !next.separator
  })
}

const confirm = reactive({ open: false, message: '', row: null, busy: false, type: '' })

function onToggleEstado(row) {
  confirm.row = row
  const activo = !!row?.activo
  confirm.type = 'estado'
  confirm.message = activo
    ? `¿Desactivar el perfil "${row.nombre}"?`
    : `¿Activar el perfil "${row.nombre}"?`
  confirm.open = true
}

async function confirmYes() {
  if (!confirm.row || confirm.busy) return
  try {
    confirm.busy = true
    if (confirm.type === 'estado') {
      await pStore.alternarEstado(confirm.row.codigo)
      await pStore.listar()
    }
  } catch (e) {
    console.error(e)
    toast.error(e?.message || 'No se pudo actualizar el estado del perfil')
  } finally {
    confirm.busy = false
  }
}
function confirmNo() {
  // No-op
}

async function onSavedProfile() {
  modal.open = false
  modal.profile = null
  await pStore.listar()
}

onMounted(() => {
  pStore.listar()
})

async function onViewActions(row) {
  try {
    if (!acciones.value?.length) await loadAcciones()
    const det = await perfilesService.mostrar(row.codigo)
    const profile = det?.data ?? det ?? row
    actionsModal.profile = profile
    actionsModal.open = true
  } catch (e) {
    console.error(e)
  }
}
</script>
