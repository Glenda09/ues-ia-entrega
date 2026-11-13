<template>
  <div>
    <!-- Título y botones -->
    <div class="flex flex-col lg:flex-row gap-8 lg:gap-4">
      <div class="w-full lg:w-4/6 content-center">
        <h2 class="text-2xl font-bold mb-2">Acciones</h2>
        <p class="text-secondary">Gestión de grupos de acciones y sus acciones</p>
      </div>
      <div class="w-full lg:w-2/6 content-center">
        <div class="flex justify-end gap-4">
          <div :class="canCreateGroup ? 'w-1/2' : 'w-full'">
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
          <div v-if="canCreateGroup" class="w-1/2">
            <Button
              class="btn btn-primary w-full"
              title="Agregar grupo de acción"
              @click="onAddGroup"
            >
              <Icon name="add" /> Grupo
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
            <!-- Búsqueda de grupos -->
            <div class="w-full lg:w-4/12">
              <InputTextWithIcon
                v-model="filters.search"
                placeholder="Buscar por id, nombre o descripción"
                :left-icon="{ name: 'search', color: 'text-secondary' }"
                input-class="pr-3! py-4!"
              />
            </div>
            <div class="flex w-full lg:w-3/12 gap-4 p-0">
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

    <!-- Paneles por grupo de acción (desplegables múltiples) -->
    <Accordion v-model:value="openGroups" multiple class="mt-6">
      <AccordionPanel v-for="(grp, idx) in grupos" :key="grp.id_grupo" :value="grp.id_grupo">
        <AccordionHeader>
          <div class="flex items-start gap-4 w-full lg:items-center lg:justify-between">
            <div
              class="flex flex-1 min-w-0 flex-col gap-2 text-left lg:flex-row lg:items-center lg:gap-3"
            >
              <div class="flex flex-col items-start gap-1 lg:flex-row lg:items-center lg:gap-2">
                <span class="text-lg font-semibold">{{ grp.nombre || grp.id_grupo }}</span>
                <span
                  v-if="grp.activo === true"
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
              </div>
              <span class="block text-sm text-secondary lg:inline">{{ grp.descripcion }}</span>
            </div>
            <div class="flex items-center self-center gap-2 ml-auto shrink-0">
              <template v-if="hasGroupActions(grp)">
                <Button
                  variant="text"
                  type="button"
                  aria-haspopup="true"
                  :aria-controls="`grp-actions-${idx}`"
                  @click.stop="(event) => toggleGroupMenu(event, idx)"
                  title="Acciones del grupo"
                >
                  <Icon name="more_vert" />
                </Button>
                <Menu
                  :ref="(el) => setGroupMenuRef(el, idx)"
                  :id="`grp-actions-${idx}`"
                  :model="getGroupActions(grp)"
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
          </div>
        </AccordionHeader>

        <AccordionContent>
          <!-- Buscador interno y botón agregar acción -->
          <div class="flex flex-wrap lg:flex-nowrap gap-4 mb-4">
            <div class="w-full lg:w-4/12 xl:w-4/12">
              <InputTextWithIcon
                v-model="grp.ui.search"
                placeholder="Buscar acciones por nombre"
                :left-icon="{ name: 'search', color: 'text-secondary' }"
                input-class="pr-3! py-4!"
                @keydown.enter="() => onSearchAcciones(grp)"
              />
            </div>
            <div class="w-full lg:w-4/12 xl:w-4/12 2xl:w-2/12 flex gap-4">
              <div class="w-1/2">
                <Button
                  class="btn btn-primary w-full h-full"
                  title="Buscar"
                  @click="() => onSearchAcciones(grp)"
                >
                  <Icon name="filter_list" /> Buscar
                </Button>
              </div>
              <div class="w-1/2">
                <Button
                  class="w-full h-full"
                  variant="outlined"
                  title="Limpiar"
                  @click="() => onClearAcciones(grp)"
                >
                  <Icon name="mop" />
                </Button>
              </div>
            </div>
            <div v-if="canCreateAction" class="w-full lg:w-4/12 xl:w-2/12 ml-auto">
              <Button
                class="btn btn-primary w-full h-full"
                title="Agregar acción"
                @click="() => onAddAccion(grp)"
              >
                <Icon name="add" /> Acción
              </Button>
            </div>
          </div>

          <!-- Tabla de acciones -->
          <DataViewerComponent
            :items="grp.ui.pagedItems"
            :columns="columns"
            :rowActions="(row) => getRowActions(grp, row)"
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
          </DataViewerComponent>

          <!-- Paginación por grupo -->
          <TablePagination
            :totalItems="grp.ui.total"
            v-model:page="grp.ui.page"
            v-model:pageSize="grp.ui.per_page"
            @change="() => paginateGroup(grp)"
          />
        </AccordionContent>
      </AccordionPanel>
    </Accordion>

    <!-- Confirmación genérica -->
    <ConfirmModal
      v-model:open="confirm.open"
      :message="confirm.message"
      yes-text="Si"
      no-text="No"
      :busy="confirm.busy"
      :on-yes="confirmYes"
      :on-no="() => {}"
    />
    <ActionGroupModal
      v-model:open="groupModal.open"
      :group="groupModal.group"
      @saved="onGroupSaved"
    />
    <ActionModal
      v-model:open="actionModal.open"
      :group="actionModal.group"
      :accion="actionModal.accion"
      @saved="onActionSaved"
    />
    <ActionPermissionsModal
      v-model:open="permissionsModal.open"
      :action="permissionsModal.action"
      :permisos="permissionsModal.permisos"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue'
