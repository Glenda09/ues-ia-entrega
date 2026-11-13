<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class MvcRoutesTest extends TestCase
{
    /**
     * Verifica que cada ruta de interfaz entregue el shell Vue de la aplicación MVC.
     */
    public function test_all_frontend_routes_render_their_mvc_page(): void
    {
        $routes = [
            '/home' => 'landing',
            '/login' => 'login',
            '/two-factor' => 'two-factor',
            '/configurar-doble-factor' => 'configurar-doble-factor',
            '/forgot-password' => 'forgot-password',
            '/' => 'dashboard',
            '/theme-preview' => 'theme-preview',
            '/perfil' => 'profile',
            '/personalizacion' => 'personalizacion',
            '/auth/usuarios' => 'users',
            '/seguridad/perfiles' => 'profiles',
            '/seguridad/acciones' => 'actions',
            '/resources/apikeys' => 'api-keys',
            '/root/permisos' => 'permissions',
            '/root/menu' => 'menu',
            '/root/logs' => 'logs',
            '/root/request-logs' => 'request-logs',
            '/root/jobs' => 'jobs',
            '/not-found' => 'not-found',
        ];

        foreach ($routes as $uri => $_page) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('<div id="app"></div>', false);
        }
    }

    /**
     * Rutas MVC del módulo académico, incluidas las que tienen parámetros (enlace directo y recarga).
     */
    public function test_academic_routes_render_their_mvc_page(): void
    {
        foreach (['/academico/configuracion', '/academico/administracion/docentes', '/academico/administracion/carreras', '/academico/administracion/materias',
            '/academico/administracion/sedes', '/academico/administracion/carreras/1/pensums', '/academico/administracion/ciclos', '/academico/autoridad',
            '/academico/gestion-carreras', '/academico/gestion-carreras/1/ciclos/2', '/academico/gestion-carreras/1/convocatorias', '/academico/actas', '/academico/actas/3',
            '/academico/solicitudes', '/academico/solicitudes/4', '/academico/expedientes', '/academico/expedientes/5', '/docente/grupos', '/docente/grupos/6',
            '/docente/grupos/6/notas', '/estudiante', '/estudiante/avance', '/estudiante/inscripcion', '/estudiante/mis-inscripciones', '/estudiante/asistente',
            '/estudiante/cambiar-password-inicial', '/admision', '/admision/registro', '/admision/convocatorias/7', '/admision/correccion', '/admision/mis-solicitudes',
            '/asistente-aspirantes', '/carreras', '/carreras/8'] as $uri) {
            $this->get($uri)->assertOk()->assertSee('<div id="app"></div>', false);
        }
    }

    public function test_unknown_route_renders_not_found_page(): void
    {
        $this->get('/ruta-inexistente')
            ->assertOk()
            ->assertSee('<div id="app"></div>', false);
    }
}
