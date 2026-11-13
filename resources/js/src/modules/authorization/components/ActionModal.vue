<template>
  <transition name="fade">
    <div
      v-if="visible"
      class="fixed inset-0 z-40 bg-overlay backdrop-blur-xs transition-all duration-300"
      @click="onCancel"
    />
  </transition>

  <Dialog
    v-model:visible="visible"
    :modal="true"
    :draggable="false"
    :closable="false"
    pt:root="w-full md:!w-7/12 lg:!w-5/12 z-50 bg-surface-card border-surface-0 dark:border-surface-900 mx-4 md:mx-0"
    pt:header="block!"
  >
    <template #header>
      <h3 class="text-xl font-semibold w-full">
        {{ isEdit ? 'Editar acción' : 'Nueva acción' }}
      </h3>
      <br />
      <p class="text-sm text-secondary w-full">Grupo: {{ groupLabel }}</p>
    </template>

    <div class="flex flex-col gap-4 mb-4">
      <div>
        <label class="block text-sm text-secondary mb-2" for="accion">Acción</label>
        <InputTextWithIcon
          id="accion"
          v-model="form.accion"
          placeholder="ej: Crear usuario"
          :left-icon="{ name: 'bolt', color: 'text-secondary' }"
          input-class="pr-3! py-4!"
          :disabled="loading"
        />
        <p v-if="errors.accion" class="text-danger text-xs mt-1">{{ errors.accion }}</p>
      </div>

      <div>
        <label class="block text-sm text-secondary mb-2" for="descripcion">Descripción</label>
        <Textarea
          id="descripcion"
          v-model="form.descripcion"
          :auto-resize="true"
          rows="3"
          placeholder="Describe la acción"
          class="w-full"
          :disabled="loading"
        />
        <p v-if="errors.descripcion" class="text-danger text-xs mt-1">{{ errors.descripcion }}</p>
      </div>

      <div>
        <div class="mb-2">
          <label class="block text-sm text-secondary" for="permisos">Permisos del paquete</label>
          <p class="text-xs text-secondary mt-1">Esta acción no se chequea en las rutas: solo agrupa permisos atómicos.</p>
        </div>

        <!-- Buscador -->
        <div class="mb-3">
          <div class="flex flex-col gap-2 md:flex-row md:items-center md:gap-4">
            <div class="relative flex-1">
              <InputTextWithIcon
                v-model="permissionsSearch"
                placeholder="Buscar permiso o descripción"
                :left-icon="{ name: 'search', color: 'text-secondary' }"
                input-class="pr-3! py-3!"
                :disabled="permissionsLoading || loading"
              />
              <Button
                v-if="permissionsSearch"
                type="button"
                variant="text"
                class="!p-0 absolute! top-1/2 -translate-y-1/2 right-2"
                aria-label="Limpiar búsqueda"
                title="Limpiar búsqueda"
                @click="permissionsSearch = ''"
              >
                <Icon name="close" />
              </Button>
            </div>
            <label
              for="ver-permisos-seleccionados"
              class="inline-flex items-center gap-2 text-sm text-secondary select-none"
            >
              <Checkbox
                id="ver-permisos-seleccionados"
                v-model="showSelectedOnly"
                binary
                :disabled="permissionsLoading"
              />
              <span>Ver seleccionados</span>
            </label>
          </div>
        </div>

        <DataTable
          v-model:selection="selectedPermissionRows"
          :value="filteredPermissions"
          dataKey="permiso"
          :loading="permissionsLoading"
          :metaKeySelection="false"
          :scrollable="false"
          :tableStyle="{ width: '100%' }"
          class="permissions-table"
          paginator
          :rows="5"
          :rowsPerPageOptions="[5, 10, 20]"
        >
          <Column selectionMode="multiple" :style="{ width: '60px' }" />
          <Column field="permiso" header="Permiso" :style="{ width: '30%' }" />
          <Column field="descripcion" header="Descripción" />
        </DataTable>
        <p v-if="errors.permisos" class="text-danger text-xs mt-1">{{ errors.permisos }}</p>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-4 mt-4">
        <Button
          type="button"
          severity="secondary"
          outlined
          class="mr-2 w-full lg:w-2/6 2xl:w-1/6"
          :disabled="loading || submitting"
          @click="onCancel"
        >
          Cancelar
        </Button>
        <Button
          type="button"
          class="text-brand-contrast w-full lg:w-3/6 2xl:w-2/6"
          :disabled="submitting || loading"
          @click="onSubmit"
        >
          {{
            submitting
              ? isEdit
                ? 'Actualizando...'
                : 'Creando...'
              : isEdit
              ? 'Actualizar'
              : 'Crear'
          }}
        </Button>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { reactive, computed, ref, watch } from 'vue'
import { Dialog, Button, DataTable, Column, Textarea, Checkbox } from 'primevue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'
import Icon from '@/shared/components/Icon.vue'
import accionesService from '@/modules/authorization/services/acciones'
import permisosService from '@/modules/authorization/services/permisos'
import { textarea as textareaRegex } from '@/shared/utils/regex'
import { toast } from 'vue3-toastify'

const ACTION_REGEX = /^[A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ ]+$/

const props = defineProps({
  open: { type: Boolean, default: false },
  group: { type: Object, default: null },
  accion: { type: Object, default: null }
})
const emit = defineEmits(['update:open', 'saved'])

const visible = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
})

const isEdit = computed(() => !!props.accion?.id_accion)
const groupLabel = computed(() => props.group?.nombre || props.group?.id_grupo || '-')
const submitting = ref(false)
const loading = ref(false)

