<template>
  <div>
    <!-- Título y botones -->
    <div class="flex flex-col lg:flex-row gap-8 lg:gap-4">
      <div class="w-full lg:w-4/6 content-center">
        <h2 class="text-2xl font-bold mb-2">Usuarios</h2>
        <p class="text-secondary">Gestión de usuarios del sistema</p>
      </div>
      <div class="w-full lg:w-2/6 content-center">
        <div class="flex justify-end gap-4">
          <div :class="canCreateUser ? 'w-1/2' : 'w-full'">
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
          <div v-if="canCreateUser" class="w-1/2">
            <Button class="btn btn-primary w-full" title="Agregar usuario" @click="onAdd">
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
            <!-- Usuario o email -->
            <div class="w-full lg:w-3/12">
              <InputTextWithIcon
                v-model="filters.query"
                placeholder="Usuario o email"
                :left-icon="{ name: 'search', color: 'text-secondary' }"
                input-class="pr-3! py-4!"
                :pt="{ input: { autocomplete: 'username' } }"
              />
            </div>
            <!-- Filtro de rango de fecha de creación -->
            <div class="w-full lg:w-4/12">
              <DatePicker
                v-model="filters.createdAtRange"
                showIcon
                fluid
                iconDisplay="input"
                selectionMode="range"
                placeholder="Fecha de creación"
              />
            </div>
            <div class="flex w-full lg:w-4/12 gap-4">
              <div class="w-1/2">
                <Button
                  class="btn btn-primary w-full h-full"
                  title="Aplicar filtros"
                  @click="applyFilters"
                >
                  <Icon name="filter_list" /> Filtrar
                </Button>
              </div>
              <div class="w-1/2">
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
          </div>
        </AccordionContent>
      </AccordionPanel>
    </Accordion>

    <!-- Datos -->
    <DataViewerComponent :items="users" :columns="columns" :rowActions="getRowActions">
      <!-- Estado con etiqueta de color e ícono -->
      <template #cell-id_estado="{ row }">
        <span
          v-if="row.estado"
          class="inline-flex items-center gap-1 px-2 py-1 rounded border text-xs font-medium whitespace-nowrap"
          :style="{ color: row.estado.color, borderColor: row.estado.color }"
        >
          <Icon :name="row.estado.icono" fontSize="18" />
          <span>{{ row.estado.nombre_estado }}</span>
        </span>
        <span v-else class="text-secondary">{{ row.id_estado || 'No definido' }}</span>
      </template>

      <!-- Fecha de creación formateada -->
      <template #cell-created_at="{ row }">
        <span>{{ formatDateTime(row.created_at) }}</span>
      </template>
    </DataViewerComponent>

    <!-- Modal crear/editar usuario -->
    <UserComponent
      v-model:open="modal.open"
      :user="modal.user"
      :profiles="perfiles"
      @saved="onSavedUser"
    />

    <!-- Confirmación resetear contraseña -->
    <ConfirmModal
      v-model:open="confirm.open"
      :message="confirm.message"
      yes-text="Si"
      no-text="No"
      :busy="confirm.busy"
      :on-yes="confirmYes"
      :on-no="confirmNo"
    />

    <!-- Paginación -->
    <TablePagination
      :totalItems="pagination.total"
      v-model:page="pagination.current_page"
      v-model:pageSize="filters.per_page"
      @change="onPaginate"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { Button, DatePicker, Accordion, AccordionPanel, AccordionContent } from 'primevue'
import UserComponent from '@/modules/authorization/components/UserComponent.vue'
import { useUsuariosStore } from '@/modules/authorization/stores/usuarios'
import TablePagination from '@/shared/components/TablePagination.vue'
import perfilesService from '@/modules/authorization/services/perfiles'
import ConfirmModal from '@/shared/components/ConfirmModal.vue'
import DataViewerComponent from '@/shared/components/DataViewerComponent.vue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'
import { useValidatePermission } from '@/shared/composables/useValidatePermission'
import { computed } from 'vue'

const uStore = useUsuariosStore()
const { items: users, filters, pagination } = storeToRefs(uStore)
const perfiles = ref([])
const showFilters = ref('0')

// Permisos
const { can } = useValidatePermission()
const canCreateUser = computed(() => can('auth_usuario_guardar'))
const canEditUser = computed(() => can('auth_usuario_actualizar'))
const canResetPassword = computed(() => can('auth_usuario_restablecer_password'))
const canToggleUserState = computed(() => can('auth_usuario_alternar_estado'))

