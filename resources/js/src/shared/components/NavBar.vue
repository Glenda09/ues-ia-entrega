<template>
  <div class="sticky top-0 z-40 flex justify-between align-center min-h-18 p-4 bg-brand shadow-md">
    <div class="flex flex-row items-center gap-4">
      <Button text @click="toggleSideBarState" class="text-brand-contrast!">
        <Icon name="menu" />
      </Button>

      <div>
        <router-link :to="baseUrl" class="flex items-center">
          <img v-if="appLogoUrl" :src="appLogoUrl" :alt="appName" class="h-10" />
        </router-link>
      </div>
    </div>
    <div>
      <Button
        text
        type="button"
        @click="toggle"
        aria-haspopup="true"
        class="text-brand-contrast!"
        data-testid="navbar-btn-user-actions"
      >
        <span class="ml-2 font-bold">{{ usuario?.username }}</span>
        <Icon name="account_circle" class="ml-2" />
        <Icon name="arrow_drop_down" />
      </Button>
    </div>
  </div>

  <Menu ref="menu" :model="items" :popup="true" class="mt-2">
    <template #item="{ item, props }">
      <a v-ripple class="flex items-center" v-bind="props.action" :data-testid="item.testId">
        <Icon v-if="item.icon" :name="item.icon" class="mr-2" />
        <span>{{ item.label }}</span>
      </a>
    </template>
  </Menu>

  <ChangePasswordModal v-model:open="isChangePasswordOpen" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { Button, Menu } from 'primevue'
import router from '@/shared/router'
import { useAppBrand } from '@/shared/composables/useAppBrand'
import { useColorMode } from '@/shared/composables/useColorMode'

import ChangePasswordModal from '@/shared/components/ChangePasswordModal.vue'

import { useRootStore } from '@/shared/stores/root'
import { useAuthStore } from '@/shared/stores/auth'

const menu = ref()

const authStore = useAuthStore()
const { usuario } = storeToRefs(authStore)
const isChangePasswordOpen = ref(false)
const { baseUrl, appName, appLogoUrl } = useAppBrand()
const { isDark, toggleMode } = useColorMode()

const onLogout = async () => {
  await authStore.logout()
}

const goToProfile = () => {
  router.replace({ name: 'personal.perfil' })
}

const items = computed(() => [
  {
    items: [
      {
        label: 'Perfil',
        icon: 'account_circle',
        testId: 'go-to-profile',
        command: goToProfile
      },
      {
        label: isDark.value ? 'Tema claro' : 'Tema oscuro',
        icon: isDark.value ? 'light_mode' : 'dark_mode',
        testId: 'navbar-theme-toggle',
        command: toggleMode
      },
      {
        label: 'Cambiar contraseña',
        icon: 'lock',
        testId: 'navbar-change-password',
        command: () => (isChangePasswordOpen.value = true)
      },
      {
        label: 'Cerrar sesión',
        icon: 'logout',
        testId: 'navbar-logout',
        command: onLogout
      }
    ]
  }
])

const toggle = (event) => {
  menu.value.toggle(event)
}

const rootStore = useRootStore()
const { toggleSideBarState } = rootStore

onMounted(() => {
  authStore.obtenerCuenta()
})
</script>
