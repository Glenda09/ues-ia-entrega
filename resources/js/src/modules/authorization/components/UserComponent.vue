<template>
  <transition name="fade">
    <div
      v-if="visible"
      class="fixed inset-0 z-40 bg-overlay backdrop-blur-xs transition-all duration-300"
      @click="onCancel"
    ></div>
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
        {{ isEdit ? 'Editar usuario' : 'Crear usuario' }}
      </h3>
    </template>

    <div class="flex flex-col gap-4 mb-4">
      <div>
        <label class="block text-sm text-secondary mb-2" for="username">Usuario</label>
        <InputTextWithIcon
          id="username"
          v-model="form.username"
          placeholder="Ingresa el usuario"
          :left-icon="{ name: 'person', color: 'text-secondary' }"
          input-class="pr-3! py-4!"
          :pt="{ input: { autocomplete: 'username' } }"
        />
      </div>

      <div>
        <label class="block text-sm text-secondary mb-2" for="email">Email</label>
        <InputTextWithIcon
          id="email"
          v-model="form.email"
          placeholder="Ingresa el email"
          :left-icon="{ name: 'mail', color: 'text-secondary' }"
          input-class="pr-3! py-4!"
          :pt="{ input: { type: 'email', autocomplete: 'email' } }"
        />
      </div>

      <div>
        <label class="block text-sm text-secondary mb-2" for="perfiles">Perfiles</label>
        <MultiSelect
          id="perfiles"
          v-model="form.perfiles"
          :options="profileOptions"
          optionLabel="codigo"
          optionValue="codigo"
          :filter="true"
          display="chip"
          placeholder="Selecciona perfiles"
          pt:root="w-full font-sm!"
          size="large"
        />
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
import { Dialog, Button, MultiSelect } from 'primevue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'
import { useUsuariosStore } from '@/modules/authorization/stores/usuarios'

const props = defineProps({
  open: { type: Boolean, default: false },
  user: { type: Object, default: null },
  profiles: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:open', 'saved'])

const visible = computed({
  get: () => props.open,
  set: (val) => emit('update:open', val)
})

const isEdit = computed(() => !!props.user)
const submitting = ref(false)

const form = reactive({
  username: '',
  email: '',
  perfiles: []
})

function normalizeProfile(p) {
  if (typeof p === 'string') return { codigo: p }
  if (p && p.codigo) return { codigo: p.codigo }
  return null
}

const profileOptions = computed(() => {
  // Normaliza a objetos { codigo }
  if (!Array.isArray(props.profiles)) return []
  return props.profiles.map(normalizeProfile).filter(Boolean)
})

function hydrateFromUser() {
  if (!props.user) {
    form.username = ''
    form.email = ''
    form.perfiles = []
    return
  }
  form.username = props.user.username ?? ''
  form.email = props.user.email ?? ''
  // No hay perfiles en el esquema de "listar"; si el padre los provee, úsalo
  form.perfiles = Array.isArray(props.user.perfiles)
    ? props.user.perfiles.map((p) => (typeof p === 'string' ? p : p?.codigo)).filter(Boolean)
    : []
}

watch(
  () => props.user,
  () => {
    hydrateFromUser()
  },
  { immediate: true }
)

function onCancel() {
  emit('update:open', false)
}

// Ya no se parsea texto; MultiSelect mantiene un array de códigos

async function onSubmit() {
  submitting.value = true
  try {
    const payload = {
      username: form.username || undefined,
      email: form.email || undefined,
      perfiles: Array.isArray(form.perfiles) ? form.perfiles : []
    }

    const store = useUsuariosStore()
    if (isEdit.value && props.user?.id_usuario) {
      await store.actualizar(props.user.id_usuario, payload)
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
