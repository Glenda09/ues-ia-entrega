/**
 * Rutas Vue del módulo académico. Cada ruta tiene su equivalente MVC en routes/web.php
 * para que un enlace directo o una recarga abra la vista correcta.
 */
const v = (nombre) => () => import(`@/modules/academico/views/${nombre}.vue`)
const ruta = (path, name, vista, title, permisos = [], extra = {}) => ({
  path, name, component: v(vista), meta: { title, requiresAuth: true, permisos, show: false, ...extra }
})

export default [
  {
    path: '/',
    meta: { base: true, show: false },
    component: () => import('@/shared/layouts/DefaultLayout.vue'),
    children: [
      ruta('academico/configuracion', 'academico.configuracion', 'ConfiguracionView', 'Configuración académica', ['academico_configuracion_ver']),
      ruta('academico/administracion/docentes', 'academico.docentes', 'DocentesView', 'Docentes', ['academico_docente_catalogo_ver']),
      ruta('academico/administracion/carreras', 'academico.carreras', 'CatalogoView', 'Carreras', ['academico_catalogo_ver'], { catalogos: ['carreras'] }),
      ruta('academico/administracion/materias', 'academico.materias', 'CatalogoView', 'Materias', ['academico_catalogo_ver'], { catalogos: ['materias'] }),
      ruta('academico/administracion/sedes', 'academico.sedes', 'CatalogoView', 'Sedes y facultades', ['academico_catalogo_ver'], { catalogos: ['sedes', 'facultades'] }),
      ruta('academico/administracion/carreras/:carrera/pensums', 'academico.pensums', 'PensumsView', 'Pensum', ['academico_pensum_ver']),
      ruta('academico/administracion/ciclos', 'academico.ciclos-institucionales', 'CiclosInstitucionalesView', 'Ciclos institucionales', ['academico_ciclo_institucional_ver']),
      ruta('academico/autoridad', 'academico.autoridad', 'AutoridadView', 'Autoridad documental', ['academico_documento_autoridad_asignar']),
      ruta('academico/gestion-carreras', 'academico.gestion-carreras', 'GestionCarrerasView', 'Gestión de carreras', ['academico_ciclo_ver', 'academico_convocatoria_ver']),
      ruta('academico/gestion-carreras/:carrera/ciclos/:ciclo', 'academico.ciclo', 'CicloView', 'Ciclo', ['academico_ciclo_ver']),
      ruta('academico/gestion-carreras/:carrera/convocatorias', 'academico.convocatorias', 'ConvocatoriasView', 'Convocatorias', ['academico_convocatoria_ver']),
      ruta('academico/actas', 'academico.actas', 'ActasView', 'Actas', ['academico_acta_ver']),
      ruta('academico/actas/:grupo', 'academico.acta', 'ActaView', 'Acta', ['academico_acta_ver']),
      ruta('academico/solicitudes', 'academico.solicitudes', 'SolicitudesView', 'Solicitudes de ingreso', ['academico_solicitud_ver']),
      ruta('academico/solicitudes/:solicitud', 'academico.solicitud', 'SolicitudView', 'Solicitud de ingreso', ['academico_solicitud_ver']),
      ruta('academico/expedientes', 'academico.expedientes', 'ExpedientesView', 'Expedientes', ['academico_expediente_ver']),
      ruta('academico/expedientes/:expediente', 'academico.expediente', 'ExpedienteView', 'Expediente', ['academico_expediente_ver']),
      ruta('docente/grupos', 'docente.grupos', 'DocenteGruposView', 'Mis grupos', ['academico_mi_grupo_ver']),
      ruta('docente/grupos/:grupo', 'docente.grupo', 'DocenteGrupoView', 'Grupo', ['academico_mi_grupo_ver']),
      ruta('docente/grupos/:grupo/notas', 'docente.notas', 'DocenteNotasView', 'Notas', ['academico_mi_grupo_ver']),
      ruta('estudiante', 'estudiante.inicio', 'EstudianteInicioView', 'Mi portal', ['academico_mi_perfil_ver']),
      ruta('estudiante/avance', 'estudiante.avance', 'EstudianteAvanceView', 'Mi avance', ['academico_mi_avance_ver']),
      ruta('estudiante/inscripcion', 'estudiante.inscripcion', 'EstudianteInscripcionView', 'Inscripción', ['academico_mi_oferta_ver']),
      ruta('estudiante/mis-inscripciones', 'estudiante.mis-inscripciones', 'EstudianteMisInscripcionesView', 'Mis inscripciones', ['academico_mi_inscripcion_ver']),
      ruta('estudiante/asistente', 'estudiante.asistente', 'EstudianteAsistenteView', 'Asistente académico', ['academico_mi_asistente_usar']),
      ruta('admision/mis-solicitudes', 'admision.mis-solicitudes', 'MisSolicitudesView', 'Mis solicitudes', ['academico_mi_solicitud_gestionar'])
    ]
  },
  // Páginas públicas (sin layout de administración).
  { path: '/admision', name: 'admision.index', component: v('AdmisionView'), meta: { title: 'Admisión', requiresAuth: false, permisos: [] } },
  { path: '/admision/registro', name: 'admision.registro', component: v('AdmisionRegistroView'), meta: { title: 'Registro de aspirante', requiresAuth: false, permisos: [] } },
  { path: '/admision/convocatorias/:convocatoria', name: 'admision.formulario', component: v('AdmisionFormularioView'), meta: { title: 'Solicitud de ingreso', requiresAuth: false, permisos: [] } },
  { path: '/admision/correccion', name: 'admision.correccion', component: v('CorreccionView'), meta: { title: 'Corrección de solicitud', requiresAuth: false, permisos: [] } },
  { path: '/asistente-aspirantes', name: 'asistente.aspirantes', component: v('AsistenteAspirantesView'), meta: { title: 'Asistente para aspirantes', requiresAuth: false, permisos: [] } },
  { path: '/carreras', name: 'carreras.index', component: v('CarrerasPublicasView'), meta: { title: 'Carreras', requiresAuth: false, permisos: [] } },
  { path: '/carreras/:carrera', name: 'carreras.detalle', component: v('CarreraPublicaView'), meta: { title: 'Carrera', requiresAuth: false, permisos: [] } },
  { path: '/estudiante/cambiar-password-inicial', name: 'estudiante.cambiar-password', component: v('CambiarPasswordInicialView'), meta: { title: 'Cambiar contraseña inicial', requiresAuth: true, permisos: [] } }
]
