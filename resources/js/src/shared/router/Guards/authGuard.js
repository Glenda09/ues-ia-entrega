export function authGuard(to, from, next) {
  const token = localStorage.getItem('access_token');
  if (!to.meta.requiresAuth) return next();
  if (!token) return next({ name: 'login', query: { redirect: to.fullPath } });
  next();
}
