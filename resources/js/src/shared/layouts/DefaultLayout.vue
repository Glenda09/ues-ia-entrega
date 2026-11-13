<template>
  <component :is="layoutComponent" />
</template>

<script setup>
import { computed } from 'vue'
import { useThemeStore } from '@/shared/stores/theme'
import AdminSidebarLayout from './variants/AdminSidebarLayout.vue'
import DrawerLayout from './variants/DrawerLayout.vue'
import FixedSidebarLayout from './variants/FixedSidebarLayout.vue'
import TopNavLayout from './variants/TopNavLayout.vue'

const themeStore = useThemeStore()

const layoutComponents = {
  'admin-sidebar': AdminSidebarLayout,
  drawer: DrawerLayout,
  'fixed-sidebar': FixedSidebarLayout,
  'top-nav': TopNavLayout
}

const layoutComponent = computed(
  () => layoutComponents[themeStore.activeLayoutId] ?? AdminSidebarLayout
)
</script>
