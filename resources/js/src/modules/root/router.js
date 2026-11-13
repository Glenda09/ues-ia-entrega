export default [
  {
    path: '/',
    meta: { base: true, show: false },
    component: () => import('@/shared/layouts/DefaultLayout.vue'),
    children: [
      {
        path: 'root/permisos',
        name: 'root.permisos',
        meta: {
          icon: 'security',
          title: 'Permisos',
          requiresAuth: true,
          permisos: ['menu_root_permisos'],
          show: true
        },
        component: () => import('@/modules/root/views/PermissionsView.vue')
      },
      {
        path: 'root/menu',
        name: 'root.menu',
        meta: {
          icon: 'flowdiagram',
          title: 'Menú',
          requiresAuth: true,
          permisos: ['menu_root_menu'],
          show: true
        },
        component: () => import('@/modules/root/views/MenuView.vue')
      },
      {
        path: 'root/logs',
        name: 'root.logs',
        meta: {
          icon: 'overview',
          title: 'Logs',
          requiresAuth: true,
          permisos: ['menu_root_logs'],
          show: true
        },
        component: () => import('@/modules/root/views/LogsView.vue')
      },
      {
        path: 'root/request-logs',
        name: 'root.request.logs',
        meta: {
          icon: 'overview',
          title: 'Request Logs',
          requiresAuth: true,
          permisos: ['menu_root_request_logs'],
          show: true
        },
        component: () => import('@/modules/root/views/RequestLogsView.vue')
      },
      {
        path: 'root/jobs',
        name: 'root.jobs',
        meta: {
          icon: 'overview',
          title: 'Colas de trabajo',
          requiresAuth: true,
          permisos: ['menu_root_jobs'],
          show: true
        },
        component: () => import('@/modules/root/views/JobsView.vue')
      }
    ]
  }
]
