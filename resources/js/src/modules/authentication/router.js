export default [
  {
    path: '/configurar-doble-factor',
    name: 'auth.configurar-2fa',
    meta: { title: 'Configurar doble factor', requiresAuth: true, permisos: [], show: false },
    component: () => import('@/modules/authentication/views/ConfigurarDobleFactorView.vue')
  },
  {
    path: '/login',
    name: 'auth.login',
    meta: {
      title: 'Login',
      requiresAuth: false,
      permisos: [],
      show: false
    },
    component: () => import('@/modules/authentication/views/LoginView.vue')
  },
  {
    path: '/two-factor',
    name: 'auth.two-factor',
    meta: {
      icon: '',
      title: 'Verificación 2FA',
      requiresAuth: false,
      permisos: [],
      show: false
    },
    component: () => import('@/modules/authentication/views/TwoFactorView.vue')
  },
  {
    path: '/forgot-password',
    name: 'auth.forgot-password',
    meta: {
      icon: '',
      title: 'Recuperar cuenta',
      requiresAuth: false,
      permisos: [],
      show: false
    },
    component: () => import('@/modules/authentication/views/ForgotPasswordView.vue')
  },
];
