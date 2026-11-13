<template>
  <div class="admin-shell flex h-dvh overflow-hidden bg-surface text-text" v-bind="$attrs">
    <AdminSidebar v-if="!esMovil" :collapsed="contraido" @expand="contraido = false" @toggle="contraido = !contraido" />
    <Drawer v-else v-model:visible="menuMovilAbierto" position="left" :block-scroll="true" class="admin-shell admin-mobile-drawer" aria-label="Navegación principal">
      <template #container>
        <AdminSidebar movil @toggle="cerrarMenuMovil" @search="cerrarMenuMovil" />
      </template>
    </Drawer>
    <div class="flex min-w-0 flex-1 flex-col">
      <div v-if="esMovil" class="shrink-0 px-4 pt-3">
        <button
          type="button"
          class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg bg-surface-card text-secondary shadow-xs focus-visible:outline-2 focus-visible:outline-brand"
          aria-label="Abrir navegación"
          aria-haspopup="dialog"
          :aria-expanded="menuMovilAbierto"
          @click="menuMovilAbierto = true"
        >
          <Icon name="menu" fontSize="20" />
        </button>
      </div>
      <main class="admin-content min-w-0 flex-1 overflow-y-auto p-4 md:p-6">
        <RouterView />
      </main>
    </div>
    <SearchModal />
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useMediaQuery, useStorage } from '@vueuse/core'
import { Drawer } from 'primevue'
import AdminSidebar from '@/shared/layouts/AdminSidebar.vue'
import Icon from '@/shared/components/Icon.vue'
import SearchModal from '@/shared/components/SearchModal.vue'
import { useAuthStore } from '@/shared/stores/auth'
import { useRootStore } from '@/shared/stores/root'
import '@/styles/admin-sidebar.css'

defineOptions({ inheritAttrs: false })

const route = useRoute()
const rootStore = useRootStore()
const authStore = useAuthStore()
const esMovil = useMediaQuery('(max-width: 767px)')
const contraido = useStorage('theme-sidebar-collapsed', false)
const menuMovilAbierto = ref(false)

function cerrarMenuMovil() {
  menuMovilAbierto.value = false
}

watch(() => route.path, cerrarMenuMovil)
watch(esMovil, cerrarMenuMovil)
watch(() => rootStore.isSearchModalOpen, (abierto) => {
  if (abierto) cerrarMenuMovil()
})

onMounted(() => {
  rootStore.isSideBarOpen = false
  authStore.obtenerCuenta()
})
</script>