import { Button, Accordion, AccordionPanel, AccordionHeader, AccordionContent, Menu } from 'primevue'
import Icon from '@/shared/components/Icon.vue'
import DataViewerComponent from '@/shared/components/DataViewerComponent.vue'
import TablePagination from '@/shared/components/TablePagination.vue'
import ConfirmModal from '@/shared/components/ConfirmModal.vue'
import ActionGroupModal from '@/modules/authorization/components/ActionGroupModal.vue'
import ActionModal from '@/modules/authorization/components/ActionModal.vue'
import ActionPermissionsModal from '@/modules/authorization/components/ActionPermissionsModal.vue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'
import gruposService from '@/modules/authorization/services/gruposAccion'
import accionesService from '@/modules/authorization/services/acciones'
import { text } from '@/shared/utils/regex'
import { toast } from 'vue3-toastify'
import { useValidatePermission } from '@/shared/composables/useValidatePermission'

const showFilters = ref('0')
const filters = reactive({ search: '', per_page: 10 })

// Columnas de la tabla de acciones
const columns = [
  { field: 'accion', header: 'Acción', style: { width: '30%' } },
  { field: 'descripcion', header: 'Descripción', style: { width: '45%' } },
  { field: 'activo', header: 'Estado', style: { width: '15%' } }
]

// Estado por grupo: items (acciones), filtros internos, paginación local
const grupos = ref([])
const openGroups = ref([]) // ids de grupo abiertos
const groupModal = reactive({ open: false, group: null })
const actionModal = reactive({ open: false, group: null, accion: null })
const permissionsModal = reactive({ open: false, action: null, permisos: [] })

// Permisos
const { can } = useValidatePermission()
const canCreateGroup = computed(() => can('seguridad_grupo_accion_guardar'))
const canEditGroup = computed(() => can('seguridad_grupo_accion_actualizar'))
const canToggleGroupState = computed(() => can('seguridad_grupo_accion_estado'))
const canCreateAction = computed(() => can('seguridad_accion_guardar'))
const canEditAction = computed(() => can('seguridad_accion_actualizar'))
const canDeleteAction = computed(() => can('seguridad_accion_eliminar'))
const canShowAction = computed(() => can('seguridad_accion_mostrar'))

function normalizeArrayResponse(res) {
  // El backend puede devolver [] o { data: [] }
  if (Array.isArray(res?.data)) return res.data
  if (Array.isArray(res)) return res
  return []
}

async function loadGrupos() {
  const res = await gruposService.listar({
    search: filters.search || undefined,
    per_page: undefined
  })
  const raw = normalizeArrayResponse(res)
  const opened = new Set(Array.isArray(openGroups.value) ? openGroups.value : [])
  // Inicializa estado UI por grupo
  grupos.value = raw.map((g) => {
    const ui = reactive({
      search: '',
      actions: [],
      page: 1,
      per_page: 10,
      total: 0,
      pagedItems: [],
      loaded: false
    })
    return { ...g, ui }
  })
  if (opened.size) {
    const toReload = grupos.value.filter((g) => opened.has(g.id_grupo))
    if (toReload.length) {
      await Promise.all(toReload.map((grp) => loadAccionesForGroup(grp)))
    }
  }
}

