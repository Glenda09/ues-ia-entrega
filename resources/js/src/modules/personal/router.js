export default [
  {
    path: '/',
    meta: { base: true, show: false },
    component: () => import('@/shared/layouts/DefaultLayout.vue'),
    children: [
      {
        path: '/perfil',
        name: 'personal.perfil',
        meta: {
          icon: 'account_circle',
          title: 'Perfil',
          requiresAuth: true,
          permisos: [],
          show: true
        },
        component: () => import('@/modules/personal/views/ProfileView.vue')
      }
    ]
  }
]
