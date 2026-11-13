// Configuración del tema del proyecto.
// Cambia estas líneas para adaptar la plantilla a un nuevo cliente/proyecto.
// También se puede controlar con variables de entorno en el archivo .env:
//   VITE_THEME_PALETTE=light-soft
//   VITE_THEME_LAYOUT=admin-sidebar
//   VITE_THEME_COLOR_MODE=auto   // 'light' | 'dark' | 'auto'

export const ACTIVE_PALETTE = import.meta.env.VITE_THEME_PALETTE || 'light-soft'
export const ACTIVE_LAYOUT = import.meta.env.VITE_THEME_LAYOUT || 'admin-sidebar'
export const DEFAULT_COLOR_MODE = import.meta.env.VITE_THEME_COLOR_MODE || 'auto'
