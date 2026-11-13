<?php

/**
 * Catálogo RBAC académico: grupo `academico`, acciones (paquetes) con permisos atómicos
 * y perfiles funcionales que reciben cada acción (ARQUITECTURA.md).
 *
 * Cada acción declara `perfiles`: códigos de perfil que la reciben.
 */
return [
    'grupo' => ['id_grupo' => 'academico', 'nombre' => 'Académico', 'descripcion' => 'Plataforma de gestión universitaria'],
    'perfiles' => [
        'academico_admin' => ['Administrador académico', 'Catálogos universitarios, docentes, pensums, configuración y consulta institucional'],
        'academico_gestor_carrera' => ['Gestor de carrera', 'Ciclos, oferta, convocatorias y actas de carreras asignadas'],
        'academico_gestor_expediente' => ['Gestor de expediente', 'Solicitudes, expedientes y documentos de carreras asignadas'],
        'academico_docente' => ['Docente', 'Grupos asignados, padrón y notas'],
        'academico_estudiante' => ['Estudiante', 'Portal propio, avance, inscripción y asistente'],
        'academico_aspirante' => ['Aspirante', 'Borrador y solicitudes de ingreso propias'],
        'academico_autoridad_documental' => ['Máxima autoridad documental', 'Titular exclusivo del permiso de firma de documentos (D07)'],
    ],
    'acciones' => [
        [
            'accion' => 'Alcance institucional académico',
            'descripcion' => 'Consulta y gestión sobre todas las carreras',
            'perfiles' => ['academico_admin'],
            'permisos' => [
                'academico_alcance_institucional' => 'Alcance institucional sobre carreras',
            ],
        ],
        [
            'accion' => 'Configuración académica',
            'descripcion' => 'Parámetros generales, escalas y consumo de IA (BASE-02)',
            'perfiles' => ['academico_admin'],
            'permisos' => [
                'academico_configuracion_ver' => 'Ver configuración académica',
                'academico_configuracion_editar' => 'Editar configuración académica',
                'academico_ia_consumo_ver' => 'Ver consumo de IA',
                'academico_ia_tarifa_gestionar' => 'Gestionar tarifas de IA',
            ],
        ],
        [
            'accion' => 'Catálogo de docentes',
            'descripcion' => 'Alta y edición de docentes (GEN-01)',
            'perfiles' => ['academico_admin'],
            'permisos' => [
                'academico_docente_catalogo_ver' => 'Ver docentes',
                'academico_docente_catalogo_gestionar' => 'Gestionar docentes',
            ],
        ],
        [
            'accion' => 'Catálogos universitarios',
            'descripcion' => 'Carreras, materias, sedes, facultades, gestores y pensums (GEN-02/03)',
            'perfiles' => ['academico_admin'],
            'permisos' => [
                'academico_catalogo_ver' => 'Ver catálogos',
                'academico_catalogo_gestionar' => 'Gestionar catálogos',
                'academico_gestor_asignar' => 'Asignar gestores de carrera',
                'academico_pensum_ver' => 'Ver pensums',
                'academico_pensum_gestionar' => 'Gestionar pensums',
                'academico_ciclo_institucional_ver' => 'Ver ciclos institucionales (GEN-04)',
                'academico_documento_autoridad_asignar' => 'Asignar máxima autoridad documental',
            ],
        ],
        [
            'accion' => 'Consulta de carreras asignadas',
            'descripcion' => 'Datos básicos de carreras gestionadas',
            'perfiles' => ['academico_gestor_carrera', 'academico_gestor_expediente'],
            'permisos' => [
                'academico_carrera_asignada_ver' => 'Ver carreras asignadas',
            ],
        ],
        [
            'accion' => 'Gestión de carrera',
            'descripcion' => 'Ciclos, oferta y convocatorias de carreras asignadas (CAR-01..03)',
            'perfiles' => ['academico_gestor_carrera'],
            'permisos' => [
                'academico_ciclo_ver' => 'Ver ciclos de carrera',
                'academico_ciclo_gestionar' => 'Gestionar ciclos de carrera',
                'academico_oferta_ver' => 'Ver oferta',
                'academico_oferta_gestionar' => 'Gestionar oferta y grupos',
                'academico_convocatoria_ver' => 'Ver convocatorias',
                'academico_convocatoria_gestionar' => 'Gestionar convocatorias',
                'academico_catalogo_ver' => 'Ver catálogos',
                'academico_docente_catalogo_ver' => 'Ver docentes',
                'academico_pensum_ver' => 'Ver pensums',
            ],
        ],
        [
            'accion' => 'Actas de carrera',
            'descripcion' => 'Revisión y publicación de actas (DOC-02)',
            'perfiles' => ['academico_gestor_carrera'],
            'permisos' => [
                'academico_acta_ver' => 'Ver actas',
                'academico_acta_publicar' => 'Publicar actas',
            ],
        ],
        [
            'accion' => 'Fuentes institucionales de IA',
            'descripcion' => 'Revisión y publicación de fuentes del asistente de aspirantes (IAA-01)',
            'perfiles' => ['academico_admin', 'academico_gestor_carrera'],
            'permisos' => [
                'academico_fuente_ia_ver' => 'Ver fuentes de IA',
                'academico_fuente_ia_publicar' => 'Publicar fuentes de IA',
            ],
        ],
        [
            'accion' => 'Gestión de expedientes',
            'descripcion' => 'Solicitudes de ingreso, expedientes y documentos (EXP-02..04)',
            'perfiles' => ['academico_gestor_expediente'],
            'permisos' => [
                'academico_solicitud_ver' => 'Ver solicitudes de ingreso',
                'academico_solicitud_decidir' => 'Observar o aceptar solicitudes y revisar pagos',
                'academico_expediente_ver' => 'Ver expedientes',
                'academico_expediente_editar' => 'Editar datos de expediente',
                'academico_expediente_estado' => 'Cambiar estado del estudiante',
                'academico_documento_ver' => 'Ver documentos de expediente',
                'academico_documento_emitir' => 'Emitir documentos de expediente',
            ],
        ],
        [
            'accion' => 'Docencia',
            'descripcion' => 'Grupos asignados y notas (DOC-01)',
            'perfiles' => ['academico_docente'],
            'permisos' => [
                'academico_mi_grupo_ver' => 'Ver grupos asignados',
                'academico_mi_nota_gestionar' => 'Registrar notas',
            ],
        ],
        [
            'accion' => 'Portal estudiantil',
            'descripcion' => 'Perfil, avance, inscripción y asistente propios (EST, INS, IAE)',
            'perfiles' => ['academico_estudiante'],
            'permisos' => [
                'academico_mi_perfil_ver' => 'Ver perfil y calendario propio',
                'academico_mi_avance_ver' => 'Ver avance propio',
                'academico_mi_oferta_ver' => 'Ver oferta elegible propia',
                'academico_mi_inscripcion_ver' => 'Ver inscripciones propias',
                'academico_mi_inscripcion_gestionar' => 'Confirmar y retirar inscripciones propias',
                'academico_mi_asistente_usar' => 'Usar asistente académico personal',
            ],
        ],
        [
            'accion' => 'Solicitud de ingreso propia',
            'descripcion' => 'Borrador, anexos y envío de solicitudes (EXP-01)',
            'perfiles' => ['academico_aspirante'],
            'permisos' => [
                'academico_mi_solicitud_gestionar' => 'Gestionar solicitud de ingreso propia',
            ],
        ],
        [
            'accion' => 'Firma de documentos oficiales',
            'descripcion' => 'Permiso exclusivo de la máxima autoridad (D07). Solo un titular activo.',
            'perfiles' => ['academico_autoridad_documental'],
            'permisos' => [
                'academico_documento_autoridad' => 'Máxima autoridad documental (exclusivo)',
            ],
        ],
    ],
];
