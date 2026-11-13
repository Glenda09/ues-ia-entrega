export default [
  {
    path: '/home',
    name: 'landingPage.index',
    meta: {
      icon: '',
      title: 'Landing page',
      requiresAuth: false,
      permisos: [],
      show: false
    },
    component: () => import('@/modules/landingPage/views/LandingPageView.vue')
  }
];
