<template>
  <div class="flex flex-col min-h-screen" v-bind="$attrs">
    <!-- Navbar superior -->
    <header class="sticky top-0 z-40 flex items-center min-h-16 px-4 bg-brand">
      <!-- Botón hamburguesa + logo -->
      <div class="flex items-center gap-3">
        <Button
          text
          type="button"
          class="text-brand-contrast!"
          aria-label="Alternar menú"
          :aria-expanded="isSidebarOpen"
          @click="toggleSidebar"
        >
          <Icon :name="isSidebarOpen ? 'menu_open' : 'menu'" />
        </Button>

        <router-link :to="baseUrl" class="flex items-center gap-2">
          <img v-if="appLogoDarkUrl" :src="appLogoDarkUrl" :alt="appName || 'Logo'" class="h-8" />
        </router-link>
      </div>

      <!-- Acciones de usuario -->
      <div class="ml-auto flex items-center">
        <Button
          text
          type="button"
          @click="toggleUserMenu"
          aria-haspopup="true"
          class="text-brand-contrast!"
        >
          <span class="ml-2 font-bold">{{ usuario?.username }}</span>
          <Icon name="account_circle" class="ml-2" />
          <Icon name="arrow_drop_down" />
        </Button>
      </div>
    </header>

    <Menu ref="userMenuRef" :model="userMenuItems" :popup="true" class="mt-2">
      <template #item="{ item, props }">
        <a v-ripple class="flex items-center" v-bind="props.action">
          <Icon v-if="item.icon" :name="item.icon" class="mr-2" />
          <span>{{ item.label }}</span>
        </a>
      </template>
    </Menu>

    <!-- Backdrop (solo overlay en móvil/tablet) -->
    <transition name="fade">
      <div
        v-if="isSidebarOpen"
        class="fixed top-16 left-0 right-0 bottom-0 bg-overlay z-30 lg:hidden"
        @click="closeSidebar"
      />
    </transition>

    <!-- Contenedor inferior: sidebar + contenido -->
    <div class="flex flex-1">
      <!-- Sidebar:
          - Móvil (<md): overlay a pantalla completa
          - Tablet (md..lg): overlay con ancho fijo
          - Desktop (>=lg): en flujo, empuja contenido -->
      <aside
        class="
          overflow-hidden bg-surface shadow-xl
          fixed top-16 left-0 bottom-0 z-40
          lg:relative lg:top-0 lg:bottom-auto lg:z-auto lg:shrink-0
          transition-[width,transform] duration-300 ease-in-out
        "
        :class="
          isSidebarOpen
            ? 'translate-x-0 w-screen md:w-80 2xl:w-72'
            : '-translate-x-full w-screen md:w-80 lg:translate-x-0 lg:w-0 2xl:w-0'
        "
      >
        <nav class="px-3 py-4 h-full overflow-y-auto w-screen md:w-80 2xl:w-72">
          <ul class="space-y-4">
            <li v-for="(item, index) in menu" :key="`menu-${index}`">
              <!-- Item híbrido: el cuerpo navega y el chevron despliega -->
              <template v-if="item.children && item.children.length && item.path">
                <div
                  class="w-full flex items-center rounded-lg text-sm hover:bg-brand-hover hover:text-brand dark:hover:text-brand-contrast transition-colors duration-200"
                  :class="[
                    { 'bg-brand-active text-brand-contrast': isActive(item.path) },
                    { 'text-surface-contrast': !isActive(item.path) }
                  ]"
                >
                  <RouterLink :to="item.path" class="flex flex-1 items-center gap-2 px-3 py-2">
                    <img
                      v-if="item.iconUrl"
                      :src="item.iconUrl"
                      :alt="`Ícono de ${item.title}`"
                      class="h-4 w-4 object-contain"
                    />
                    <Icon v-else-if="item.icon" :name="item.icon" class="text-base" />
                    <span>{{ item.title }}</span>
                  </RouterLink>
                  <button
                    type="button"
                    class="flex items-center self-stretch px-2 rounded-lg"
                    :aria-label="`Desplegar ${item.title}`"
                    :aria-expanded="isDropdownOpen(index)"
                    @click.prevent.stop="toggleDropdown(index)"
                  >
                    <Icon
                      name="arrow_drop_down"
                      class="transition-transform duration-200 text-base"
                      :class="{ 'rotate-180': isDropdownOpen(index) }"
                    />
                  </button>
                </div>
                <transition name="fade">
                  <ul v-show="isDropdownOpen(index)" class="mt-1 ml-3 border-l border-brand-50/30 pl-2 space-y-1">
                    <li v-for="(child, cIdx) in item.children" :key="`submenu-${index}-${cIdx}`">
                      <RouterLink
                        :to="child.path"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm hover:bg-brand-hover hover:text-brand dark:hover:text-brand-contrast transition-colors duration-200"
                        :class="[
                          { 'bg-brand-active text-brand-contrast': isActive(child.path) },
                          { 'text-surface-contrast': !isActive(child.path) }
                        ]"
                      >
                        <img
                          v-if="child.iconUrl"
                          :src="child.iconUrl"
                          :alt="`Ícono de ${child.title}`"
                          class="h-4 w-4 object-contain"
                        />
                        <Icon v-else-if="child.icon" :name="child.icon" class="text-base" />
                        <span>{{ child.title }}</span>
                      </RouterLink>
                    </li>
                  </ul>
                </transition>
              </template>

              <!-- Item con hijos -->
              <template v-else-if="item.children && item.children.length">
                <button
                  type="button"
                  class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm text-surface-contrast hover:bg-brand-hover hover:text-brand dark:hover:text-brand-contrast transition-colors duration-200"
                  @click="toggleDropdown(index)"
                >
                  <div class="flex items-center gap-2">
                    <img
                      v-if="item.iconUrl"
                      :src="item.iconUrl"
                      :alt="`Ícono de ${item.title}`"
                      class="h-4 w-4 object-contain"
                    />
                    <Icon v-else-if="item.icon" :name="item.icon" class="text-base" />
                    <span>{{ item.title }}</span>
                  </div>
                  <Icon
                    name="arrow_drop_down"
                    class="transition-transform duration-200 text-base"
                    :class="{ 'rotate-180': isDropdownOpen(index) }"
                  />
                </button>
                <transition name="fade">
                  <ul v-show="isDropdownOpen(index)" class="mt-1 ml-3 border-l border-brand-50/30 pl-2 space-y-1">
                    <li v-for="(child, cIdx) in item.children" :key="`submenu-${index}-${cIdx}`">
                      <RouterLink
                        :to="child.path"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm hover:bg-brand-hover hover:text-brand dark:hover:text-brand-contrast transition-colors duration-200"
                        :class="[
                          { 'bg-brand-active text-brand-contrast': isActive(child.path) },
                          { 'text-surface-contrast': !isActive(child.path) }
                        ]"
                      >
                        <img
                          v-if="child.iconUrl"
                          :src="child.iconUrl"
                          :alt="`Ícono de ${child.title}`"
                          class="h-4 w-4 object-contain"
                        />
                        <Icon v-else-if="child.icon" :name="child.icon" class="text-base" />
                        <span>{{ child.title }}</span>
                      </RouterLink>
                    </li>
                  </ul>
                </transition>
              </template>

              <!-- Item simple -->
              <template v-else>
                <RouterLink
                  :to="item.path"
                  class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm hover:bg-brand-hover hover:text-brand dark:hover:text-brand-contrast transition-colors duration-200"
                  :class="[
                    { 'bg-brand-active text-brand-contrast': isActive(item.path) },
                    { 'text-surface-contrast': !isActive(item.path) }
                  ]"
                >
                  <img
                    v-if="item.iconUrl"
                    :src="item.iconUrl"
                    :alt="`Ícono de ${item.title}`"
                    class="h-4 w-4 object-contain"
                  />
                  <Icon v-else-if="item.icon" :name="item.icon" class="text-base" />
                  <span>{{ item.title }}</span>
                </RouterLink>
              </template>
            </li>
          </ul>
        </nav>
      </aside>

      <!-- Contenido principal -->
      <main class="flex-1 min-w-0 overflow-y-auto">
        <router-view class="m-8" />
      </main>
    </div>

    <ChangePasswordModal v-model:open="isChangePasswordOpen" />
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted, watch } from 'vue'
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
const { menu, isSideBarOpen: isSidebarOpen } = storeToRefs(rootStore)
const { usuario } = storeToRefs(authStore)
const { baseUrl, appName, appLogoDarkUrl } = useAppBrand()
const { isDark, toggleMode } = useColorMode()

