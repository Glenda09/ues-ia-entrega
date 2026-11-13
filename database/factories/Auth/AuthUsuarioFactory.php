<?php

namespace Database\Factories\Auth;

use App\Models\Auth\AuthUsuario;
use App\Models\Auth\AuthUsuarioPerfil;
use App\Models\Seguridad\SeguridadPerfil;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuthUsuarioFactory extends Factory
{
    protected $model = AuthUsuario::class;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
            'fecha_verificacion' => now(),
            'id_estado' => 'ACTIVO',
        ];
    }

    /**
     * Asigna automáticamente el perfil "usuario" (codigo 'user') al crear.
     */
    public function withPerfilUsuario(string $codigoPerfil = 'user'): static
    {
        return $this->afterCreating(function (AuthUsuario $usuario) use ($codigoPerfil) {
            AuthUsuarioPerfil::updateOrCreate(
                [
                    'id_usuario' => $usuario->id_usuario,
                    'codigo_perfil' => $codigoPerfil,
                    'creado_por' => '537b8b56-341c-4322-869a-de00d11cf10e',
                ],
                [
                    'creado_por' => '537b8b56-341c-4322-869a-de00d11cf10e',
                ]
            );
        });
    }
}
