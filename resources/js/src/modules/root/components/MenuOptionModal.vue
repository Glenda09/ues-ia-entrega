<template>
  <transition name="fade">
    <div v-if="visible" class="fixed inset-0 bg-overlay backdrop-blur-xs z-40" @click="onCancel" />
  </transition>

  <Dialog
    v-model:visible="visible"
    modal
    :draggable="false"
    :closable="false"
    pt:root="w-full md:!w-8/12 lg:!w-6/12 xl:!w-5/12 mx-4 md:mx-0"
  >
    <template #header>
      <div class="flex flex-col gap-1">
        <h2 class="text-xl font-semibold">Opción de menú</h2>
      </div>
    </template>

    <div v-if="detailLoading" class="flex flex-col gap-4 py-6">
      <span class="text-secondary">Cargando detalles...</span>
    </div>

    <div v-else class="flex flex-col gap-4">
      <div class="border border-dashed border-secondary-100 rounded-md p-4 flex flex-col gap-2">
        <p class="text-sm"><strong>Módulo:</strong> {{ detail?.id_modulo || 'No definido' }}</p>
        <p class="text-sm" v-if="detail?.padre">
          <strong>Padre actual:</strong>
          {{ detail.padre?.nombre || detail.padre?.id_opcion || 'No definido' }}
        </p>
        <p class="text-sm" v-else>
          <strong>Padre actual:</strong> {{ detail?.id_opcion_padre ? 'No definido' : 'Sin padre' }}
        </p>
        <p class="text-sm"><strong>Ruta:</strong> {{ detail?.ruta || 'Sin ruta' }}</p>

        <Divider />

        <h3 class="text-sm font-semibold mb-2">Permisos asociados</h3>
        <ul
          v-if="permissions.length"
          class="list-disc list-inside space-y-1 max-h-48 overflow-y-auto"
        >
          <li v-for="permiso in permissions" :key="permiso" class="text-sm text-text">
            {{ permiso }}
          </li>
        </ul>
        <p v-else class="text-sm text-secondary">Sin permisos asignados.</p>
      </div>

      <div class="grid grid-cols-1 gap-4 mb-12">
        <div>
          <label class="block text-sm text-secondary mb-2" for="menu-nombre">Nombre *</label>
          <InputTextWithIcon
            id="menu-nombre"
            v-model="form.nombre"
            placeholder="Dashboard"
            :left-icon="{ name: 'text_fields', color: 'text-secondary' }"
            :disabled="saving"
            input-class="pr-3! py-4!"
          />
          <p v-if="errors.nombre" class="text-danger text-xs mt-1">{{ errors.nombre }}</p>
        </div>
        <div>
          <label class="block text-sm text-secondary mb-2" for="menu-icono">Ícono *</label>
          <InputTextWithIcon
            id="menu-icono"
            v-model="form.icono"
            placeholder="home"
            :right-icon="{ name: form.icono, color: 'text-secondary' }"
            :disabled="saving"
            input-class="pr-3! py-4!"
          />
          <p v-if="errors.icono" class="text-danger text-xs mt-1">{{ errors.icono }}</p>
        </div>
        <div v-if="showParentField">
          <label class="block text-sm text-secondary mb-2" for="menu-padre">Opción padre</label>
          <div class="flex gap-2 items-center">
            <Dropdown
              v-model="form.id_opcion_padre"
              :options="parentOptions"
              optionLabel="label"
              optionValue="value"
              :filter="true"
              placeholder="Sin padre"
              class="w-full"
              :disabled="saving"
              @update:modelValue="onDropdownSelect"
            />
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-3 w-full">
        <Button
          type="button"
          severity="secondary"
          outlined
          class="w-full sm:w-auto"
          :disabled="saving"
          @click="onCancel"
        >
          Cancelar
        </Button>
        <Button
          type="button"
          class="btn btn-primary w-full sm:w-auto"
          :loading="saving"
          :disabled="detailLoading"
          @click="onSubmit"
        >
          {{ saving ? 'Guardando...' : 'Guardar cambios' }}
        </Button>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Dialog, Button, Dropdown, Divider } from 'primevue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'
import { useRootMenuStore } from '@/modules/root/stores/menu'
import { toast } from 'vue3-toastify'

const props = defineProps({
  open: { type: Boolean, default: false },
  optionId: { type: String, default: null }
})

const emit = defineEmits(['update:open', 'saved'])

const menuStore = useRootMenuStore()
const visible = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value)
})

const form = reactive({
  nombre: '',
  icono: '',
  id_opcion_padre: null
})

const errors = reactive({ nombre: '', icono: '' })
const detail = ref(null)
const detailLoading = computed(() => menuStore.detailLoading)
const saving = computed(() => menuStore.saving)

const permissions = computed(() => {
  const list = detail.value?.permisos
  return Array.isArray(list) ? list : []
})

const parentOptions = computed(() => {
  const parents = Array.isArray(menuStore.parents) ? menuStore.parents : []
  const currentId = props.optionId
  return parents
    .filter((parent) => parent.id_opcion !== currentId)
    .map((parent) => ({
      label: parent.nombre || parent.id_opcion || 'No definido',
      value: parent.id_opcion
    }))
})

const showParentField = computed(() => {
  if (!detail.value) return false
  if (typeof detail.value.desplegable === 'boolean') return !detail.value.desplegable
  return !Array.isArray(detail.value.hijos) || detail.value.hijos.length === 0
})

function resetForm() {
  form.nombre = ''
  form.icono = ''
  form.id_opcion_padre = null
  errors.nombre = ''
  errors.icono = ''
  detail.value = null
}

async function loadDetail() {
  if (!props.optionId) return
  resetForm()
  try {
    const [parents, info] = await Promise.all([
      menuStore.fetchParents(),
      menuStore.obtenerDetalle(props.optionId)
    ])
    detail.value = info
    form.nombre = info?.nombre || ''
    form.icono = info?.icono || ''
    form.id_opcion_padre = info?.id_opcion_padre || null
    if (!Array.isArray(parents) || parents.length === 0) {
      // no-op, parents already handled by store
    }
  } catch (error) {
    toast.error('No fue posible cargar la opción seleccionada.')
    console.error(error)
  }
}

function validate() {
  errors.nombre = form.nombre ? '' : 'El nombre es obligatorio.'
  errors.icono = form.icono ? '' : 'El ícono es obligatorio.'
  return !errors.nombre && !errors.icono
}

async function onSubmit() {
  if (!detail.value || !validate()) return

  try {
    const payload = {
      id_modulo: detail.value.id_modulo,
      id_opcion_padre: showParentField.value
        ? form.id_opcion_padre || null
        : detail.value.id_opcion_padre ?? null,
      nombre: form.nombre,
      ruta: detail.value.ruta ?? null,
      icono: form.icono,
      orden: detail.value.orden,
      id_estado: detail.value.id_estado,
      permisos: Array.isArray(detail.value.permisos) ? detail.value.permisos : [],
      desplegable: detail.value.desplegable
    }
    await menuStore.actualizar(detail.value.id_opcion, payload)
    emit('saved')
  } catch (error) {
    toast.error('No fue posible actualizar la opción.')
    console.error(error)
  }
}

function onCancel() {
  emit('update:open', false)
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      loadDetail()
    } else {
      resetForm()
    }
  }
)

watch(
  () => props.optionId,
  (newId, oldId) => {
    if (props.open && newId && newId !== oldId) {
      loadDetail()
    }
  }
)
</script>
