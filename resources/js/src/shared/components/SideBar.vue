<template>
  <transition name="fade">
    <div v-if="isSideBarOpen" class="fixed inset-0 z-40 bg-overlay backdrop-blur-xs transition-all duration-300"></div>
  </transition>

  <Drawer v-model:visible="isSideBarOpen" :modal="false"
    class="bg-surface! w-full! md:w-100! lg:w-120! z-50 fixed top-0 border-0!">
    <template #container="{ closeCallback }">
      <div class="flex flex-col min-h-screen min-w-sm">
        <!-- Header -->
        <div class="flex items-center justify-between p-4 shrink-0">
          <div class="min-w-[15%]">
            <Button type="button" text @click="closeCallback" class="border-0">
              <Icon name="menu" />
            </Button>
          </div>
          <div class="flex items-center min-w-[85%]">
            <img v-if="appLogoUrl" :src="appLogoUrl" :alt="appName" class="h-8" />
            <span class="ml-2 text-xl">{{ appName }}</span>
          </div>
        </div>
        <!-- Items -->
        <div class="overflow-y-auto">
          <div class="sidebar-menu-options px-4">
            <!-- Buscador -->
            <div class="my-4">
              <InputTextWithIcon placeholder="Buscar..." :left-icon="{ name: 'search', color: 'text-secondary' }"
                input-class="pr-3! py-4!" size="large" @focus="toggleSearchModal()" />
            </div>

            <!-- Items -->
            <ul class="flex flex-col py-4 space-y-2">
              <li v-for="(item, index) in menu" :key="`menu-${index}`" class="">
                <!-- Item híbrido: el cuerpo navega y el chevron despliega -->
                <template v-if="item.children && item.children.length && item.path">
                  <div
                    class="w-full flex items-center rounded-lg text-base font-normal hover:bg-brand! hover:text-brand-contrast! transition-colors duration-200"
                    :class="{ 'bg-brand-active text-brand-contrast': isActive(item.path) }">
                    <RouterLink :to="item.path" class="flex flex-1 items-center p-3" @click="handleNavigate">
                      <img
                        v-if="item.iconUrl"
                        :src="item.iconUrl"
                        :alt="`Ícono de ${item.title}`"
                        class="h-5 w-5 object-contain"
                      />
                      <Icon v-else-if="item.icon" :name="item.icon" />
                      <span class="ml-3">{{ item.title }}</span>
                    </RouterLink>
                    <button type="button" class="flex items-center self-stretch px-3 rounded-lg"
                      :aria-label="`Desplegar ${item.title}`" :aria-expanded="isDropdownOpen(index)"
                      @click.prevent.stop="toggleDropdown(index)">
                      <Icon name="arrow_drop_down" class="transition-transform duration-200"
                        :class="{ 'rotate-180': isDropdownOpen(index) }" />
                    </button>
                  </div>
                  <transition name="fade">
                    <ul v-show="isDropdownOpen(index)" class="mt-1 ml-2 border-l border-surface!">
                      <li v-for="(child, cIdx) in item.children" :key="`submenu-${index}-${cIdx}`" class="">
                        <RouterLink :to="child.path"
                          class="flex items-center p-3 pl-4 text-sm font-normal rounded-lg hover:bg-brand! hover:text-brand-contrast! transition-colors duration-200"
                          :class="{ 'bg-brand-active text-brand-contrast': isActive(child.path) }"
                          @click="handleNavigate">
                          <img
                            v-if="child.iconUrl"
                            :src="child.iconUrl"
                            :alt="`Ícono de ${child.title}`"
                            class="h-5 w-5 object-contain"
                          />
                          <Icon v-else-if="child.icon" :name="child.icon" />
                          <span class="ml-3">{{ child.title }}</span>
                        </RouterLink>
                      </li>
                    </ul>
                  </transition>
                </template>

                <!-- Item con hijos (dropdown) -->
                <template v-else-if="item.children && item.children.length">
                  <button type="button"
                    class="w-full flex items-center justify-between p-3 text-base font-normal rounded-lg hover:bg-brand! hover:text-brand-contrast! transition-colors duration-200"
                    @click="toggleDropdown(index)">
                    <div class="flex items-center">
                      <img
                        v-if="item.iconUrl"
                        :src="item.iconUrl"
                        :alt="`Ícono de ${item.title}`"
                        class="h-5 w-5 object-contain"
                      />
                      <Icon v-else-if="item.icon" :name="item.icon" />
                      <span class="ml-3">{{ item.title }}</span>
                    </div>
                    <Icon name="arrow_drop_down" class="transition-transform duration-200"
                      :class="{ 'rotate-180': isDropdownOpen(index) }" />
                  </button>
                  <transition name="fade">
                    <ul v-show="isDropdownOpen(index)" class="mt-1 ml-2 border-l border-surface!">
                      <li v-for="(child, cIdx) in item.children" :key="`submenu-${index}-${cIdx}`" class="">
                        <RouterLink :to="child.path"
                          class="flex items-center p-3 pl-4 text-sm font-normal rounded-lg hover:bg-brand! hover:text-brand-contrast! transition-colors duration-200"
                          :class="{ 'bg-brand-active text-brand-contrast': isActive(child.path) }"
                          @click="handleNavigate">
                          <img
                            v-if="child.iconUrl"
                            :src="child.iconUrl"
                            :alt="`Ícono de ${child.title}`"
                            class="h-5 w-5 object-contain"
                          />
                          <Icon v-else-if="child.icon" :name="child.icon" />
                          <span class="ml-3">{{ child.title }}</span>
                        </RouterLink>
                      </li>
                    </ul>
                  </transition>
                </template>

                <!-- Item simple -->
                <template v-else>
                  <RouterLink :to="item.path"
                    class="flex items-center p-3 text-base font-normal rounded-lg hover:bg-brand! hover:text-brand-contrast! transition-colors duration-200"
                    :class="{ 'bg-brand-active text-brand-contrast': isActive(item.path) }" @click="handleNavigate">
                    <img
                      v-if="item.iconUrl"
                      :src="item.iconUrl"
                      :alt="`Ícono de ${item.title}`"
                      class="h-5 w-5 object-contain"
                    />
                    <Icon v-else-if="item.icon" :name="item.icon" />
                    <span class="ml-3">{{ item.title }}</span>
                  </RouterLink>
                </template>
              </li>
            </ul>
          </div>
        </div>
        <!-- Bottom -->
        <div class="mt-auto"></div>
      </div>
    </template>
  </Drawer>

  <!-- Modal de buscador -->
  <SearchModal v-model:visible="isSearchModalOpen" />
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { Button, Drawer } from 'primevue'
import SearchModal from '@/shared/components/SearchModal.vue'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'

import { useRootStore } from '@/shared/stores/root'
import { useAppBrand } from '@/shared/composables/useAppBrand'

const route = useRoute()
const rootStore = useRootStore()
const { appName, appLogoUrl } = useAppBrand()

const { toggleSideBarState, toggleSearchModal } = rootStore
const { isSideBarOpen, menu, isSearchModalOpen } = storeToRefs(rootStore)

const dropdownState = ref({})

function isActive(path) {
  return route.path === path
}

function isDropdownOpen(index) {
  return !!dropdownState.value[index]
}

function toggleDropdown(index) {
  dropdownState.value[index] = !dropdownState.value[index]
}

function handleNavigate() {
  toggleSideBarState()
}

function syncOpenStates() {
  if (!menu.value) return
  menu.value.forEach((item, idx) => {
    if (item?.children?.some((c) => c.path === route.path)) {
      dropdownState.value[idx] = true
    }
  })
}

onMounted(() => {
  syncOpenStates()
})

watch(
  () => route.path,
  () => {
    syncOpenStates()
  }
)
</script>
