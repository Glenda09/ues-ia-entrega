<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\App\AppModulo;

class AppModuloSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modulos = [
            [
                'codigo' => 'ROOT',
                'nombre' => 'Root',
                'id_estado' => 'ACTIVO',
            ],
            [
                'codigo' => 'AUTORIZACION',
                'nombre' => 'Autorización',
                'id_estado' => 'ACTIVO',
            ],
            [
                'codigo' => 'AUTENTICACION',
                'nombre' => 'Autenticación',
                'id_estado' => 'ACTIVO',
            ],
            [
                'codigo' => 'RESOURCES',
                'nombre' => 'Recursos',
                'id_estado' => 'ACTIVO',
            ],
            [
                'codigo' => 'PERSONAL',
                'nombre' => 'Personal',
                'id_estado' => 'ACTIVO',
            ],
        ];

        foreach ($modulos as $m) {
            AppModulo::updateOrCreate(
                ['codigo' => $m['codigo']],
                ['nombre' => $m['nombre'], 'id_estado' => $m['id_estado']]
            );
        }
    }
}
