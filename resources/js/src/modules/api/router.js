export default [
  {
    path: '/',
    meta: { base: true, show: false },
    component: () => import('@/shared/layouts/DefaultLayout.vue'),
    children: [
      {
        path: 'resources/apikeys',
        name: 'resources.apikeys',
        meta: {
          icon: 'key',
          title: 'API Keys',
          requiresAuth: true,
          permisos: ['menu_resources_apikeys'],
          show: true
        },
        component: () => import('@/modules/api/views/ApiKeysView.vue')
      }
    ]
  }
]
