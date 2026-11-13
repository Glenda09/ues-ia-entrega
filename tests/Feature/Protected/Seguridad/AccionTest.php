<?php

namespace Tests\Feature\Protected\Seguridad;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;

use App\Models\Seguridad\SeguridadPermiso;
use App\Models\Seguridad\SeguridadAccion;
use App\Models\Seguridad\SeguridadGrupoAccion;

class AccionTest extends TestCase
{
    use WithFaker;

    private string $grupoId = 'seguridad';

    protected function setUp(): void
    {
        parent::setUp();

        if (!SeguridadGrupoAccion::where('id_grupo', $this->grupoId)->exists()) {
            SeguridadGrupoAccion::create([
                'id_grupo' => $this->grupoId,
                'nombre' => 'Seguridad',
                'descripcion' => 'Grupo de pruebas',
                'activo' => true,
            ]);
        }

        foreach (['permiso_prueba_1', 'permiso_prueba_2', 'perm_asignar_1', 'perm_asignar_2', 'perm_sync_1'] as $permiso) {
            if (!SeguridadPermiso::where('permiso', $permiso)->exists()) {
                SeguridadPermiso::create(['permiso' => $permiso]);
            }
        }
    }

    public function test_can_list_actions()
    {
        $this->withoutMiddleware();
        $resp = $this->getJson(route('protected.seguridad.accion.index'));
        $resp->assertOk()->assertJsonStructure(['data']);
    }

    public function test_can_create_action_with_permissions()
    {
        $this->withoutMiddleware();

        $payload = [
            'id_grupo' => $this->grupoId,
            'accion' => 'Probar crear',
            'descripcion' => 'Desc',
            'activo' => true,
            'permisos' => ['permiso_prueba_1', 'permiso_prueba_2'],
        ];

        $resp = $this->postJson(route('protected.seguridad.accion.guardar'), $payload);
        $resp->assertCreated()->assertJsonPath('data.accion', 'Probar crear');
        $this->assertDatabaseHas('seguridad_accion', ['accion' => 'Probar crear']);
        $this->assertDatabaseHas('seguridad_permiso', ['permiso' => 'permiso_prueba_1']);
        $this->assertDatabaseHas('seguridad_permiso_accion', ['permiso' => 'permiso_prueba_1']);
    }

    public function test_can_view_action()
    {
        $this->withoutMiddleware();
        $accion = SeguridadAccion::create([
            'id_accion' => Str::uuid()->toString(),
            'id_grupo' => $this->grupoId,
            'accion' => 'Ver acción',
            'descripcion' => 'X',
            'activo' => true,
        ]);
        $resp = $this->getJson(route('protected.seguridad.accion.mostrar', $accion->id_accion));
        $resp->assertOk()->assertJsonPath('data.id_accion', $accion->id_accion);
    }

    public function test_can_update_action()
    {
        $this->withoutMiddleware();
        $accion = SeguridadAccion::create([
            'id_accion' => Str::uuid()->toString(),
            'id_grupo' => $this->grupoId,
            'accion' => 'Editar',
            'descripcion' => null,
            'activo' => true,
        ]);
        $resp = $this->putJson(route('protected.seguridad.accion.actualizar', $accion->id_accion), [
            'accion' => 'Editada',
            'descripcion' => 'Nueva desc',
        ]);
        $resp->assertOk()->assertJsonPath('data.accion', 'Editada');
        $this->assertDatabaseHas('seguridad_accion', ['id_accion' => $accion->id_accion, 'accion' => 'Editada']);
    }

    public function test_can_assign_and_remove_permissions()
    {
        $this->withoutMiddleware();
        $accion = SeguridadAccion::create([
            'id_accion' => Str::uuid()->toString(),
            'id_grupo' => $this->grupoId,
            'accion' => 'Asignar',
            'activo' => true,
        ]);

        $respAsignar = $this->postJson(route('protected.seguridad.accion.permisos.asignar', $accion->id_accion), [
            'permisos' => ['perm_asignar_1', 'perm_asignar_2'],
        ]);
        $respAsignar->assertOk();
        $this->assertDatabaseHas('seguridad_permiso_accion', ['id_accion' => $accion->id_accion, 'permiso' => 'perm_asignar_1']);

        $respSync = $this->putJson(route('protected.seguridad.accion.permisos.sincronizar', $accion->id_accion), [
            'permisos' => ['perm_sync_1'],
        ]);
        $respSync->assertOk();
        $this->assertDatabaseMissing('seguridad_permiso_accion', ['id_accion' => $accion->id_accion, 'permiso' => 'perm_asignar_2']);

        $respRem = $this->deleteJson(route('protected.seguridad.accion.permisos.remover', $accion->id_accion), [
            'permisos' => ['perm_sync_1'],
        ]);
        $respRem->assertOk();
        $this->assertDatabaseMissing('seguridad_permiso_accion', ['id_accion' => $accion->id_accion, 'permiso' => 'perm_sync_1']);
    }

    public function test_can_toggle_action_state()
    {
        $this->withoutMiddleware();
        $accion = SeguridadAccion::create([
            'id_accion' => Str::uuid()->toString(),
            'id_grupo' => $this->grupoId,
            'accion' => 'Estado',
            'activo' => true,
        ]);

        $resp = $this->putJson(route('protected.seguridad.accion.alternar-estado', $accion->id_accion));
        $resp->assertOk()->assertJson(['message' => 'Estado de la acción actualizado correctamente']);
        $this->assertDatabaseHas('seguridad_accion', ['id_accion' => $accion->id_accion, 'activo' => false]);
    }

    public function test_can_delete_action()
    {
        $this->withoutMiddleware();
        $accion = SeguridadAccion::create([
            'id_accion' => Str::uuid()->toString(),
            'id_grupo' => $this->grupoId,
            'accion' => 'Eliminar',
            'activo' => true,
        ]);
        $resp = $this->deleteJson(route('protected.seguridad.accion.eliminar', $accion->id_accion));
        $resp->assertNoContent();
        $this->assertDatabaseMissing('seguridad_accion', ['id_accion' => $accion->id_accion]);
    }
}
