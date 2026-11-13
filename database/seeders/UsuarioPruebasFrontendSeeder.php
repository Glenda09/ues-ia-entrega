<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

use App\Models\Auth\AuthUsuario;
use App\Models\Auth\AuthUsuarioPerfil;

class UsuarioPruebasFrontendSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = '72e0f498-2428-4f5e-a690-68096edce7d3@yopmail.com';

        $usuario = AuthUsuario::firstOrCreate(
            ['email' => $email],
            [
                'username' => 'frontend.test',
                'password' => Hash::make('password123'),
                'fecha_verificacion' => now(),
                'id_estado' => 'ACTIVO',
                'creado_por' => DatabaseSeeder::ID_USUARIO_ADMINISTRADOR,
                'created_at' => now(),
            ]
        );

        // Asegura actualización de campos idempotentes
        $usuario->fill([
            'username' => 'frontend.test',
            'fecha_verificacion' => $usuario->fecha_verificacion ?? now(),
            'id_estado' => 'ACTIVO',
        ])->save();

        AuthUsuarioPerfil::updateOrCreate(
            [
                'id_usuario' => $usuario->id_usuario,
                'codigo_perfil' => DatabaseSeeder::ID_PERFIL_ADMINISTRADOR,
            ],
            [
                'creado_por' => DatabaseSeeder::ID_USUARIO_ADMINISTRADOR,
            ]
        );
    }
}