async function loadPerfiles() {
  try {
    const res = await perfilesService.listar({ solo_activos: true })
    let perfilesData = []
    if (Array.isArray(res?.data)) {
      perfilesData = res.data
    } else if (Array.isArray(res)) {
      perfilesData = res
    }
    perfiles.value = perfilesData
  } catch (e) {
    console.error(e)
    perfiles.value = []
  }
}

async function applyFilters() {
  uStore.setPage(1)
  await uStore.listar()
}

async function onClearFilters() {
  filters.value.query = ''
  filters.value.createdAtRange = null
  uStore.setPage(1)
  await uStore.listar()
}

// Columnas dinámicas para DataViewerComponent
const columns = [
  { field: 'username', header: 'Usuario', style: { width: '200px' } },
  { field: 'email', header: 'Email', style: { width: '200px' } },
  { field: 'id_estado', header: 'Estado', style: { width: '150px' } },
  { field: 'created_at', header: 'Fecha de creación', style: { width: '200px' } }
]

// Acciones por fila
const modal = reactive({ open: false, user: null })

function onAdd() {
  modal.user = null
  loadPerfiles()
  modal.open = true
}

function onEdit(row) {
  modal.user = row
  loadPerfiles()
  modal.open = true
}

async function onResetPassword(row) {
  // Abrir confirmación
  confirm.row = row
  confirm.message = `¿Resetear la contraseña del usuario "${row.username}"?`
  confirm.onConfirm = async () => {
    await uStore.restablecerPassword(row.id_usuario)
  }
  confirm.open = true
}

async function onToggleEstado(row) {
  const isActivo = row.id_estado === 'ACTIVO'
  confirm.row = row
  confirm.message = isActivo
    ? `¿Desactivar al usuario "${row.username}"?`
    : `¿Activar al usuario "${row.username}"?`
  confirm.onConfirm = async () => {
    await uStore.alternarEstado(row.id_usuario)
    await uStore.listar()
  }
  confirm.open = true
}

function getRowActions(row) {
  const isActivo = row.id_estado === 'ACTIVO'
  const items = []
  if (canEditUser.value) items.push({ label: 'Editar', icon: 'edit', command: () => onEdit(row) })
  if (canEditUser.value && (canResetPassword.value || canToggleUserState.value)) items.push({ separator: true })
  if (canResetPassword.value)
    items.push({ label: 'Resetear contraseña', icon: 'lock_reset', command: () => onResetPassword(row) })
  if (canResetPassword.value && canToggleUserState.value) items.push({ separator: true })
  if (canToggleUserState.value)
    items.push({
      label: isActivo ? 'Desactivar' : 'Activar',
      icon: isActivo ? 'block' : 'check_circle',
      command: () => onToggleEstado(row)
    })
  // Compactar separadores al inicio/fin o duplicados
  return items.filter((it, idx, arr) => {
    if (!it.separator) return true
    const prev = arr[idx - 1]
    const next = arr[idx + 1]
    return prev && !prev.separator && next && !next.separator
  })
}

async function onSavedUser() {
  modal.open = false
  modal.user = null
  await uStore.listar()
}

onMounted(() => {
  uStore.listar()
})

async function onPaginate({ page, pageSize }) {
  if (pageSize !== filters.value.per_page) {
    uStore.setPerPage(pageSize)
  }
  if (page !== pagination.value.current_page) {
    uStore.setPage(page)
  }
  await uStore.listar()
}

// Estado del modal de confirmación
const confirm = reactive({
  open: false,
  message: '¿Estás seguro de realizar la acción?',
  row: null,
  busy: false,
  onConfirm: null
})

async function confirmYes() {
  if (confirm.busy || typeof confirm.onConfirm !== 'function') return
  try {
    confirm.busy = true
    await confirm.onConfirm()
  } finally {
    confirm.busy = false
    confirm.row = null
    confirm.onConfirm = null
  }
}

function confirmNo() {
  // No-op adicional; el modal se cierra desde el componente
  confirm.row = null
  confirm.onConfirm = null
}

// Formato: dia-mes-año hora:minutos AM/PM
function formatDateTime(value) {
  if (!value) return ''
  const d = new Date(value)
  if (isNaN(d)) return String(value)
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = d.getFullYear()
  let hours = d.getHours()
  const minutes = String(d.getMinutes()).padStart(2, '0')
  const ampm = hours >= 12 ? 'PM' : 'AM'
  hours = hours % 12
  if (hours === 0) hours = 12
  const hh = String(hours).padStart(2, '0')
  return `${dd}-${mm}-${yyyy} ${hh}:${minutes} ${ampm}`
}
</script>
