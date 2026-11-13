export default [
  {
    path: '/',
    meta: { base: true, show: false },
    component: () => import('@/shared/layouts/DefaultLayout.vue'),
    children: [
      {
        path: '',
        name: 'dashboard.index',
        meta: {
          icon: 'dashboard',
          title: 'Dashboard',
          requiresAuth: true,
          permisos: [],
          show: true
        },
        component: () => import('@/modules/dashboard/views/DashboardView.vue')
      },
      {
        path: 'theme-preview',
        name: 'dashboard.themePreview',
        meta: {
          icon: 'palette',
          title: 'Previsualización de tema',
          requiresAuth: false,
          permisos: [],
          show: true
        },
        component: () => import('@/modules/dashboard/views/ThemePreviewView.vue')
      }
    ]
  }
]
