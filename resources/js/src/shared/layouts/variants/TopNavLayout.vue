<template>
  <div class="flex flex-col min-h-screen" v-bind="$attrs">
    <!-- Barra superior completa -->
    <header class="sticky top-0 z-40 bg-brand shadow-md">
      <div class="flex items-center justify-between min-h-16 px-4 gap-4">
        <!-- Logo -->
        <router-link :to="baseUrl" class="flex items-center gap-2 shrink-0">
          <img v-if="appLogoUrl" :src="appLogoUrl" :alt="appName" class="h-9" />
        </router-link>

        <!-- Items del menú horizontal -->
        <nav class="hidden md:flex items-center gap-1 flex-1 overflow-x-auto">
          <template v-for="(item, index) in menu" :key="`topnav-${index}`">
            <!-- Item con hijos: dropdown -->
            <template v-if="item.children && item.children.length">
              <Button
                text
                type="button"
                class="text-brand-contrast! whitespace-nowrap"
                @click="toggleDropdown(index, $event)"
                :aria-haspopup="true"
              >
                <Icon v-if="item.icon" :name="item.icon" class="text-base" />
                <span class="ml-1 text-sm">{{ item.title }}</span>
                <Icon name="arrow_drop_down" class="text-base transition-transform" :class="{ 'rotate-180': isDropdownOpen(index) }" />
              </Button>
              <Menu
                :ref="(el) => setDropdownRef(index, el)"
                :model="item.children.map(toMenuItem)"
                :popup="true"
                class="mt-2"
              >
                <template #item="{ item: child, props }">
                  <RouterLink
                    :to="child.path"
                    class="flex items-center gap-2 px-3 py-2"
                    v-bind="props.action"
                  >
                    <Icon v-if="child.icon" :name="child.icon" class="text-base" />
                    <span>{{ child.label }}</span>
                  </RouterLink>
                </template>
              </Menu>
            </template>

            <!-- Item simple -->
            <template v-else>
              <RouterLink
                :to="item.path"
                class="flex items-center gap-1 px-3 py-2 rounded-lg text-sm text-brand-contrast hover:bg-brand-hover transition-colors duration-200 whitespace-nowrap"
                :class="{ 'bg-brand-active': isActive(item.path) }"
              >
                <Icon v-if="item.icon" :name="item.icon" class="text-base" />
                <span>{{ item.title }}</span>
              </RouterLink>
            </template>
          </template>
        </nav>

        <!-- Acciones de usuario -->
        <div class="flex items-center gap-2 shrink-0">
          <!-- Botón hamburguesa en móvil -->
          <Button
            text
            class="md:hidden text-brand-contrast!"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >
            <Icon name="menu" />
          </Button>

          <Button
            text
            type="button"
            @click="toggleUserMenu"
            aria-haspopup="true"
            class="text-brand-contrast!"
          >
            <span class="hidden sm:inline font-bold">{{ usuario?.username }}</span>
            <Icon name="account_circle" class="ml-1" />
            <Icon name="arrow_drop_down" />
          </Button>
        </div>
      </div>

      <!-- Menú móvil desplegable -->
      <transition name="fade">
        <nav v-if="mobileMenuOpen" class="md:hidden px-4 pb-4 border-t border-brand-50/20">
          <ul class="mt-3 space-y-1">
            <li v-for="(item, index) in menu" :key="`mobile-${index}`">
              <template v-if="item.children && item.children.length">
                <button
                  type="button"
                  class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm text-brand-contrast hover:bg-brand-hover transition-colors"
                  @click="toggleMobileDropdown(index)"
                >
                  <div class="flex items-center gap-2">
                    <Icon v-if="item.icon" :name="item.icon" />
                    <span>{{ item.title }}</span>
                  </div>
                  <Icon name="arrow_drop_down" :class="{ 'rotate-180': mobileDropdown[index] }" />
                </button>
                <ul v-show="mobileDropdown[index]" class="ml-4 mt-1 space-y-1">
                  <li v-for="(child, cIdx) in item.children" :key="`mobile-child-${index}-${cIdx}`">
                    <RouterLink
                      :to="child.path"
                      class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-brand-contrast hover:bg-brand-hover"
                      :class="{ 'bg-brand-active': isActive(child.path) }"
                      @click="mobileMenuOpen = false"
                    >
                      <Icon v-if="child.icon" :name="child.icon" />
                      <span>{{ child.title }}</span>
                    </RouterLink>
                  </li>
                </ul>
              </template>
              <template v-else>
                <RouterLink
                  :to="item.path"
                  class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-brand-contrast hover:bg-brand-hover"
                  :class="{ 'bg-brand-active': isActive(item.path) }"
                  @click="mobileMenuOpen = false"
                >
                  <Icon v-if="item.icon" :name="item.icon" />
                  <span>{{ item.title }}</span>
                </RouterLink>
              </template>
            </li>
          </ul>
        </nav>
      </transition>
    </header>

    <Menu ref="userMenuRef" :model="userMenuItems" :popup="true" class="mt-2">
      <template #item="{ item, props }">
        <a v-ripple class="flex items-center" v-bind="props.action">
          <Icon v-if="item.icon" :name="item.icon" class="mr-2" />
          <span>{{ item.label }}</span>
        </a>
      </template>
    </Menu>

    <!-- Contenido -->
    <main class="flex-1">
      <router-view class="m-8" />
    </main>

    <ChangePasswordModal v-model:open="isChangePasswordOpen" />
  </div>
