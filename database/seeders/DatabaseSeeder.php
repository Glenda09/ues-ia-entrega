<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    const ID_USUARIO_ADMINISTRADOR = '537b8b56-341c-4322-869a-de00d11cf10e';

    const ID_PERFIL_ADMINISTRADOR = 'super_admin';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $orderedSeeders = [
            EstadoSeeder::class,
            AppModuloSeeder::class,
            AuthApiKeySeeder::class,
            UsuarioAdministradorSeeder::class,
            PermisosAccionesSeeder::class,
            UsuarioAdminPerfilSeeder::class,
            UsuarioPruebasFrontendSeeder::class,
            MenuSeeder::class,
        ];

        // El usuario de pruebas de frontend tiene credenciales conocidas: nunca se siembra en producción.
        if (app()->environment('production')) {
            $orderedSeeders = array_values(array_diff($orderedSeeders, [UsuarioPruebasFrontendSeeder::class]));
        }

        try {
            $nextBatch = ((int) DB::table('seeders')->max('batch')) + 1;
        } catch (\Throwable $e) {
            $nextBatch = 1;
        }

        foreach ($orderedSeeders as $seederClass) {
            if (! $this->hasRun($seederClass)) {
                $this->call($seederClass);
                $this->markAsRun($seederClass, $nextBatch);
            }
        }

        // Catálogo académico idempotente: se sincroniza en cada ejecución (BASE-01).
        $this->call(AcademicoRbacSeeder::class);

        // Planes documentados de la UES, sin asignaciones ni historial estudiantil.
        $this->call(PensumsUesSeeder::class);

        // Datos de demostración con credenciales de prueba (solo entorno local).
        if (app()->environment('local') && env('ACADEMICO_DEMO_SEED', true)) {
            $this->call(AcademicoDemoSeeder::class);
        }
    }

    protected function hasRun(string $seederClass): bool
    {
        try {
            return DB::table('seeders')->where('seeder', $seederClass)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function markAsRun(string $seederClass, int $batch): void
    {
        DB::table('seeders')->insert([
            'seeder' => $seederClass,
            'batch' => $batch,
            'ran_at' => now(),
        ]);
    }
}
