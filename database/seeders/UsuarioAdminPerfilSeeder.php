<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\Auth\AuthUsuario;
use App\Models\Auth\AuthUsuarioPerfil;

class UsuarioAdminPerfilSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Asegura que exista el usuario admin
        $usuario = AuthUsuario::where('email', 'admin@example.com')->first();
        if (! $usuario) {
            return; // Si no existe, no puede asignar perfil aún
        }

        // Asigna/actualiza el perfil de super administrador de forma idempotente
        AuthUsuarioPerfil::updateOrCreate(
            [
                'id_usuario' => $usuario->id_usuario,
                'codigo_perfil' => DatabaseSeeder::ID_PERFIL_ADMINISTRADOR,
            ],
            [
                'creado_por' => $usuario->id_usuario,
            ]
        );
    }
}