</template>

<script setup>
import { computed, ref, onMounted, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { storeToRefs } from 'pinia'
import { Button, Menu } from 'primevue'

import router from '@/shared/router'
import ChangePasswordModal from '@/shared/components/ChangePasswordModal.vue'
import { useAppBrand } from '@/shared/composables/useAppBrand'
import { useColorMode } from '@/shared/composables/useColorMode'
import { useRootStore } from '@/shared/stores/root'
import { useAuthStore } from '@/shared/stores/auth'

defineOptions({ inheritAttrs: false })

const route = useRoute()
const rootStore = useRootStore()
const authStore = useAuthStore()
const { menu } = storeToRefs(rootStore)
const { usuario } = storeToRefs(authStore)
const { baseUrl, appName, appLogoUrl } = useAppBrand()
const { isDark, toggleMode } = useColorMode()

const userMenuRef = ref()
const dropdownRefs = ref({})
const dropdownState = ref({})
const mobileMenuOpen = ref(false)
const mobileDropdown = ref({})
const isChangePasswordOpen = ref(false)

function isActive(path) {
  return route.path === path
}

function isDropdownOpen(index) {
  return !!dropdownState.value[index]
}

function setDropdownRef(index, el) {
  if (el) dropdownRefs.value[index] = el
}

function toggleDropdown(index, event) {
  dropdownRefs.value[index]?.toggle(event)
  dropdownState.value[index] = !dropdownState.value[index]
}

function toggleMobileDropdown(index) {
  mobileDropdown.value[index] = !mobileDropdown.value[index]
}

function toMenuItem(child) {
  return { label: child.title, icon: child.icon, path: child.path }
}

const userMenuItems = computed(() => [
  {
    items: [
      { label: 'Perfil', icon: 'account_circle', command: () => router.replace({ name: 'personal.perfil' }) },
      {
        label: isDark.value ? 'Tema claro' : 'Tema oscuro',
        icon: isDark.value ? 'light_mode' : 'dark_mode',
        command: toggleMode
      },
      { label: 'Cambiar contraseña', icon: 'lock', command: () => (isChangePasswordOpen.value = true) },
      { label: 'Cerrar sesión', icon: 'logout', command: () => authStore.logout() }
    ]
  }
])

function toggleUserMenu(event) {
  userMenuRef.value.toggle(event)
}

onMounted(() => {
  authStore.obtenerCuenta()
})

watch(() => route.path, () => {
  mobileMenuOpen.value = false
})
</script>
