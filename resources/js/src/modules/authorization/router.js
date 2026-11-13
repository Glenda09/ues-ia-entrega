export default [
  {
    path: '/',
    meta: { base: true, show: false },
    component: () => import('@/shared/layouts/DefaultLayout.vue'),
    children: [
      {
        path: 'auth/usuarios',
        name: 'auth.usuarios',
        meta: {
          icon: 'group',
          title: 'Usuarios',
          requiresAuth: true,
          permisos: ['menu_administracion_usuarios'],
          show: true
        },
        component: () => import('@/modules/authorization/views/UsersView.vue')
      },
      {
        path: 'seguridad/perfiles',
        name: 'seguridad.perfiles',
        meta: {
          icon: 'group',
          title: 'Perfiles',
          requiresAuth: true,
          permisos: ['menu_administracion_perfiles'],
          show: true
        },
        component: () => import('@/modules/authorization/views/ProfilesView.vue')
      },
      {
        path: 'seguridad/acciones',
        name: 'seguridad.acciones',
        meta: {
          icon: 'group',
          title: 'Acciones',
          requiresAuth: true,
          permisos: ['menu_administracion_acciones'],
          show: true
        },
        component: () => import('@/modules/authorization/views/ActionsView.vue')
      }
    ]
  }
]
