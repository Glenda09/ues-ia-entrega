export default [
  {
    path: '/not-found',
    name: 'not-found',
    meta: {
      icon: '',
      title: 'Not Found',
      requiresAuth: false,
      permisos: [],
      show: false
    },
    component: () => import('./errors/NotFoundView.vue')
  },
]
