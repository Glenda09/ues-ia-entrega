<?php

namespace Tests\Feature\Protected\Seguridad;

use Tests\TestCase;
use Illuminate\Support\Str;

use App\Models\App\Estado;
use App\Models\App\AppModulo;
use App\Models\Seguridad\SeguridadMenuOpcion;
use App\Models\Seguridad\SeguridadPermiso;

class MenuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Asegurar datos base requeridos por FKs
        Estado::updateOrCreate(['codigo' => 'ACTIVO'], [
            'nombre_estado' => 'Activo',
            'label' => 'default',
            'color' => '#00AA00',
            'icono' => 'check',
            'activo' => true,
        ]);

        AppModulo::updateOrCreate(['codigo' => 'AUTENTICACION'], [
            'nombre' => 'Autenticación',
            'id_estado' => 'ACTIVO',
        ]);

        // Permisos para probar vinculación
        foreach (['menu_ver', 'menu_editar'] as $p) {
            SeguridadPermiso::updateOrCreate(['permiso' => $p], [
                'descripcion' => null,
                'activo' => true,
            ]);
        }
    }

    public function test_can_list_menu()
    {
        $this->withoutMiddleware();
        $root = SeguridadMenuOpcion::create([
            'id_modulo' => 'AUTENTICACION',
            'nombre' => 'Menú principal',
            'ruta' => '/inicio',
            'icono' => 'home',
            'orden' => 1,
            'id_estado' => 'ACTIVO',
        ]);

        $child = SeguridadMenuOpcion::create([
            'id_modulo' => 'AUTENTICACION',
            'id_opcion_padre' => $root->id_opcion,
            'nombre' => 'Reportes',
            'ruta' => '/inicio/reportes',
            'icono' => 'pie-chart',
            'orden' => 1,
            'id_estado' => 'ACTIVO',
        ]);

        $resp = $this->getJson(route('protected.seguridad.menu.index'));
        $resp->assertOk();

        $payload = $resp->json('data');
        $this->assertIsArray($payload);

        $menu = collect($payload)->firstWhere('id_opcion', $root->id_opcion);
        $this->assertNotNull($menu, 'La opción raíz creada debe estar presente en el listado.');
        $this->assertArrayHasKey('padre', $menu);
        $this->assertNull($menu['padre']);
        $this->assertArrayHasKey('childrens', $menu);
        $this->assertIsArray($menu['childrens']);
        $this->assertNotEmpty($menu['childrens']);

        $childMatch = collect($menu['childrens'])->firstWhere('id_opcion', $child->id_opcion);
        $this->assertNotNull($childMatch, 'La opción hija debe estar presente en la colección childrens.');
        $this->assertSame($root->id_opcion, $childMatch['id_opcion_padre']);
        $this->assertIsInt($menu['orden']);
    }

    public function test_list_filters_by_module()
    {
        $this->withoutMiddleware();

        AppModulo::updateOrCreate(['codigo' => 'REPORTES'], [
            'nombre' => 'Reportes',
            'id_estado' => 'ACTIVO',
        ]);

        $root = SeguridadMenuOpcion::create([
            'id_modulo' => 'AUTENTICACION',
            'nombre' => 'Menú principal',
            'ruta' => '/inicio',
            'icono' => 'home',
            'orden' => 1,
            'id_estado' => 'ACTIVO',
        ]);

        $matchingChild = SeguridadMenuOpcion::create([
            'id_modulo' => 'AUTENTICACION',
            'id_opcion_padre' => $root->id_opcion,
            'nombre' => 'Reportes',
            'ruta' => '/inicio/reportes',
            'orden' => 2,
            'id_estado' => 'ACTIVO',
        ]);

        $otherModuleRoot = SeguridadMenuOpcion::create([
            'id_modulo' => 'REPORTES',
            'nombre' => 'Administración',
            'ruta' => '/admin',
            'orden' => 1,
            'id_estado' => 'ACTIVO',
        ]);

        $resp = $this->getJson(route('protected.seguridad.menu.index', [
            'id_modulo' => 'AUTENTICACION',
        ]));

        $resp->assertOk();
        $data = collect($resp->json('data'));

        $this->assertNotEmpty($data);
        $this->assertTrue($data->every(fn ($item) => $item['id_modulo'] === 'AUTENTICACION'));

        $menu = $data->firstWhere('id_opcion', $root->id_opcion);
        $this->assertNotNull($menu);

        $childrens = collect($menu['childrens'] ?? []);
        $this->assertNotEmpty($childrens);
        $this->assertNotNull($childrens->firstWhere('id_opcion', $matchingChild->id_opcion));

        $this->assertNull($data->firstWhere('id_opcion', $otherModuleRoot->id_opcion));
    }

    public function test_head_request_returns_empty_body()
    {
        $this->withoutMiddleware();

        $response = $this->call('HEAD', route('protected.seguridad.menu.index'));
        $response->assertOk();
        $this->assertSame('', $response->getContent());
    }

    public function test_padres_endpoint_returns_desplegable_options()
    {
        $this->withoutMiddleware();

        $parent = SeguridadMenuOpcion::create([
            'id_modulo' => 'AUTENTICACION',
            'nombre' => 'Administración',
            'ruta' => null,
            'icono' => 'settings',
            'desplegable' => true,
            'orden' => 100,
            'id_estado' => 'ACTIVO',
        ]);

        SeguridadMenuOpcion::create([
            'id_modulo' => 'AUTENTICACION',
            'id_opcion_padre' => $parent->id_opcion,
            'nombre' => 'Subopción',
            'desplegable' => false,
            'orden' => 1,
            'id_estado' => 'ACTIVO',
        ]);

        $nonDesplegable = SeguridadMenuOpcion::create([
            'id_modulo' => 'AUTENTICACION',
            'nombre' => 'Detalle',
            'desplegable' => false,
            'orden' => 50,
            'id_estado' => 'ACTIVO',
        ]);

        $response = $this->getJson(route('protected.seguridad.menu.padres'));
        $response->assertOk();

        $payload = $response->json();
        $this->assertIsArray($payload);

        $match = collect($payload)->firstWhere('id_opcion', $parent->id_opcion);
        $this->assertNotNull($match, 'La opción desplegable debe estar presente en el resultado.');
        $this->assertSame('AUTENTICACION', $match['id_modulo']);
        $this->assertNull($match['id_opcion_padre']);
        $this->assertArrayHasKey('padre', $match);
        $this->assertNull($match['padre']);
        $this->assertSame('Administración', $match['nombre']);
        $this->assertSame(100, $match['orden']);
        $this->assertSame('ACTIVO', $match['id_estado']);

        $this->assertNull(collect($payload)->firstWhere('id_opcion', $nonDesplegable->id_opcion));
    }

    public function test_can_create_menu_option()
    {
        $this->withoutMiddleware();

        $payload = [
            'id_modulo' => 'AUTENTICACION',
            'nombre' => 'Inicio',
            'ruta' => '/inicio',
            'icono' => 'home',
            'orden' => 1,
            'permisos' => ['menu_ver', 'menu_editar']
        ];

        $resp = $this->postJson(route('protected.seguridad.menu.guardar'), $payload);
        $resp->assertCreated()
            ->assertJsonPath('nombre', 'Inicio');
        $this->assertDatabaseHas('seguridad_menu_opcion', ['nombre' => 'Inicio']);
        $createdId = $resp->json('id_opcion');
        $this->assertDatabaseHas('seguridad_menu_opcion_permiso', ['id_opcion' => $createdId, 'id_permiso' => 'menu_ver']);
    }

    public function test_can_view_menu_option()
    {
        $this->withoutMiddleware();
        $menu = SeguridadMenuOpcion::create([
            'id_opcion' => Str::uuid()->toString(),
            'id_modulo' => 'AUTENTICACION',
            'nombre' => 'Ver',
            'orden' => 1,
            'id_estado' => 'ACTIVO',
        ]);

        $resp = $this->getJson(route('protected.seguridad.menu.mostrar', $menu->id_opcion));
        $resp->assertOk()->assertJsonPath('id_opcion', $menu->id_opcion);
    }

    public function test_can_update_menu_option()
    {
        $this->withoutMiddleware();
        $menu = SeguridadMenuOpcion::create([
            'id_opcion' => Str::uuid()->toString(),
            'id_modulo' => 'AUTENTICACION',
            'nombre' => 'Editar',
            'orden' => 1,
            'id_estado' => 'ACTIVO',
        ]);

        $resp = $this->putJson(route('protected.seguridad.menu.actualizar', $menu->id_opcion), [
            'nombre' => 'Editada',
            'orden' => 2,
            'permisos' => ['menu_ver']
        ]);
        $resp->assertOk()->assertJsonPath('nombre', 'Editada');
        $this->assertDatabaseHas('seguridad_menu_opcion_permiso', ['id_opcion' => $menu->id_opcion, 'id_permiso' => 'menu_ver']);
        $this->assertDatabaseHas('seguridad_menu_opcion', ['id_opcion' => $menu->id_opcion, 'nombre' => 'Editada', 'orden' => 2]);
    }

    public function test_can_delete_menu_option()
    {
        $this->withoutMiddleware();
        $menu = SeguridadMenuOpcion::create([
            'id_opcion' => Str::uuid()->toString(),
            'id_modulo' => 'AUTENTICACION',
            'nombre' => 'Eliminar',
            'orden' => 1,
            'id_estado' => 'ACTIVO',
        ]);

        $resp = $this->deleteJson(route('protected.seguridad.menu.eliminar', $menu->id_opcion));
        $resp->assertOk();
        $this->assertSoftDeleted('seguridad_menu_opcion', ['id_opcion' => $menu->id_opcion]);
    }
}