const form = reactive({
  accion: '',
  descripcion: ''
})

const errors = reactive({ accion: '', descripcion: '', permisos: '' })

const permissions = ref([])
const permissionsLoaded = ref(false)
const permissionsLoading = ref(false)
const permissionsSearch = ref('')
const showSelectedOnly = ref(false)

const selectedPermissionRows = ref([])
const selectedPermissionIds = ref([])

watch(selectedPermissionRows, (rows) => {
  selectedPermissionIds.value = Array.isArray(rows) ? rows.map((row) => row.permiso) : []
})

const filteredPermissions = computed(() => {
  if (!permissions.value?.length) return []
  const term = (permissionsSearch.value || '').toLowerCase().trim()
  const selectedSet = showSelectedOnly.value ? new Set(selectedPermissionIds.value) : null
  const base = showSelectedOnly.value
    ? permissions.value.filter((item) => selectedSet?.has(item.permiso))
    : permissions.value
  if (!term) return base
  return base.filter(
    (item) =>
      item.permiso?.toLowerCase().includes(term) || item.descripcion?.toLowerCase().includes(term)
  )
})

watch(
  () => props.open,
  async (open) => {
    if (open) {
      await initialize()
    }
  }
)

async function initialize() {
  resetState()
  try {
    if (!props.group?.id_grupo) {
      toast.error('No se encontró el grupo seleccionado para la acción')
      onCancel()
      return
    }
    await loadPermissions()
    if (isEdit.value) {
      await loadAccionDetalle(props.accion.id_accion)
    }
  } catch (error) {
    console.error(error)
    toast.error(error?.message || 'No se pudo preparar el formulario de acción')
  }
}

function resetState() {
  form.accion = ''
  form.descripcion = ''
  errors.accion = ''
  errors.descripcion = ''
  errors.permisos = ''
  permissionsSearch.value = ''
  showSelectedOnly.value = false
  selectedPermissionRows.value = []
  selectedPermissionIds.value = []
}

async function loadPermissions(force = false) {
  if (permissionsLoading.value) return
  if (permissionsLoaded.value && !force) {
    syncSelection()
    return
  }
  try {
    permissionsLoading.value = true
    const { data } = await permisosService.listar({ per_page: 200 })
    permissions.value = Array.isArray(data) ? data : []
    permissionsLoaded.value = true
    syncSelection()
  } catch (error) {
    console.error(error)
    toast.error(error?.message || 'No se pudieron cargar los permisos disponibles')
  } finally {
    permissionsLoading.value = false
  }
}

async function loadAccionDetalle(id_accion) {
  if (!id_accion) return
  try {
    loading.value = true
    const data = await accionesService.mostrar(id_accion)
    form.accion = data?.accion ?? ''
    form.descripcion = data?.descripcion ?? ''
    const asociados = Array.isArray(data?.permisos_asociados)
      ? data.permisos_asociados
          .map((perm) => (!perm ? null : typeof perm === 'string' ? perm : perm.permiso))
          .filter(Boolean)
      : []
    selectedPermissionIds.value = asociados
    syncSelection()
  } catch (error) {
    console.error(error)
    toast.error(error?.message || 'No se pudo cargar la información de la acción')
  } finally {
    loading.value = false
  }
}

function syncSelection() {
  if (!permissions.value?.length) {
    selectedPermissionRows.value = []
    return
  }
  const map = new Map(permissions.value.map((perm) => [perm.permiso, perm]))
  selectedPermissionRows.value = selectedPermissionIds.value
    .map((id) => map.get(id))
    .filter(Boolean)
}

function validate() {
  errors.accion = ''
  errors.descripcion = ''
  errors.permisos = ''

  const accionValue = form.accion?.trim()
  if (!accionValue) {
    errors.accion = 'La acción es obligatoria'
  } else if (!ACTION_REGEX.test(accionValue)) {
    errors.accion = 'Solo se permiten caracteres alfanuméricos y espacios'
  }

  if (form.descripcion) {
    const descripcionValue = form.descripcion.trim()
    if (!textareaRegex.test(descripcionValue)) {
      errors.descripcion = 'La descripción contiene caracteres inválidos'
    }
  }

  if (!selectedPermissionIds.value.length) {
    errors.permisos = 'Selecciona al menos un permiso'
  }

  return !errors.accion && !errors.descripcion && !errors.permisos
}

async function onSubmit() {
  if (submitting.value || loading.value) return
  if (!validate()) return

  try {
    submitting.value = true
    const payload = {
      id_grupo: props.group.id_grupo,
      accion: form.accion.trim(),
      descripcion: form.descripcion ? form.descripcion.trim() : null,
      permisos: selectedPermissionIds.value
    }

    if (isEdit.value) {
      await accionesService.actualizar(props.accion.id_accion, {
        accion: payload.accion,
        descripcion: payload.descripcion
      })
      await accionesService.syncPermisos(props.accion.id_accion, payload.permisos)
      toast.success('Acción actualizada correctamente')
      emit('saved', { mode: 'update', id_accion: props.accion.id_accion })
    } else {
      await accionesService.crear(payload)
      toast.success('Acción creada correctamente')
      emit('saved', { mode: 'create' })
    }
    onCancel()
  } catch (error) {
    console.error(error)
    toast.error(error?.response?.data?.message || error?.message || 'No se pudo guardar la acción')
  } finally {
    submitting.value = false
  }
}

function onCancel() {
  emit('update:open', false)
}
</script>
