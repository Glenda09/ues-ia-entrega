<template>
  <transition name="fade">
    <div v-if="isLoading" class="fixed inset-0 z-[9999]">
      <div class="absolute inset-0 bg-overlay backdrop-blur-sm"></div>
      <div class="absolute inset-0 flex items-center justify-center">
        <div class="flex flex-col items-center gap-3 text-brand-contrast">
          <svg
            class="animate-spin h-10 w-10 text-brand-contrast"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              class="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              stroke-width="4"
            />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
          </svg>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import { useLoadingStore } from '@/shared/stores/loading'

const loadingStore = useLoadingStore()
const { isLoading } = storeToRefs(loadingStore)
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
