<template>
  <transition name="fade">
    <div
      v-if="visible"
      class="fixed inset-0 z-40 bg-overlay backdrop-blur-xs transition-all duration-300"
      @click="handleClose"
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
      <div>
        <h3 class="text-xl font-semibold">Crear API Key</h3>
        <p class="text-sm text-secondary">Registra una credencial para integraciones externas.</p>
      </div>
    </template>

    <div class="flex flex-col gap-6">
      <div>
        <label for="cliente" class="block text-sm text-secondary mb-2">Nombre del cliente</label>
        <InputTextWithIcon
          id="cliente"
          v-model="form.cliente"
          placeholder="Ej. Integración Contable"
          :left-icon="{ name: 'business', color: 'text-secondary' }"
          input-class="pr-3! py-4!"
          :invalid="v$.cliente.$error || !!errors.cliente"
        />
        <p v-if="v$.cliente.$error" class="text-danger text-xs mt-1">
          {{ v$.cliente.$errors[0]?.$message }}
        </p>
        <p v-else-if="errors.cliente" class="text-danger text-xs mt-1">{{ errors.cliente }}</p>
      </div>

      <div>
        <label for="permisos" class="block text-sm text-secondary mb-2">Permisos</label>
        <Dropdown
          id="permisos"
          v-model="selectedPermission"
          :options="permissionOptions"
          optionLabel="label"
          optionValue="permiso"
          placeholder="Selecciona un permiso"
          class="w-full"
          showClear
          :filter="true"
          filterPlaceholder="Buscar permiso"
          @change="onSelectPermission($event.value)"
        />
        <p v-if="errors.permisos" class="text-danger text-xs mt-1">{{ errors.permisos }}</p>
      </div>

      <div>
        <h4 class="text-sm font-semibold text-secondary mb-3">Permisos seleccionados</h4>
        <div v-if="selectedPermissions.length" class="border border-secondary-100 rounded-lg">
          <DataTable
            :value="selectedPermissions"
            dataKey="permiso"
            :virtualScrollerOptions="{ itemSize: 44 }"
          >
            <Column field="permiso" header="Permiso" :style="{ width: '45%' }" />
            <Column field="descripcion" header="Descripción" />
            <Column header="" :style="{ width: '80px' }">
              <template #body="{ data }">
                <Button
                  type="button"
                  variant="text"
                  aria-label="Remover permiso"
                  @click="removePermission(data.permiso)"
                >
                  <Icon name="delete" />
                </Button>
              </template>
            </Column>
          </DataTable>
        </div>
        <p v-else class="text-sm text-secondary">Sin permisos asociados por ahora.</p>
      </div>
    </div>

    <template #footer>
      <div class="flex flex-col lg:flex-row gap-3 w-full justify-end">
        <Button
          type="button"
          severity="secondary"
          outlined
          class="w-full lg:w-1/6"
          :disabled="submitting"
          @click="handleClose"
        >
          Cancelar
        </Button>
        <Button
          type="button"
          class="btn btn-primary w-full lg:w-2/6"
          :disabled="submitting"
          @click="handleSubmit"
        >
          <template v-if="submitting">
            <Icon name="progress_activity" class="animate-spin" />
            <span class="ml-2">Creando...</span>
          </template>
          <template v-else>
            <span class="ml-2">Crear</span>
          </template>
        </Button>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import useVuelidate from '@vuelidate/core'
import { required, helpers } from '@vuelidate/validators'
import { Dialog, Button, Dropdown, DataTable, Column } from 'primevue'
import { toast } from 'vue3-toastify'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'
import Icon from '@/shared/components/Icon.vue'
import { useApiKeysStore } from '@/modules/api/stores/api-keys'

const props = defineProps({
  open: { type: Boolean, default: false },
  permisos: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:open', 'created'])

const visible = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value)
})

const form = reactive({
  cliente: '',
  permisos: []
})

const errors = reactive({ cliente: '', permisos: '' })
const submitting = ref(false)
const store = useApiKeysStore()

const rules = computed(() => ({
  cliente: {
    required: helpers.withMessage('El nombre del cliente es obligatorio', required)
  }
}))

const v$ = useVuelidate(rules, form)

const permissionsCatalog = computed(() => {
  if (!Array.isArray(props.permisos)) return []
  return props.permisos
    .map((permiso) => ({
      permiso: permiso?.permiso ?? '',
      descripcion: permiso?.descripcion ?? '',
      label: permiso?.permiso
        ? `${permiso.permiso} · ${permiso.descripcion || 'Sin descripción'}`
        : ''
    }))
    .filter((item) => !!item.permiso)
})

const permissionOptions = computed(() => {
  const selected = new Set(Array.isArray(form.permisos) ? form.permisos : [])
  return permissionsCatalog.value.filter((item) => !selected.has(item.permiso))
})

const permissionsMap = computed(() => {
  return new Map(permissionsCatalog.value.map((item) => [item.permiso, item]))
})

const selectedPermission = ref(null)

const selectedPermissions = computed(() => {
  if (!form.permisos?.length) return []
  const map = permissionsMap.value
  return form.permisos
    .map((code) => map.get(code) || { permiso: code, descripcion: 'Sin descripción' })
    .filter(
      (item, index, arr) =>
        !!item.permiso && arr.findIndex((e) => e.permiso === item.permiso) === index
    )
})

function removePermission(code) {
  form.permisos = form.permisos.filter((permiso) => permiso !== code)
}

function resetForm() {
  form.cliente = ''
  form.permisos = []
  errors.cliente = ''
  errors.permisos = ''
  selectedPermission.value = null
  v$.value.$reset()
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      resetForm()
    }
  }
)

function handleClose() {
  emit('update:open', false)
}

function onSelectPermission(value) {
  if (!value) {
    selectedPermission.value = null
    return
  }
  if (!Array.isArray(form.permisos)) form.permisos = []
  if (!form.permisos.includes(value)) {
    form.permisos.push(value)
  }
  selectedPermission.value = null
}

async function handleSubmit() {
  errors.cliente = ''
  errors.permisos = ''
  const valid = await v$.value.$validate()
  if (!valid) return

  submitting.value = true
  try {
    const payload = {
      cliente: form.cliente.trim(),
      permisos: Array.isArray(form.permisos) ? form.permisos : [],
      id_estado: 'ACTIVO'
    }
    const response = await store.crear(payload)
    emit('created', {
      apiKey: response?.data?.api_key ?? null,
      cliente: payload.cliente,
      permisos: payload.permisos
    })
    emit('update:open', false)
  } catch (error) {
    if (error?.details?.cliente?.length) {
      errors.cliente = error.details.cliente[0]
    }
    if (error?.details?.permisos?.length) {
      errors.permisos = error.details.permisos[0]
    }
    if (error?.message && !errors.cliente && !errors.permisos) {
      toast.error(error.message)
    }
  } finally {
    submitting.value = false
  }
}
</script>