async function loadAccionesForGroup(grp) {
  try {
    const acciones = await accionesService.listar({
      id_grupo: grp.id_grupo,
      accion: grp.ui.search || undefined
    })
    grp.ui.actions = Array.isArray(acciones) ? acciones : []
    grp.ui.total = grp.ui.actions.length
    grp.ui.page = 1
    applyPagination(grp)
    grp.ui.loaded = true
  } catch (e) {
    console.error(e)
    grp.ui.actions = []
    grp.ui.total = 0
    grp.ui.pagedItems = []
  }
}

function applyPagination(grp) {
  const start = (grp.ui.page - 1) * grp.ui.per_page
  const end = start + grp.ui.per_page
  grp.ui.pagedItems = grp.ui.actions.slice(start, end)
}

function paginateGroup(grp) {
  applyPagination(grp)
}

async function applyFilters() {
  // Validación básica del texto usando regex de proyecto
  if (filters.search && !text.test(filters.search)) {
    toast.error('El texto de búsqueda contiene caracteres inválidos')
    return
  }
  await loadGrupos()
}

async function onClearFilters() {
  filters.search = ''
  await loadGrupos()
}

// Acciones de grupo (menú en encabezado)
function getGroupActions(grp) {
  const items = []
  if (canEditGroup.value) items.push({ label: 'Editar', icon: 'edit', command: () => onEditGroup(grp) })
  if (canEditGroup.value && canToggleGroupState.value) items.push({ separator: true })
  if (canToggleGroupState.value)
    items.push({
      label: grp.activo ? 'Desactivar' : 'Activar',
      icon: grp.activo ? 'block' : 'check_circle',
      command: () => onToggleGroupState(grp)
    })
  return items.filter((it, idx, arr) => {
    if (!it.separator) return true
    const prev = arr[idx - 1]
    const next = arr[idx + 1]
    return prev && !prev.separator && next && !next.separator
  })
}
function hasGroupActions(grp) {
  return getGroupActions(grp).length > 0
}

function onAddGroup() {
  groupModal.group = null
  groupModal.open = true
}
function onEditGroup(grp) {
  groupModal.group = {
    id_grupo: grp.id_grupo,
    nombre: grp.nombre ?? '',
    descripcion: grp.descripcion ?? ''
  }
  groupModal.open = true
}
function onToggleGroupState(grp) {
  confirm.type = 'estado'
  confirm.row = {
    id_grupo: grp.id_grupo,
    nombre: grp.nombre || grp.id_grupo,
    activo: grp.activo === true
  }
  confirm.message =
    grp.activo === true
      ? `¿Desactivar el grupo "${grp.nombre || grp.id_grupo}"?`
      : `¿Activar el grupo "${grp.nombre || grp.id_grupo}"?`
  confirm.open = true
}

// Acciones por fila de acción
function getRowActions(grp, row) {
  const items = []
  if (canShowAction.value) items.push({ label: 'Ver permisos', icon: 'visibility', command: () => onViewPermisos(row) })
  if (canShowAction.value && canEditAction.value) items.push({ separator: true })
  if (canEditAction.value) items.push({ label: 'Editar', icon: 'edit', command: () => onEditAccion(row, grp) })
  if ((canShowAction.value || canEditAction.value) && canEditAction.value) items.push({ separator: true })
  if (canEditAction.value)
    items.push({
      label: row.activo ? 'Desactivar' : 'Activar',
      icon: row.activo ? 'block' : 'check_circle',
      command: () => onToggleAccionState(row, grp)
    })
  if ((canShowAction.value || canEditAction.value) && (canEditAction.value || canDeleteAction.value)) items.push({ separator: true })
  if (canDeleteAction.value) items.push({ label: 'Eliminar', icon: 'delete', command: () => onDeleteAccion(row, grp) })
  return items.filter((it, idx, arr) => {
    if (!it.separator) return true
    const prev = arr[idx - 1]
    const next = arr[idx + 1]
    return prev && !prev.separator && next && !next.separator
  })
}

function onAddAccion(grp) {
  actionModal.group = grp
  actionModal.accion = null
  actionModal.open = true
}
function onEditAccion(row, grp) {
  actionModal.group = grp
  actionModal.accion = { id_accion: row.id_accion }
  actionModal.open = true
}
function onViewPermisos(row) {
  loadAccionPermisos(row)
}
function onDeleteAccion(row, grp) {
  confirm.type = 'accion'
  confirm.row = { row, grp }
  confirm.message = `¿Eliminar la acción "${row.accion}"?`
  confirm.open = true
}

