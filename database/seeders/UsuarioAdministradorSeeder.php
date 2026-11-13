<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioAdministradorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crea o actualiza el usuario administrador con ID fijo
        DB::table('auth_usuario')->updateOrInsert(
            [
                'id_usuario' => DatabaseSeeder::ID_USUARIO_ADMINISTRADOR,
            ],
            [
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password123'),
                'fecha_verificacion' => now(),
                'id_estado' => 'ACTIVO',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
