<template>
  <div>
    <h1 class="text-h1 mb-4">Mi Perfil</h1>

    <Tabs value="0">
      <TabList>
        <Tab value="0" as="div" class="flex items-center gap-2">
          <Icon name="account_circle" />
          <span class="font-bold whitespace-nowrap">Usuario</span>
        </Tab>
        <Tab value="1" as="div" class="flex items-center gap-2">
          <Icon name="security" />
          <span class="font-bold whitespace-nowrap">Seguridad</span>
        </Tab>
      </TabList>

      <TabPanels>
        <TabPanel value="0" as="usuario" class="m-0">
          <p class="my-4 text-secondary">Usuario</p>
          <h5 class="text-h5 ml-4">{{ usuario?.username }}</h5>
          <hr class="border-secondary-100 my-4" />
          <p class="my-4 text-secondary">Correo</p>
          <h5 class="text-h5 ml-4">{{ usuario?.email }}</h5>
        </TabPanel>
        <TabPanel value="1" as="seguridad" class="m-0">
          <TwoFactorSecurityPanel />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
<script setup>
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/shared/stores/auth'

import { Tabs, TabList, Tab, TabPanels, TabPanel } from 'primevue'
import Icon from '@/shared/components/Icon.vue'
import TwoFactorSecurityPanel from '@/modules/personal/components/TwoFactorSecurityPanel.vue'

const authStore = useAuthStore()
const { usuario } = storeToRefs(authStore)
</script>
