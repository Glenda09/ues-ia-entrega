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
        {{ isEdit ? 'Editar perfil' : 'Crear perfil' }}
      </h3>
    </template>

    <div class="flex flex-col gap-4 mb-4">
      <!-- Código -->
      <div>
        <label class="block text-sm text-secondary mb-2" for="codigo">Código</label>
        <InputTextWithIcon
          id="codigo"
          v-model="form.codigo"
          placeholder="ej: supervisor"
          :left-icon="{ name: 'tag', color: 'text-secondary' }"
          input-class="pr-3! py-4!"
          :disabled="isEdit"
        />
        <p v-if="errors.codigo" class="text-danger text-xs mt-1">{{ errors.codigo }}</p>
      </div>

      <!-- Nombre -->
      <div>
        <label class="block text-sm text-secondary mb-2" for="nombre">Nombre</label>
        <InputTextWithIcon
          id="nombre"
          v-model="form.nombre"
          placeholder="ej: Supervisor"
          :left-icon="{ name: 'drive_file_rename_outline', color: 'text-secondary' }"
          input-class="pr-3! py-4!"
        />
        <p v-if="errors.nombre" class="text-danger text-xs mt-1">{{ errors.nombre }}</p>
      </div>

      <!-- Descripción -->
      <div>
        <label class="block text-sm text-secondary mb-2" for="descripcion">Descripción</label>
        <Textarea
          id="descripcion"
          v-model="form.descripcion"
          :auto-resize="true"
          rows="3"
          placeholder="Describe el perfil"
          class="w-full"
        />
        <p v-if="errors.descripcion" class="text-danger text-xs mt-1">{{ errors.descripcion }}</p>
      </div>

      <!-- Acciones -->
      <div>
        <label class="block text-sm text-secondary mb-2" for="acciones">Acciones</label>
        <p class="text-xs text-secondary mb-2">Cada acción es un paquete: otorga el grupo de permisos que tenga asociados.</p>
        <div class="flex gap-2 items-center">
          <Dropdown
            v-model="accionSeleccionada"
            :options="accionOptions"
            optionLabel="label"
            optionValue="id_accion"
            :filter="true"
            placeholder="Selecciona una acción"
            class="w-full"
            @update:modelValue="onDropdownSelect"
          />
        </div>

        <!-- Tabla de acciones seleccionadas -->
        <div class="mt-3">
          <div class="mb-2">
            <div class="relative">
              <InputTextWithIcon
                v-model="search"
                placeholder="Buscar acción o grupo"
                :left-icon="{ name: 'search', color: 'text-secondary' }"
                input-class="pr-3! py-3!"
              />
              <Button
                v-if="search"
                type="button"
                variant="text"
                class="!p-0 absolute! top-1/2 -translate-y-1/2 right-2"
                aria-label="Limpiar búsqueda"
                title="Limpiar búsqueda"
                @click="search = ''"
              >
                <Icon name="close" />
              </Button>
            </div>
          </div>

          <DataTable
            :value="filteredAccionesSeleccionadas"
            :scrollable="false"
            :tableStyle="{ width: '100%' }"
          >
            <Column field="grupo.nombre" header="Grupo" :style="{ width: '35%' }">
              <template #body="{ data }">
                <span>{{ data?.grupo?.nombre || data?.grupo?.id_grupo || '-' }}</span>
              </template>
            </Column>
            <Column field="accion" header="Acción" />
            <Column :style="{ width: '60px' }">
              <template #body="{ data }">
                <div class="flex justify-center">
                  <Button variant="text" title="Quitar" @click="onRemoveAccion(data.id_accion)">
                    <Icon name="delete" />
                  </Button>
                </div>
              </template>
            </Column>
          </DataTable>
        </div>
        <p v-if="errors.acciones" class="text-danger text-xs mt-1">{{ errors.acciones }}</p>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-4">
        <Button
          type="button"
          severity="secondary"
          outlined
          class="mr-2 w-full lg:w-2/6 2xl:w-1/6"
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
import { Dialog, Button, Dropdown, DataTable, Column, Textarea } from 'primevue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'
import Icon from '@/shared/components/Icon.vue'
import { usePerfilesStore } from '@/modules/authorization/stores/perfiles'
import { alpha, text as textRegex } from '@/shared/utils/regex'

const props = defineProps({
  open: { type: Boolean, default: false },
  profile: { type: Object, default: null },
  acciones: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:open', 'saved'])

const visible = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
})

