<template>
  <aside
    class="admin-sidebar relative z-30 flex h-full shrink-0 flex-col overflow-hidden bg-sidebar text-sidebar-contrast transition-[width] duration-[280ms] [transition-timing-function:cubic-bezier(0.4,0,0.2,1)]"
    :class="collapsed ? 'admin-sidebar--collapsed w-[60px]' : 'w-[224px]'"
    aria-label="Barra lateral"
    data-testid="admin-sidebar"
  >
    <div class="flex h-12 shrink-0 items-center gap-2 px-2.5">
      <RouterLink
        :to="baseUrl"
        class="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg px-1.5 py-1 focus-visible:outline-2 focus-visible:outline-brand"
        :class="collapsed ? 'justify-center' : ''"
        :aria-label="appName || 'Inicio'"
      >
        <img v-if="appLogoUrl" :src="appLogoUrl" alt="" class="h-6 w-5 shrink-0 object-contain" />
        <span v-if="!collapsed" class="min-w-0 truncate text-[13px] font-semibold">{{ appName }}</span>
      </RouterLink>
      <button
        v-if="!collapsed"
        type="button"
        class="flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center rounded-md text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-contrast focus-visible:outline-2 focus-visible:outline-brand"
        :aria-label="movil ? 'Cerrar navegación' : 'Contraer barra lateral'"
        :title="movil ? 'Cerrar navegación' : 'Contraer barra lateral'"
        data-testid="admin-sidebar-toggle"
        @click="emit('toggle')"
      >
        <Icon name="left_panel_close" fontSize="18" />
      </button>
    </div>
    <div v-if="collapsed" class="shrink-0 px-2.5 pb-2">
      <button
        v-tooltip.right="'Expandir barra lateral'"
        type="button"
        class="flex h-9 w-full cursor-pointer items-center justify-center rounded-lg text-sidebar-muted hover:bg-sidebar-hover focus-visible:outline-2 focus-visible:outline-brand"
        aria-label="Expandir barra lateral"
        data-testid="admin-sidebar-toggle"
        @click="emit('toggle')"
      >
        <Icon name="left_panel_open" fontSize="20" />
      </button>
    </div>

    <div class="shrink-0 px-2.5 pb-2">
      <button
        v-if="!collapsed"
        type="button"
        class="admin-sidebar-search flex h-8 w-full cursor-pointer items-center gap-2 rounded-lg px-2 text-left text-xs text-sidebar-muted focus-visible:outline-2 focus-visible:outline-brand"
        aria-label="Buscar en el panel"
        data-testid="admin-sidebar-search"
        @click="abrirBusqueda"
      >
        <Icon name="search" fontSize="18" class="shrink-0" />
        <span class="min-w-0 flex-1 truncate">Buscar…</span>
        <kbd class="admin-sidebar-kbd">Ctrl K</kbd>
      </button>
      <button
        v-else
        v-tooltip.right="'Buscar en el panel'"
        type="button"
        class="flex h-9 w-full cursor-pointer items-center justify-center rounded-lg text-sidebar-muted hover:bg-sidebar-hover focus-visible:outline-2 focus-visible:outline-brand"
        aria-label="Buscar en el panel"
        @click="abrirBusqueda"
      >
        <Icon name="search" fontSize="20" />
      </button>
    </div>

    <nav
      class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-2.5 py-1.5"
      aria-label="Navegación principal"
    >
      <ul class="flex flex-col pb-4" :class="collapsed ? 'gap-2' : 'gap-1'">
        <li v-for="(item, index) in menu" :key="`${item.path || item.title}-${index}`">
          <template v-if="item.children?.length">
            <button
              v-tooltip.right="collapsed ? tituloGrupo(item) : null"
              type="button"
              class="admin-nav-item flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2 text-left text-xs transition-colors hover:bg-sidebar-hover focus-visible:outline-2 focus-visible:outline-brand"
              :class="[
                isParentActive(item) ? 'font-semibold text-link' : 'text-sidebar-muted',
                collapsed ? 'justify-center' : ''
              ]"
              :aria-expanded="isOpen(index)"
              :aria-label="collapsed ? tituloGrupo(item) : undefined"
              @click="toggle(index)"
            >
              <Icon :name="item.icon || 'folder'" fontSize="18" class="shrink-0" />
              <span v-if="!collapsed" class="min-w-0 flex-1 truncate">{{ item.title }}</span>
              <Icon
                v-if="!collapsed"
                name="expand_more"
                fontSize="18"
                class="transition-transform duration-200"
                :class="isOpen(index) ? 'rotate-180' : ''"
              />
            </button>
            <Transition name="admin-submenu">
              <ul
                v-if="!collapsed && isOpen(index)"
                class="admin-subnav ml-[17px] mt-1 flex flex-col gap-1 pl-2"
              >
                <li v-if="item.path">
                  <RouterLink
                    :to="item.path"
                    class="admin-nav-item admin-nav-item--child flex items-center gap-2.5 rounded-lg px-2 text-xs hover:bg-sidebar-hover focus-visible:outline-2 focus-visible:outline-brand"
                    :class="isActive(item.path) ? claseActivo() : 'text-sidebar-muted'"
                    :aria-current="isActive(item.path) ? 'page' : undefined"
                  >
                    <Icon :name="item.icon || 'folder'" fontSize="16" />
                    <span class="truncate">{{ item.title }}</span>
                  </RouterLink>
                </li>
                <li v-for="child in item.children" :key="child.path">
                  <RouterLink
                    :to="child.path"
                    class="admin-nav-item admin-nav-item--child flex items-center gap-2.5 rounded-lg px-2 text-xs transition-colors hover:bg-sidebar-hover focus-visible:outline-2 focus-visible:outline-brand"
                    :class="
                      isActive(child.path)
                        ? claseActivo()
                        : 'text-sidebar-muted'
                    "
                    :aria-current="isActive(child.path) ? 'page' : undefined"
                  >
                    <Icon :name="child.icon || 'chevron_right'" fontSize="16" class="shrink-0" />
                    <span class="truncate">{{ child.title }}</span>
                  </RouterLink>
                </li>
              </ul>
            </Transition>
          </template>

          <RouterLink
            v-else
            v-tooltip.right="collapsed ? item.title : null"
            :to="item.path"
            class="admin-nav-item flex items-center gap-2.5 rounded-lg px-2 text-xs transition-colors hover:bg-sidebar-hover focus-visible:outline-2 focus-visible:outline-brand"
            :class="[
              isActive(item.path) ? claseActivo() : 'text-sidebar-muted',
              collapsed ? 'justify-center' : ''
            ]"
            :aria-label="collapsed ? item.title : undefined"
            :aria-current="isActive(item.path) ? 'page' : undefined"
          >
            <Icon :name="item.icon || 'grid_view'" fontSize="18" class="shrink-0" />
            <span v-if="!collapsed" class="truncate">{{ item.title }}</span>
          </RouterLink>
        </li>
      </ul>
    </nav>

    <div
      class="admin-sidebar-footer flex shrink-0 gap-1 px-2.5 py-2"
      :class="collapsed ? 'flex-col items-stretch' : 'items-center'"
    >
      <button
        v-tooltip.right="collapsed ? (isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro') : null"
        type="button"
        class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center self-center rounded-lg text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-contrast focus-visible:outline-2 focus-visible:outline-brand"
        :class="collapsed ? 'order-first w-full' : 'order-last'"
        :aria-label="isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
        :title="isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
        data-testid="admin-theme-toggle"
        @click="toggleMode"
      >
        <Icon :name="isDark ? 'light_mode' : 'dark_mode'" fontSize="18" />
      </button>
      <button
        type="button"
        class="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 rounded-lg p-1.5 text-left hover:bg-sidebar-hover focus-visible:outline-2 focus-visible:outline-brand"
        :class="collapsed ? 'justify-center' : ''"
        aria-haspopup="true"
        :aria-label="`Opciones de la cuenta ${usuario?.username || ''}`"
        data-testid="admin-user-actions"
        @click="toggleUserMenu"
      >
        <span
          class="relative flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold uppercase text-brand"
          aria-hidden="true"
        >
          {{ inicialUsuario }}
        </span>
        <span v-if="!collapsed" class="flex min-w-0 flex-col leading-tight">
          <span class="truncate text-xs font-medium text-sidebar-contrast">
            {{ usuario?.username || 'Cuenta' }}
          </span>
          <span class="truncate text-xs text-sidebar-muted">{{ usuario?.email || '' }}</span>
        </span>
      </button>
    </div>

    <Menu class="admin-account-menu" ref="userMenu" :model="userMenuItems" :popup="true">
      <template #item="{ item, props: menuProps }">
        <a v-ripple class="flex items-center" v-bind="menuProps.action">
          <Icon v-if="item.icon" :name="item.icon" class="mr-2" />
          <span>{{ item.label }}</span>
        </a>
      </template>
    </Menu>
    <ChangePasswordModal v-model:open="isChangePasswordOpen" />
  </aside>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { Menu } from 'primevue'