const userMenuRef = ref()
const isChangePasswordOpen = ref(false)
const isDesktop = ref(false)
const dropdownState = ref({})

let desktopMql

function isActive(path) {
  return route.path === path
}

function isDropdownOpen(index) {
  return !!dropdownState.value[index]
}

function toggleDropdown(index) {
  dropdownState.value[index] = !dropdownState.value[index]
}

function toggleSidebar() {
  rootStore.toggleSideBarState()
}

function closeSidebar() {
  rootStore.isSideBarOpen = false
}

function syncOpenStates() {
  if (!menu.value) return
  for (const [idx, item] of menu.value.entries()) {
    if (item?.children?.some((c) => c.path === route.path)) {
      dropdownState.value[idx] = true
    }
  }
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

function handleViewportChange(event) {
  isDesktop.value = event.matches
}

onMounted(() => {
  desktopMql = window.matchMedia('(min-width: 1024px)')
  isDesktop.value = desktopMql.matches
  rootStore.isSideBarOpen = desktopMql.matches
  desktopMql.addEventListener('change', handleViewportChange)

  syncOpenStates()
  authStore.obtenerCuenta()
})

onUnmounted(() => {
  desktopMql?.removeEventListener('change', handleViewportChange)
})

watch(() => route.path, () => {
  syncOpenStates()
  if (!isDesktop.value) closeSidebar()
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