const isEdit = computed(() => !!props.profile)
const submitting = ref(false)

const form = reactive({
  codigo: '',
  nombre: '',
  descripcion: '',
  acciones: [] // array de uuids
})

const errors = reactive({ codigo: '', nombre: '', descripcion: '', acciones: '' })

const accionSeleccionada = ref(null)
const accionOptions = computed(() => {
  const arr = Array.isArray(props.acciones) ? props.acciones : []
  const selected = new Set(Array.isArray(form.acciones) ? form.acciones : [])
  return arr
    .filter((a) => !selected.has(a.id_accion))
    .map((a) => ({
      ...a,
      label: `${a?.grupo?.nombre || a?.grupo?.id_grupo || '-'} - ${a?.accion || ''}`
    }))
})

const accionesSeleccionadas = computed(() => {
  if (!Array.isArray(form.acciones)) return []
  const fullMap = new Map((props.acciones || []).map((a) => [a.id_accion, a]))
  return form.acciones.map((id) => fullMap.get(id)).filter(Boolean)
})
const search = ref('')
const filteredAccionesSeleccionadas = computed(() => {
  const term = (search.value || '').toLowerCase()
  if (!term) return accionesSeleccionadas.value
  return accionesSeleccionadas.value.filter((x) => {
    const g = x?.grupo?.nombre || x?.grupo?.id_grupo || ''
    const a = x?.accion || ''
    return g.toLowerCase().includes(term) || a.toLowerCase().includes(term)
  })
})

function hydrateFromProfile() {
  if (!props.profile) {
    form.codigo = ''
    form.nombre = ''
    form.descripcion = ''
    form.acciones = []
    return
  }
  form.codigo = props.profile.codigo ?? ''
  form.nombre = props.profile.nombre ?? ''
  form.descripcion = props.profile.descripcion ?? ''
  // Normaliza acciones: pueden venir como array de uuids o de objetos
  form.acciones = Array.isArray(props.profile.acciones)
    ? props.profile.acciones.map((a) => (typeof a === 'string' ? a : a?.id_accion)).filter(Boolean)
    : []
}

watch(
  () => props.profile,
  () => {
    hydrateFromProfile()
    clearErrors()
    accionSeleccionada.value = null
  },
  { immediate: true }
)

function onCancel() {
  emit('update:open', false)
}

function clearErrors() {
  errors.codigo = ''
  errors.nombre = ''
  errors.descripcion = ''
  errors.acciones = ''
}

function validate() {
  clearErrors()
  let ok = true
  // Código: minúsculas y guion bajo
  if (!form.codigo || !/^[a-z_]+$/.test(form.codigo)) {
    errors.codigo = 'Usa solo minúsculas y guiones bajos'
    ok = false
  }
  // Nombre: solo letras y espacios (usar alpha de regex.js para letras)
  const nameNoSpaces = (form.nombre || '').replace(/\s/g, '')
  if (!form.nombre || !alpha.test(nameNoSpaces)) {
    errors.nombre = 'Usa solo letras y espacios'
    ok = false
  }
  // Descripción: sin caracteres especiales (usar text de regex.js: letras, números y espacios)
  if (form.descripcion && !textRegex.test(form.descripcion)) {
    errors.descripcion = 'Solo letras, números y espacios'
    ok = false
  }
  // Acciones: al menos un array válido
  if (!Array.isArray(form.acciones)) form.acciones = []
  return ok
}

function onDropdownSelect(val) {
  const id = val
  if (!id) return
  if (!Array.isArray(form.acciones)) form.acciones = []
  if (!form.acciones.includes(id)) form.acciones.push(id)
  accionSeleccionada.value = null
}

function onRemoveAccion(id) {
  if (!Array.isArray(form.acciones)) return
  form.acciones = form.acciones.filter((x) => x !== id)
}

async function onSubmit() {
  if (!validate()) return
  submitting.value = true
  try {
    const payload = {
      codigo: form.codigo,
      nombre: form.nombre,
      descripcion: form.descripcion || undefined,
      acciones: form.acciones || []
    }
    const store = usePerfilesStore()
    if (isEdit.value) {
      await store.actualizar(form.codigo, {
        nombre: payload.nombre,
        descripcion: payload.descripcion,
        acciones: payload.acciones
      })
    } else {
      await store.crear(payload)
    }
    emit('saved')
  } catch (e) {
    console.error(e)
  } finally {
    submitting.value = false
  }
}
</script>
