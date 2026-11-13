export default [{
  path: '/',
  component: () => import('@/shared/layouts/DefaultLayout.vue'),
  children: [{
    path: 'personalizacion',
    name: 'personalizacion.index',
    meta: { title: 'Personalización', icon: 'palette', requiresAuth: true, permisos: ['personalizacion_ver'], show: true },
    component: () => import('./views/PersonalizacionView.vue')
  }]
}]
