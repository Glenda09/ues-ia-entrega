<template>
  <div v-bind="$attrs">
    <NavBar />
    <div class="flex flex-row">
      <SideBar />
      <div class="w-full min-h-screen">
        <router-view class="m-12" />
      </div>
    </div>
  </div>
</template>

<script setup>
import NavBar from '@/shared/components/NavBar.vue'
import SideBar from '@/shared/components/SideBar.vue'

defineOptions({ inheritAttrs: false })
</script>
