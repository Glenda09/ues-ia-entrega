import { createRouter, createWebHistory } from 'vue-router'
import systemRoutes from './system-routes.js'
import { permissionsGuard } from './Guards/permissionsGuard.js'

const routes = [...systemRoutes]

const router = createRouter({
  history: createWebHistory(import.meta.env.VITE_PUBLIC_BASE_URL),
  routes,
  scrollBehavior() {
    return { top: 0 }
  }
})

router.beforeEach((to, from, next) => {
  const hasMatch = to.matched && to.matched.length > 0
  if (!hasMatch && to.name !== 'not-found') {
    return next({ name: 'not-found', query: { from: from.path } })
  }

  const requiresAuth = to.matched.some((r) => r.meta?.requiresAuth)
  const token = localStorage.getItem('access_token') ?? null

  if (token && to.name === 'auth.login') {
    return next('/')
  }

  if (requiresAuth) {
    if (!token) {
      return next({ name: 'auth.login', query: { redirect: to.fullPath } })
    }
  }

  next()
})

// Verificar permisos después de validar existencia de ruta y auth básico
router.beforeEach(permissionsGuard)

export default router