import Icon from '@/shared/components/Icon.vue'
import ChangePasswordModal from '@/shared/components/ChangePasswordModal.vue'
import { useAppBrand } from '@/shared/composables/useAppBrand'
import { useColorMode } from '@/shared/composables/useColorMode'
import { useAuthStore } from '@/shared/stores/auth'
import { useRootStore } from '@/shared/stores/root'

const props = defineProps({
  collapsed: { type: Boolean, default: false },
  movil: { type: Boolean, default: false }
})
const emit = defineEmits(['expand', 'toggle', 'search'])
const route = useRoute()
const router = useRouter()
const rootStore = useRootStore()
const authStore = useAuthStore()
const { menu } = storeToRefs(rootStore)
const { usuario } = storeToRefs(authStore)
const { baseUrl, appName, appLogoUrl } = useAppBrand()
const { isDark, toggleMode } = useColorMode()
const opened = ref({})
const userMenu = ref()
const isChangePasswordOpen = ref(false)
const activePath = computed(() => route.path)
const inicialUsuario = computed(() => (usuario.value?.username || 'U').trim().charAt(0))
const userMenuItems = computed(() => [{
  items: [
    { label: 'Perfil', icon: 'account_circle', command: () => router.push({ name: 'personal.perfil' }) },
    { label: 'Cambiar contraseña', icon: 'lock', command: () => (isChangePasswordOpen.value = true) },
    { separator: true },
    { label: 'Cerrar sesión', icon: 'logout', command: () => authStore.logout() }
  ]
}])

function toggleUserMenu(event) {
  userMenu.value?.toggle(event)
}

function abrirBusqueda() {
  emit('search')
  rootStore.toggleSearchModal()
}

function claseActivo() {
  return 'bg-sidebar-active font-medium text-link'
}

function isActive(path) {
  return Boolean(path) && activePath.value === path
}

function isParentActive(item) {
  return isActive(item.path) || item.children?.some((child) => isActive(child.path))
}

function tituloGrupo(item) {
  const hijoActivo = item.children?.find((child) => isActive(child.path))
  return hijoActivo ? `${item.title} / ${hijoActivo.title}` : item.title
}

function isOpen(index) {
  return Boolean(opened.value[index])
}

function toggle(index) {
  if (props.collapsed) {
    emit('expand')
    opened.value[index] = true
    return
  }
  opened.value[index] = !opened.value[index]
}

function syncOpened() {
  menu.value.forEach((item, index) => {
    if (item.children?.some((child) => isActive(child.path))) opened.value[index] = true
  })
}

watch([menu, activePath, () => props.collapsed], syncOpened, { immediate: true })
</script>
