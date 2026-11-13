<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\App\Estado;

class EstadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $estados = [
            [
                'codigo' => 'ACTIVO',
                'nombre_estado' => 'Activo',
                'label' => 'default,activo',
                'color' => '#15803D',
                'icono' => 'check_circle',
                'activo' => true,
            ],
            [
                'codigo' => 'INACTIVO',
                'nombre_estado' => 'Inactivo',
                'label' => 'default,inactivo',
                'color' => '#D1D5DB',
                'icono' => 'cancel',
                'activo' => true,
            ],
        ];

        foreach ($estados as $estado) {
            Estado::updateOrCreate(
                ['codigo' => $estado['codigo']],
                $estado
            );
        }
    }
}
