<template>
  <div class="flex flex-col mt-32">
    <img v-if="appLogoUrl" :src="appLogoUrl" :alt="appName" class="w-32 h-32 mb-4 mx-auto" />
    <h1 class="font-bold text-4xl text-center mx-auto">¡Bienvenido {{ usuario?.username }}!</h1>
  </div>
</template>

<script setup>
import { useAuthStore } from '@/shared/stores/auth'
import { storeToRefs } from 'pinia'
import { useAppBrand } from '@/shared/composables/useAppBrand'

const authStore = useAuthStore()
const { usuario } = storeToRefs(authStore)

const { appName, appLogoUrl } = useAppBrand()
</script>