function onToggleAccionState(row, grp) {
  confirm.type = 'accion-estado'
  confirm.row = { row, grp }
  confirm.message =
    row.activo === true
      ? `¿Desactivar la acción "${row.accion}"?`
      : `¿Activar la acción "${row.accion}"?`
  confirm.open = true
}

async function loadAccionPermisos(row) {
  try {
    const data = await accionesService.mostrar(row.id_accion)
    permissionsModal.action = {
      accion: data?.accion ?? row.accion,
      grupo: data?.grupo ?? row.grupo
    }
    permissionsModal.permisos = Array.isArray(data?.permisos_asociados)
      ? data.permisos_asociados
          .map((perm) => ({
            permiso: typeof perm === 'string' ? perm : perm?.permiso,
            descripcion: typeof perm === 'object' ? perm?.descripcion ?? '' : ''
          }))
          .filter((perm) => !!perm.permiso)
      : []
    permissionsModal.open = true
  } catch (error) {
    console.error(error)
    toast.error(error?.message || 'No se pudieron cargar los permisos de la acción')
  }
}

async function onSearchAcciones(grp) {
  if (grp.ui.search && !text.test(grp.ui.search)) {
    toast.error('El texto de búsqueda contiene caracteres inválidos')
    return
  }
  await loadAccionesForGroup(grp)
}

async function onClearAcciones(grp) {
  grp.ui.search = ''
  await loadAccionesForGroup(grp)
}

// Menú por grupo (refs)
const groupMenus = ref([])
const setGroupMenuRef = (el, index) => {
  if (el) groupMenus.value[index] = el
}
const toggleGroupMenu = (event, index) => {
  groupMenus.value[index]?.toggle(event)
}

// Confirmación eliminar
const confirm = reactive({ open: false, message: '', row: null, busy: false, type: '' })

async function confirmYes() {
  if (confirm.busy) return
  try {
    confirm.busy = true
    if (confirm.type === 'accion' && confirm.row) {
      await accionesService.eliminar(confirm.row.row.id_accion)
      await loadAccionesForGroup(confirm.row.grp)
      toast.success('Acción eliminada correctamente')
    } else if (confirm.type === 'estado' && confirm.row) {
      await gruposService.actualizarEstado(confirm.row.id_grupo)
      await loadGrupos()
    } else if (confirm.type === 'accion-estado' && confirm.row) {
      await accionesService.actualizarEstado(confirm.row.row.id_accion)
      await loadAccionesForGroup(confirm.row.grp)
    }
  } catch (e) {
    console.error(e)
    toast.error(e?.message || 'No se pudo completar la acción')
  } finally {
    confirm.busy = false
    confirm.open = false
  }
}

async function onGroupSaved(payload) {
  const mode = payload?.mode
  groupModal.group = null
  if (mode === 'create') {
    toast.success('Grupo creado correctamente')
  } else if (mode === 'update') {
    toast.success('Grupo actualizado correctamente')
  } else {
    toast.success('Grupo guardado correctamente')
  }
  try {
    await loadGrupos()
  } catch (error) {
    console.error(error)
    toast.error(error?.message || 'No se pudo refrescar la información de los grupos')
  }
}

async function onActionSaved() {
  const targetGroup = actionModal.group
  actionModal.accion = null
  if (!targetGroup) return
  try {
    await loadAccionesForGroup(targetGroup)
  } catch (error) {
    console.error(error)
    toast.error(error?.message || 'No se pudo actualizar la lista de acciones')
  } finally {
    if (!actionModal.open) {
      actionModal.group = null
    }
  }
}

onMounted(() => {
  loadGrupos()
})

// Limpiar referencias cuando se cierra el modal de acción
watch(
  () => actionModal.open,
  (open) => {
    if (!open) {
      actionModal.accion = null
      actionModal.group = null
    }
  }
)

// Cargar acciones al expandir un grupo por primera vez
watch(
  () => openGroups.value.slice(),
  (newVals, oldVals) => {
    const prev = Array.isArray(oldVals) ? oldVals : []
    const added = (Array.isArray(newVals) ? newVals : []).filter((v) => !prev.includes(v))
    if (!added.length) return
    for (const id of added) {
      const grp = grupos.value.find((g) => g.id_grupo === id)
      if (grp && !grp.ui.loaded) {
        loadAccionesForGroup(grp)
      }
    }
  }
)
</script>
