<template>
  <transition name="fade">
    <div
      v-if="isSearchModalOpen"
      class="fixed inset-0 z-40 bg-overlay backdrop-blur-xs transition-all duration-300"
      @click="toggleSearchModal"
    ></div>
  </transition>

  <Dialog
    v-model:visible="isSearchModalOpen"
    :modal="false"
    :draggable="false"
    :showHeader="false"
    pt:root="w-full lg:!w-7/12 z-50 bg-surface-card border-0 min-h-[50%] mx-4 lg:!mx-0"
  >
    <div class="my-4 relative">
      <InputTextWithIcon
        v-model="debounce.input.value"
        ref="modalSearchInput"
        placeholder="Buscar..."
        :left-icon="{ name: 'search', color: 'text-secondary' }"
        input-class="pr-3! py-4!"
        @focus="ensureOpen()"
        @keydown.down.prevent="onArrowDown"
        @keydown.up.prevent="onArrowUp"
        @keydown.enter.prevent="onEnter"
      />
    </div>

    <!-- Resultados de búsqueda -->
    <div v-if="!hasMenuResults && !hasOtherResults" class="py-6 text-center">
      <img src="@/shared/assets/images/no-search.png" alt="No results" class="mx-auto my-8" />
      <p v-if="resultsNotFound" class="text-sm text-secondary">No se encontraron resultados</p>
    </div>

    <div v-else>
      <!-- Accesos de menú -->
      <div v-if="hasMenuResults">
        <p class="text-sm text-secondary mb-2">Accesos de menú:</p>
        <ul class="list-none m-0 p-0">
          <li
            v-for="(item, idx) in menuResults"
            :key="`menu-${idx}-${item.path}`"
            class="mb-2 rounded-md border-2 transition-colors"
            :class="{
              'border-secondary bg-brand-hover': isActive('menu', idx),
              '!border-secondary-100 hover:bg-brand-hover': !isActive('menu', idx)
            }"
          >
            <RouterLink
              :to="item.path"
              class="flex items-center px-4 py-2 text-sm text-text"
              @click="toggleSearchModal"
            >
              <Icon v-if="item.icon" :name="item.icon" class="text-secondary" />
              <span class="ml-3">{{ item.title }}</span>
            </RouterLink>
          </li>
        </ul>
      </div>

      <!-- Otros resultados -->
      <div v-if="hasOtherResults">
        <Divider v-if="hasMenuResults" class="my-4 border-1 border-secondary-100" />
        <p class="text-sm text-secondary mb-2">Otros resultados:</p>
        <ul class="list-none m-0 p-0">
          <li
            v-for="(item, idx) in otherResults"
            :key="`other-${idx}-${item.path}`"
            class="mb-2 rounded-md border-2 transition-colors"
            :class="{
              'border-secondary bg-brand-hover': isActive('others', idx),
              'border-secondary-100 hover:bg-brand-hover': !isActive('others', idx)
            }"
          >
            <RouterLink
              :to="item.path"
              class="flex items-center px-4 py-2 text-sm text-text"
              @click="toggleSearchModal"
            >
              <span>{{ item.title }}</span>
            </RouterLink>
          </li>
        </ul>
      </div>
    </div>
  </Dialog>
</template>

<script setup>
import { ref, watch, nextTick, computed } from 'vue'
import { storeToRefs } from 'pinia'
import { Dialog, Divider } from 'primevue'
import Icon from '@/shared/components/Icon.vue'
import { useDebounce } from '@/shared/composables/useDebounce'
import { useRouter } from 'vue-router'
import InputTextWithIcon from '@/shared/components/InputTextWithIcon.vue'

import { useRootStore } from '@/shared/stores/root'

const props = defineProps({
  visible: { type: Boolean, default: false }
})

const emit = defineEmits(['update:visible'])

const rootStore = useRootStore()
const router = useRouter()

const { toggleSearchModal, search } = rootStore
const { isSearchModalOpen, searchResults, resultsNotFound } = storeToRefs(rootStore)
const modalSearchInput = ref(null)

// Constantes para evitar números mágicos / strings sueltas
const DEBOUNCE_MS = 500
const KEY_ARROW_DOWN = 'ArrowDown'
const KEY_ARROW_UP = 'ArrowUp'
const KEY_ENTER = 'Enter'
const NOT_FOUND_INDEX = -1

function ensureOpen() {
  emit('update:visible', true)
}

const debounce = useDebounce('', DEBOUNCE_MS, (val) => search(val))

// Ordenar resultados por longitud del título (ascendente)
const menuResults = computed(() => {
  const list = searchResults.value?.menu ?? []
  return [...list].sort((a, b) => (a?.title?.length ?? 0) - (b?.title?.length ?? 0))
})

const otherResults = computed(() => {
  const list = searchResults.value?.others ?? []
  return [...list].sort((a, b) => (a?.title?.length ?? 0) - (b?.title?.length ?? 0))
})

const hasMenuResults = computed(() => menuResults.value.length > 0)
const hasOtherResults = computed(() => otherResults.value.length > 0)

// Lista combinada para navegación con teclado
const combinedResults = computed(() => [
  ...menuResults.value.map((item, index) => ({ section: 'menu', index, item })),
  ...otherResults.value.map((item, index) => ({ section: 'others', index, item }))
])

// Índice activo global en la lista combinada
const activeIndex = ref(NOT_FOUND_INDEX)

// Indicar si una fila está activa
function isActive(section, idx) {
  const entry = combinedResults.value[activeIndex.value]
  return !!entry && entry.section === section && entry.index === idx
}

// Resetear selección cuando cambian resultados o cuando se abre el modal
watch([combinedResults, isSearchModalOpen], ([list, open]) => {
  if (open && list.length > 0) {
    activeIndex.value = 0
  } else if (!open || list.length === 0) {
    activeIndex.value = NOT_FOUND_INDEX
  }
})

function onArrowDown(e) {
  if (e?.key && e.key !== KEY_ARROW_DOWN) return
  const total = combinedResults.value.length
  if (total === 0) return
  if (activeIndex.value === NOT_FOUND_INDEX) {
    activeIndex.value = 0
    return
  }
  activeIndex.value = (activeIndex.value + 1) % total
}

function onArrowUp(e) {
  if (e?.key && e.key !== KEY_ARROW_UP) return
  const total = combinedResults.value.length
  if (total === 0) return
  if (activeIndex.value === NOT_FOUND_INDEX) {
    activeIndex.value = 0
    return
  }
  activeIndex.value = (activeIndex.value - 1 + total) % total
}

function onEnter(e) {
  if (e?.key && e.key !== KEY_ENTER) return
  const entry = combinedResults.value[activeIndex.value]
  if (!entry) return
  const to = entry.item?.path
  if (to) {
    router.push(to)
    toggleSearchModal()
  }
}

watch(isSearchModalOpen, async (open) => {
  if (open) {
    await nextTick()
    // Limpiar búsqueda previa
    debounce.input.value = ''
    activeIndex.value = NOT_FOUND_INDEX

      // Enfocar input (usa focus() expuesto por InputTextWithIcon)
      const comp = modalSearchInput.value
      if (comp && typeof comp.focus === 'function') {
        comp.focus()
      } else {
        // Fallback robusto al input nativo
        const target = comp?.$el ?? comp
        if (target && typeof target.querySelector === 'function') {
          const input = target.querySelector('input')
          input && typeof input.focus === 'function' && input.focus()
        }
      }
  }
})
</script>
