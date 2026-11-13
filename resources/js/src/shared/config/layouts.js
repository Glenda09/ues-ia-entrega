export const layouts = [
  {
    id: 'admin-sidebar',
    label: 'Sidebar compacto',
    description: 'Barra lateral colapsable sin navbar (por defecto)',
    icon: 'dock_to_left'
  },
  {
    id: 'drawer',
    label: 'Drawer sidebar',
    description: 'Sidebar como overlay deslizante',
    icon: 'left_panel_open'
  },
  {
    id: 'fixed-sidebar',
    label: 'Sidebar fijo',
    description: 'Columna lateral siempre visible a la izquierda',
    icon: 'view_sidebar'
  },
  {
    id: 'top-nav',
    label: 'Top navigation',
    description: 'Menú horizontal integrado en la barra superior',
    icon: 'web'
  }
]

export const defaultLayoutId = 'admin-sidebar'
