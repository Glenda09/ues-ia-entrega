<?php

return [
    [
        'id_grupo' => 'personalizacion',
        'nombre' => 'Personalización',
        'descripcion' => 'Identidad de la plataforma',
        'activo' => true,
        'acciones' => [
            [
                'id_grupo' => 'personalizacion',
                'accion' => 'Administrar marca blanca',
                'descripcion' => 'Consultar y editar la identidad de la plataforma',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'personalizacion_ver', 'descripcion' => 'Ver personalización', 'activo' => true],
                    ['permiso' => 'personalizacion_editar', 'descripcion' => 'Editar personalización', 'activo' => true],
                ],
            ],
        ],
    ],
    [
        'id_grupo' => self::ID_GRUPO_ACCIONES_PRINCIPAL,
        'nombre' => 'Administración',
        'descripcion' => 'Grupo de acciones para la administración del sistema',
        'activo' => true,
        'acciones' => [
            [
                'id_accion' => self::ID_ACCION_PRINCIPAL,
                'id_grupo' => self::ID_GRUPO_ACCIONES_PRINCIPAL,
                'accion' => 'Acceso completo',
                'descripcion' => 'Paquete que otorga el permiso root y acceso a todas las funcionalidades',
                'activo' => true,
                'permisos' => [
                    [
                        'permiso' => self::ID_PERMISO_ROOT,
                        'descripcion' => 'Permite acceder a todas las funcionalidades de la aplicación',
                        'activo' => true,
                    ],
                ],
            ],
        ],
    ],
    [
        'id_grupo' => 'seguridad',
        'nombre' => 'Seguridad',
        'descripcion' => 'Paquetes de permisos para administrar la seguridad',
        'activo' => true,
        'acciones' => [
            [
                'id_grupo' => 'seguridad',
                'accion' => 'Administrar permisos',
                'descripcion' => 'Listar, ver, crear, actualizar y eliminar permisos del catálogo',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'seguridad_permisos_index', 'descripcion' => 'Listar permisos', 'activo' => true],
                    ['permiso' => 'seguridad_permisos_mostrar', 'descripcion' => 'Ver permiso', 'activo' => true],
                    ['permiso' => 'seguridad_permisos_guardar', 'descripcion' => 'Crear permiso', 'activo' => true],
                    ['permiso' => 'seguridad_permisos_actualizar', 'descripcion' => 'Actualizar permiso', 'activo' => true],
                    ['permiso' => 'seguridad_permisos_eliminar', 'descripcion' => 'Eliminar permiso', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'seguridad',
                'accion' => 'Administrar grupos de acción',
                'descripcion' => 'CRUD y estado de los grupos que organizan el catálogo de acciones',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'seguridad_grupo_accion_index', 'descripcion' => 'Listar grupos de acción', 'activo' => true],
                    ['permiso' => 'seguridad_grupo_accion_mostrar', 'descripcion' => 'Ver grupo de acción', 'activo' => true],
                    ['permiso' => 'seguridad_grupo_accion_guardar', 'descripcion' => 'Crear grupo de acción', 'activo' => true],
                    ['permiso' => 'seguridad_grupo_accion_actualizar', 'descripcion' => 'Actualizar grupo de acción', 'activo' => true],
                    ['permiso' => 'seguridad_grupo_accion_eliminar', 'descripcion' => 'Eliminar grupo de acción', 'activo' => true],
                    ['permiso' => 'seguridad_grupo_accion_estado', 'descripcion' => 'Alternar estado de grupo de acción', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'seguridad',
                'accion' => 'Administrar acciones',
                'descripcion' => 'CRUD, estado y asignación de permisos de un paquete de acción',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'seguridad_accion_index', 'descripcion' => 'Listar acciones', 'activo' => true],
                    ['permiso' => 'seguridad_accion_mostrar', 'descripcion' => 'Ver acción', 'activo' => true],
                    ['permiso' => 'seguridad_accion_guardar', 'descripcion' => 'Crear acción', 'activo' => true],
                    ['permiso' => 'seguridad_accion_actualizar', 'descripcion' => 'Actualizar acción', 'activo' => true],
                    ['permiso' => 'seguridad_accion_eliminar', 'descripcion' => 'Eliminar acción', 'activo' => true],
                    ['permiso' => 'seguridad_accion_alternar_estado', 'descripcion' => 'Alternar estado de acción', 'activo' => true],
                    ['permiso' => 'seguridad_accion_permisos_asignar', 'descripcion' => 'Asignar permisos a acción', 'activo' => true],
                    ['permiso' => 'seguridad_accion_permisos_sincronizar', 'descripcion' => 'Sincronizar permisos de acción', 'activo' => true],
                    ['permiso' => 'seguridad_accion_permisos_remover', 'descripcion' => 'Remover permisos de acción', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'seguridad',
                'accion' => 'Administrar perfiles',
                'descripcion' => 'CRUD y estado de perfiles (roles) y sus acciones asociadas',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'seguridad_perfil_index', 'descripcion' => 'Listar perfiles', 'activo' => true],
                    ['permiso' => 'seguridad_perfil_mostrar', 'descripcion' => 'Ver perfil', 'activo' => true],
                    ['permiso' => 'seguridad_perfil_guardar', 'descripcion' => 'Crear perfil', 'activo' => true],
                    ['permiso' => 'seguridad_perfil_actualizar', 'descripcion' => 'Actualizar perfil', 'activo' => true],
                    ['permiso' => 'seguridad_perfil_eliminar', 'descripcion' => 'Eliminar perfil', 'activo' => true],
                    ['permiso' => 'seguridad_perfil_alternar_estado', 'descripcion' => 'Alternar estado de perfil', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'seguridad',
                'accion' => 'Administrar menú',
                'descripcion' => 'CRUD de opciones de menú del sistema',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'seguridad_menu_index', 'descripcion' => 'Listar opciones de menú', 'activo' => true],
                    ['permiso' => 'seguridad_menu_mostrar', 'descripcion' => 'Ver opción de menú', 'activo' => true],
                    ['permiso' => 'seguridad_menu_guardar', 'descripcion' => 'Crear opción de menú', 'activo' => true],
                    ['permiso' => 'seguridad_menu_actualizar', 'descripcion' => 'Actualizar opción de menú', 'activo' => true],
                    ['permiso' => 'seguridad_menu_eliminar', 'descripcion' => 'Eliminar opción de menú', 'activo' => true],
                ],
            ],
        ],
    ],
    [
        'id_grupo' => 'auth',
        'nombre' => 'Autenticación',
        'descripcion' => 'Paquetes de permisos para usuarios y API keys',
        'activo' => true,
        'acciones' => [
            [
                'id_grupo' => 'auth',
                'accion' => 'Administrar usuarios',
                'descripcion' => 'Listar, ver, crear, actualizar, estado, 2FA y restablecimiento de usuarios',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'auth_usuario_index', 'descripcion' => 'Listar usuarios', 'activo' => true],
                    ['permiso' => 'auth_usuario_mostrar', 'descripcion' => 'Ver usuario', 'activo' => true],
                    ['permiso' => 'auth_usuario_guardar', 'descripcion' => 'Crear usuario', 'activo' => true],
                    ['permiso' => 'auth_usuario_actualizar', 'descripcion' => 'Actualizar usuario', 'activo' => true],
                    ['permiso' => 'auth_usuario_restablecer_password', 'descripcion' => 'Restablecer contraseña de usuario', 'activo' => true],
                    ['permiso' => 'auth_usuario_alternar_estado', 'descripcion' => 'Alternar estado de usuario', 'activo' => true],
                    ['permiso' => 'auth_usuario_alternar_2fa', 'descripcion' => 'Alternar 2FA de usuario', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'auth',
                'accion' => 'Administrar API keys',
                'descripcion' => 'Listar, crear, estado y permisos de API keys',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'auth_api_key_index', 'descripcion' => 'Listar API keys', 'activo' => true],
                    ['permiso' => 'auth_api_key_guardar', 'descripcion' => 'Crear API key', 'activo' => true],
                    ['permiso' => 'auth_api_key_estado', 'descripcion' => 'Alternar estado de API key', 'activo' => true],
                    ['permiso' => 'auth_api_key_permisos_sincronizar', 'descripcion' => 'Sincronizar permisos de API key', 'activo' => true],
                ],
            ],
        ],
    ],
    [
        'id_grupo' => 'cuenta',
        'nombre' => 'Cuenta',
        'descripcion' => 'Información de la cuenta del usuario',
        'activo' => true,
        'acciones' => [
            [
                'id_grupo' => 'cuenta',
                'accion' => 'Ver información de la cuenta',
                'descripcion' => 'Permite consultar la información de la cuenta',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'cuenta_info', 'descripcion' => 'Ver información de la cuenta', 'activo' => true],
                ],
            ],
        ],
    ],
    [
        'id_grupo' => 'menu',
        'nombre' => 'Menú',
        'descripcion' => 'Accesos a las vistas que se muestran en el menú',
        'activo' => true,
        'acciones' => [
            [
                'id_grupo' => 'menu',
                'accion' => 'Dashboard',
                'descripcion' => 'Pantalla inicial de la aplicación donde se muestran estadísticas generales',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'menu_dashboard', 'descripcion' => 'Acceso al dashboard', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'menu',
                'accion' => 'Perfil',
                'descripcion' => 'Vista para que el usuario pueda ver y actualizar su perfil',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'menu_perfil', 'descripcion' => 'Acceso al perfil de usuario', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'menu',
                'accion' => 'Usuarios',
                'descripcion' => 'Vista para la administración de usuarios',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'menu_administracion_usuarios', 'descripcion' => 'Acceso a la gestión de usuarios', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'menu',
                'accion' => 'Perfiles',
                'descripcion' => 'Vista para la administración de perfiles',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'menu_administracion_perfiles', 'descripcion' => 'Acceso a la gestión de perfiles', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'menu',
                'accion' => 'Acciones',
                'descripcion' => 'Vista para la administración de acciones',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'menu_administracion_acciones', 'descripcion' => 'Acceso a la gestión de acciones', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'menu',
                'accion' => 'API Keys',
                'descripcion' => 'Vista para la administración de API keys',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'menu_resources_apikeys', 'descripcion' => 'Acceso a la gestión de API keys', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'menu',
                'accion' => 'Permisos',
                'descripcion' => 'Visualización de los permisos del sistema',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'menu_root_permisos', 'descripcion' => 'Acceso a la visualización de permisos', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'menu',
                'accion' => 'Menú',
                'descripcion' => 'Vista para la administración del menú de la aplicación',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'menu_root_menu', 'descripcion' => 'Acceso a la gestión del menú', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'menu',
                'accion' => 'Logs',
                'descripcion' => 'Vista para la revisión de logs del sistema',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'menu_root_logs', 'descripcion' => 'Acceso a la visualización de logs del sistema', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'menu',
                'accion' => 'Request Logs',
                'descripcion' => 'Vista para la revisión de logs sobre las peticiones de usuarios',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'menu_root_request_logs', 'descripcion' => 'Acceso a la visualización de logs sobre las peticiones de usuarios', 'activo' => true],
                ],
            ],
            [
                'id_grupo' => 'menu',
                'accion' => 'Colas de trabajo',
                'descripcion' => 'Vista para la revisión de las colas de trabajo',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'menu_root_jobs', 'descripcion' => 'Acceso a la visualización de logs sobre las colas de trabajo', 'activo' => true],
                ],
            ],
        ],
    ],
    [
        'id_grupo' => 'root',
        'nombre' => 'Root',
        'descripcion' => 'Acceso a la gestión de bajo nivel',
        'activo' => true,
        'acciones' => [
            [
                'id_grupo' => 'root',
                'accion' => 'Visualizar logs',
                'descripcion' => 'Acceso completo a logs del sistema',
                'activo' => true,
                'permisos' => [
                    ['permiso' => 'resources_request_logs', 'descripcion' => 'Acceso a los logs de las peticiones del usuario', 'activo' => true],
                    ['permiso' => 'resources_system_logs', 'descripcion' => 'Acceso a los logs del sistema', 'activo' => true],
                    ['permiso' => 'resources_jobs', 'descripcion' => 'Acceso a la lista de trabajos en cola pendientes', 'activo' => true],
                ],
            ],
        ],
    ],
];
