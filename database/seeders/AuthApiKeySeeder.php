<?php

namespace Database\Seeders;

use App\Models\Auth\AuthApiKey;
use App\Models\Auth\AuthApiKeyPermiso;
use App\Models\Seguridad\SeguridadPermiso;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AuthApiKeySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * `ROOT_API_KEY` es el secreto real (header `X-API-Key`); solo su hash SHA-256
     * se persiste. `api_key` es el identificador público: si el secreto ya es un
     * UUID se reutiliza, de lo contrario se deriva un UUID v5 determinístico de su
     * hash para mantener la fila idempotente entre ejecuciones del seeder.
     */
    public function run(): void
    {
        $rawSecret = (string) env('ROOT_API_KEY', '');
        $tokenHash = $rawSecret !== '' ? hash('sha256', $rawSecret) : null;

        if ($rawSecret !== '' && Str::isUuid($rawSecret)) {
            $publicId = $rawSecret;
        } elseif ($rawSecret !== '') {
            $publicId = (string) \Ramsey\Uuid\Uuid::uuid5(
                \Ramsey\Uuid\Uuid::NAMESPACE_OID,
                $tokenHash
            );
        } else {
            $publicId = (string) Str::uuid();
        }

        $model = AuthApiKey::firstOrCreate(
            ['cliente' => 'ROOT'],
            [
                'api_key' => $publicId,
                'token_hash' => $tokenHash,
                'id_estado' => 'ACTIVO',
                'created_at' => now(),
            ]
        );

        $model->fill([
            'token_hash' => $tokenHash,
            'id_estado' => 'ACTIVO',
        ])->save();

        SeguridadPermiso::updateOrCreate(
            ['permiso' => 'root'],
            [
                'descripcion' => 'Permite acceder a todas las funcionalidades de la aplicación',
                'activo' => true,
            ]
        );

        if (! empty($model->api_key)) {
            AuthApiKeyPermiso::updateOrCreate(
                [
                    'api_key' => $model->api_key,
                    'id_permiso' => 'root',
                ],
                [
                    'creado_por' => null,
                    'created_at' => now(),
                ]
            );
        }
    }
}
