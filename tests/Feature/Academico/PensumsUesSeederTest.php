<?php

namespace Tests\Feature\Academico;

use App\Models\Academico\Materia;
use App\Models\Academico\Pensum;
use Database\Seeders\PensumsUesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\CreatesApplication;

class PensumsUesSeederTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    public function test_importa_cuatro_planes_documentados_sin_crear_historial_ni_usuarios(): void
    {
        $this->seed(PensumsUesSeeder::class);

        $this->assertDatabaseCount('carrera', 4);
        $this->assertDatabaseCount('pensum', 4);
        $this->assertDatabaseCount('materia', 180);
        $this->assertDatabaseCount('pensum_materia', 180);
        $this->assertSame(4, Pensum::query()->where('estado', 'borrador')->whereNull('vigente_desde')->whereNull('publicado_por')->count());
        foreach (['auth_usuario', 'estudiante', 'expediente', 'inscripcion', 'resultado_final', 'gestor_carrera', 'configuracion_academica'] as $tabla) {
            $this->assertDatabaseCount($tabla, 0);
        }
        $this->assertSame(4, DB::table('carrera_sede')->count());
        $this->assertDatabaseHas('materia', ['codigo' => 'UES-FMP-L70439-FSP274']);
        $this->assertDatabaseMissing('materia', ['codigo' => 'UES-FMP-L70439-FPS274']);
    }

    public function test_las_electivas_son_opciones_y_los_espacios_no_son_materias_ficticias(): void
    {
        $this->seed(PensumsUesSeeder::class);
        $this->assertSame(0, Materia::query()->where('codigo', 'like', '%ELECTIVA%')->count());
        $this->assertSame(8, DB::table('pensum_materia')->where('obligatoria', false)->count());
        $this->assertSame(172, DB::table('pensum_materia')->where('obligatoria', true)->count());

        // Se respeta la dependencia real, sin colisionar con BAD115 del seeder demo.
        $destino = Materia::query()->where('codigo', 'UES-FMP-I00515-BAD115')->firstOrFail();
        $requisito = Materia::query()->where('codigo', 'UES-FMP-I00515-ESD115')->firstOrFail();
        $this->assertDatabaseHas('pensum_requisito', ['id_materia' => $destino->id_materia, 'id_materia_requisito' => $requisito->id_materia]);
        // Las alternativas de 3 UV se conservan como tales.
        $laboral = Materia::query()->where('codigo', 'UES-FMP-L70802-DLA178')->firstOrFail();
        $this->assertDatabaseHas('pensum_materia', ['id_materia' => $laboral->id_materia, 'creditos' => 3, 'obligatoria' => false]);
    }

    public function test_repetir_la_carga_preserva_ids_ediciones_y_planes_publicados(): void
    {
        $this->seed(PensumsUesSeeder::class);
        $pensum = Pensum::query()->firstOrFail();
        $pensum->update(['estado' => 'publicado', 'version' => 7, 'vigente_desde' => '2026-01-01']);
        $materia = Materia::query()->firstOrFail();
        $materia->update(['descripcion' => 'Revisión posterior del gestor']);
        $antes = [];
        foreach (['carrera', 'materia', 'pensum', 'pensum_materia', 'pensum_requisito', 'carrera_sede'] as $tabla) {
            $antes[$tabla] = DB::table($tabla)->get()->toJson();
        }
        $this->seed(PensumsUesSeeder::class);
        foreach ($antes as $tabla => $contenido) {
            $this->assertSame($contenido, DB::table($tabla)->get()->toJson(), $tabla);
        }
    }

    public function test_conflictos_revierten_la_carga_completa_sin_sobrescribir_datos(): void
    {
        Materia::create(['codigo' => 'UES-FMP-I00515-FPC115', 'nombre' => 'Contenido ajeno']);
        try {
            $this->seed(PensumsUesSeeder::class);
            $this->fail('Debió detectar la colisión de código.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Materia existente incompatible', $e->getMessage());
        }
        $this->assertDatabaseCount('materia', 1);
        $this->assertDatabaseCount('carrera', 0);
        $this->assertDatabaseCount('facultad', 0);
        $this->assertDatabaseCount('sede', 0);
        $this->assertDatabaseCount('pensum', 0);
        $this->assertDatabaseHas('materia', ['nombre' => 'Contenido ajeno']);
    }
}
