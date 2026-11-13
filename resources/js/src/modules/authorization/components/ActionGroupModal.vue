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
  >
    <template #header>
      <h3 class="text-xl font-semibold">
        {{ isEdit ? 'Editar grupo de acción' : 'Crear grupo de acción' }}
      </h3>
    </template>

    <div class="flex flex-col gap-4 mb-4">
      <div>
        <label class="block text-sm text-secondary mb-2" for="grupo-id">ID Grupo</label>
        <InputTextWithIcon
          id="grupo-id"
          v-model="form.id_grupo"
          placeholder="Ej: administracion"
          :left-icon="{ name: 'badge', color: 'text-secondary' }"
          input-class="pr-3! py-4!"
          :disabled="isEdit"
          @input="onIdInput"
        />
        <p v-if="errors.id_grupo" class="text-danger text-xs mt-1">{{ errors.id_grupo }}</p>
      </div>

      <div>
        <label class="block text-sm text-secondary mb-2" for="grupo-nombre">Nombre</label>
        <InputTextWithIcon
          id="grupo-nombre"
          v-model="form.nombre"
          placeholder="Ej: Administración"
          :left-icon="{ name: 'drive_file_rename_outline', color: 'text-secondary' }"
          input-class="pr-3! py-4!"
          @input="onNombreInput"
        />
        <p v-if="errors.nombre" class="text-danger text-xs mt-1">{{ errors.nombre }}</p>
      </div>

      <div>
        <label class="block text-sm text-secondary mb-2" for="grupo-descripcion">Descripción</label>
        <Textarea
          id="grupo-descripcion"
          v-model="form.descripcion"
          :auto-resize="true"
          rows="3"
          placeholder="Describe el grupo"
          class="w-full"
          :maxlength="DESCRIPTION_MAX_LENGTH"
          @input="onDescripcionInput"
        />
        <div class="flex items-center text-xs mt-1">
          <p v-if="errors.descripcion" class="text-danger">{{ errors.descripcion }}</p>
          <span class="ml-auto text-secondary">{{ form.descripcion.length }} / {{ DESCRIPTION_MAX_LENGTH }}</span>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-4">
        <Button
          type="button"
          severity="secondary"
          outlined
          class="mr-2 w-full lg:w-2/6 2xl:w-1/6"
          :disabled="submitting"
          @click="onCancel"
        >
          Cancelar
        </Button>
        <Button
          type="button"
          class="text-brand-contrast w-full lg:w-3/6 2xl:w-2/6"
          :disabled="submitting"
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
import { reactive, computed, watch, ref } from 'vue'
import { Dialog, Textarea, Button } from 'primevue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'
import gruposService from '@/modules/authorization/services/gruposAccion'
import { toast } from 'vue3-toastify'

const DESCRIPTION_MAX_LENGTH = 255
const ID_CLEAN_PATTERN = /[^A-Za-z_]/g
const ID_VALID_PATTERN = /^[A-Za-z_]+$/
const NAME_CLEAN_PATTERN = /[^A-Za-zÁÉÍÓÚáéíóúÑñÜü ]/g
const NAME_VALID_PATTERN = /^[A-Za-zÁÉÍÓÚáéíóúÑñÜü ]+$/

const props = defineProps({
  open: { type: Boolean, default: false },
  group: { type: Object, default: null }
})
const emit = defineEmits(['update:open', 'saved'])

const visible = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
})

const isEdit = computed(() => !!props.group)
const submitting = ref(false)

const form = reactive({ id_grupo: '', nombre: '', descripcion: '' })
const errors = reactive({ id_grupo: '', nombre: '', descripcion: '' })

function clearErrors() {
  errors.id_grupo = ''
  errors.nombre = ''
  errors.descripcion = ''
}

function sanitizeId(value) {
  return (value || '').replace(ID_CLEAN_PATTERN, '')
}
function sanitizeNombre(value) {
  return (value || '').replace(NAME_CLEAN_PATTERN, '')
}
function sanitizeDescripcion(value) {
  if (!value) return ''
  return value.slice(0, DESCRIPTION_MAX_LENGTH)
}

function hydrateFromGroup() {
  if (!props.group) {
    form.id_grupo = ''
    form.nombre = ''
    form.descripcion = ''
    return
  }
  form.id_grupo = props.group.id_grupo ?? ''
  form.nombre = props.group.nombre ?? ''
  form.descripcion = sanitizeDescripcion(props.group.descripcion ?? '')
}

watch(
  () => props.group,
  () => {
    if (props.open) {
      hydrateFromGroup()
      clearErrors()
    }
  }
)

watch(
  () => props.open,
  (val) => {
    if (val) {
      hydrateFromGroup()
      clearErrors()
    } else {
      form.id_grupo = ''
      form.nombre = ''
      form.descripcion = ''
      clearErrors()
    }
  },
  { immediate: true }
)

function onIdInput(event) {
  if (isEdit.value) return
  const next = sanitizeId(event?.target?.value)
  if (form.id_grupo !== next) {
    form.id_grupo = next
  }
}

function onNombreInput(event) {
  const next = sanitizeNombre(event?.target?.value)
  if (form.nombre !== next) {
    form.nombre = next
  }
}

function onDescripcionInput(event) {
  const next = sanitizeDescripcion(event?.target?.value)
  if (form.descripcion !== next) {
    form.descripcion = next
  }
}

function validate() {
  clearErrors()
  let ok = true
  if (!form.id_grupo) {
    errors.id_grupo = 'El ID del grupo es requerido'
    ok = false
  } else if (!ID_VALID_PATTERN.test(form.id_grupo) && !isEdit.value) {
    errors.id_grupo = 'Usa solo letras y guiones bajos'
    ok = false
  }

  if (!form.nombre) {
    errors.nombre = 'El nombre es requerido'
    ok = false
  } else if (!NAME_VALID_PATTERN.test(form.nombre)) {
    errors.nombre = 'Usa solo letras y espacios'
    ok = false
  }

  if (form.descripcion.length > DESCRIPTION_MAX_LENGTH) {
    errors.descripcion = `Máximo ${DESCRIPTION_MAX_LENGTH} caracteres`
    ok = false
  }

  return ok
}

function onCancel() {
  if (submitting.value) return
  emit('update:open', false)
}

async function onSubmit() {
  if (!validate()) return
  submitting.value = true
  try {
    const payload = {
      nombre: form.nombre,
      descripcion: form.descripcion || undefined
    }

    if (isEdit.value) {
      await gruposService.actualizar(form.id_grupo, payload)
      emit('saved', { id_grupo: form.id_grupo, mode: 'update' })
    } else {
      await gruposService.crear({
        id_grupo: form.id_grupo,
        nombre: form.nombre,
        descripcion: form.descripcion || undefined
      })
      emit('saved', { id_grupo: form.id_grupo, mode: 'create' })
    }
    emit('update:open', false)
  } catch (error) {
    console.error(error)
    const message = error?.response?.data?.message || error?.message || 'No se pudo guardar el grupo'
    toast.error(message)
  } finally {
    submitting.value = false
  }
}
</script>
