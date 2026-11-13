<?php

/**
 * Menú académico. Cada opción se muestra si el usuario tiene alguno de sus permisos.
 */
return [
    [
        'id_modulo' => 'ACADEMICO',
        'id_opcion_padre' => null,
        'nombre' => 'Administración académica',
        'ruta' => null,
        'icono' => 'account_balance',
        'desplegable' => true,
        'orden' => 10,
        'childrens' => [
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Configuración', 'ruta' => '/academico/configuracion', 'icono' => 'tune', 'desplegable' => false, 'orden' => 1, 'permisos' => ['academico_configuracion_ver']],
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Docentes', 'ruta' => '/academico/administracion/docentes', 'icono' => 'school', 'desplegable' => false, 'orden' => 2, 'permisos' => ['academico_docente_catalogo_gestionar']],
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Carreras', 'ruta' => '/academico/administracion/carreras', 'icono' => 'workspaces', 'desplegable' => false, 'orden' => 3, 'permisos' => ['academico_catalogo_ver']],
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Materias', 'ruta' => '/academico/administracion/materias', 'icono' => 'menu_book', 'desplegable' => false, 'orden' => 4, 'permisos' => ['academico_catalogo_ver']],
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Sedes y facultades', 'ruta' => '/academico/administracion/sedes', 'icono' => 'location_city', 'desplegable' => false, 'orden' => 5, 'permisos' => ['academico_catalogo_ver']],
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Ciclos institucionales', 'ruta' => '/academico/administracion/ciclos', 'icono' => 'event_note', 'desplegable' => false, 'orden' => 6, 'permisos' => ['academico_ciclo_institucional_ver']],
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Autoridad documental', 'ruta' => '/academico/autoridad', 'icono' => 'verified', 'desplegable' => false, 'orden' => 7, 'permisos' => ['academico_documento_autoridad_asignar']],
        ],
    ],
    [
        'id_modulo' => 'ACADEMICO',
        'id_opcion_padre' => null,
        'nombre' => 'Gestión de carreras',
        'ruta' => null,
        'icono' => 'calendar_month',
        'desplegable' => true,
        'orden' => 11,
        'childrens' => [
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Mis carreras', 'ruta' => '/academico/gestion-carreras', 'icono' => 'calendar_month', 'desplegable' => false, 'orden' => 1, 'permisos' => ['academico_ciclo_ver', 'academico_convocatoria_ver']],
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Actas', 'ruta' => '/academico/actas', 'icono' => 'fact_check', 'desplegable' => false, 'orden' => 2, 'permisos' => ['academico_acta_ver']],
        ],
    ],
    [
        'id_modulo' => 'ACADEMICO',
        'id_opcion_padre' => null,
        'nombre' => 'Expediente estudiantil',
        'ruta' => null,
        'icono' => 'folder_shared',
        'desplegable' => true,
        'orden' => 12,
        'childrens' => [
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Solicitudes de ingreso', 'ruta' => '/academico/solicitudes', 'icono' => 'inbox', 'desplegable' => false, 'orden' => 1, 'permisos' => ['academico_solicitud_ver']],
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Expedientes', 'ruta' => '/academico/expedientes', 'icono' => 'folder_shared', 'desplegable' => false, 'orden' => 2, 'permisos' => ['academico_expediente_ver']],
        ],
    ],
    [
        'id_modulo' => 'ACADEMICO',
        'id_opcion_padre' => null,
        'nombre' => 'Docencia',
        'ruta' => '/docente/grupos',
        'icono' => 'co_present',
        'desplegable' => false,
        'orden' => 13,
        'permisos' => ['academico_mi_grupo_ver'],
    ],
    [
        'id_modulo' => 'ACADEMICO',
        'id_opcion_padre' => null,
        'nombre' => 'Mi portal',
        'ruta' => null,
        'icono' => 'badge',
        'desplegable' => true,
        'orden' => 14,
        'childrens' => [
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Inicio estudiante', 'ruta' => '/estudiante', 'icono' => 'badge', 'desplegable' => false, 'orden' => 1, 'permisos' => ['academico_mi_perfil_ver']],
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Mi avance', 'ruta' => '/estudiante/avance', 'icono' => 'trending_up', 'desplegable' => false, 'orden' => 2, 'permisos' => ['academico_mi_avance_ver']],
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Inscripción', 'ruta' => '/estudiante/inscripcion', 'icono' => 'how_to_reg', 'desplegable' => false, 'orden' => 3, 'permisos' => ['academico_mi_oferta_ver']],
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Mis inscripciones', 'ruta' => '/estudiante/mis-inscripciones', 'icono' => 'list_alt', 'desplegable' => false, 'orden' => 4, 'permisos' => ['academico_mi_inscripcion_ver']],
            ['id_modulo' => 'ACADEMICO', 'nombre' => 'Asistente académico', 'ruta' => '/estudiante/asistente', 'icono' => 'smart_toy', 'desplegable' => false, 'orden' => 5, 'permisos' => ['academico_mi_asistente_usar']],
        ],
    ],
    [
        'id_modulo' => 'ACADEMICO',
        'id_opcion_padre' => null,
        'nombre' => 'Mis solicitudes',
        'ruta' => '/admision/mis-solicitudes',
        'icono' => 'assignment',
        'desplegable' => false,
        'orden' => 15,
        'permisos' => ['academico_mi_solicitud_gestionar'],
    ],
];
