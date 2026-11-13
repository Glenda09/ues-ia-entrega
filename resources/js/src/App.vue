<template>
  <OverlayLoader />
  <RouterView id="main" tabindex="-1" />
  <footer v-if="marca.texto('texto_pie')" class="px-6 py-4 text-center text-sm text-secondary">{{ marca.texto('texto_pie') }}</footer>
  
</template>

<script setup>
import { usePersonalizacionStore } from '@/shared/stores/personalizacion'
import { onMounted, onBeforeUnmount } from 'vue'
import OverlayLoader from '@/shared/components/OverlayLoader.vue'
import { useRootStore } from '@/shared/stores/root'

const marca = usePersonalizacionStore()
const rootStore = useRootStore()

const KEY_K = 'k'

function onGlobalKeydown(e) {
  const isOpenShortcut = (e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === KEY_K
  if (isOpenShortcut) {
    e.preventDefault()
    if (!rootStore.isSearchModalOpen) rootStore.toggleSearchModal()
  }
}

onMounted(() => {
  window.addEventListener('keydown', onGlobalKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onGlobalKeydown)
})
</script>
