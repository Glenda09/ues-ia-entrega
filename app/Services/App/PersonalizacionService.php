<?php

namespace App\Services\App;

use App\Models\App\AppPersonalizacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PersonalizacionService
{
    public const TEXTOS = ['nombre' => 60, 'titulo_pestana' => 80, 'descripcion' => 200, 'titulo_inicio' => 100, 'descripcion_inicio' => 300, 'texto_pie' => 100];

    public const ASSETS = ['logo', 'logo_oscuro', 'logo_vertical', 'favicon', 'fondo_acceso', 'fondo_acceso_oscuro'];

    public const DISCO = 'personalizacion';

    public function publica(): array
    {
        $fila = Schema::hasTable('app_personalizacion') ? AppPersonalizacion::find('plataforma') : null;
        $assets = [];
        foreach (self::ASSETS as $slot) {
            $guardado = $fila?->assets[$slot] ?? null;
            $assets[$slot] = $guardado ? route('public.personalizacion.asset', ['slot' => $slot, 'version' => $guardado['hash']], false) : null;
        }

        return [
            'textos' => array_replace(array_fill_keys(array_keys(self::TEXTOS), null), $fila?->textos ?? []),
            'assets' => $assets,
            'color_primario' => $fila?->color_primario,
        ];
    }

    public function guardar(array $datos): array
    {
        DB::transaction(function () use ($datos) {
            $fila = $this->filaBloqueada();
            if (array_key_exists('textos', $datos)) {
                $textos = $fila->textos ?? [];
                foreach ($datos['textos'] as $campo => $valor) {
                    $textos[$campo] = is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
                }
                $fila->textos = $textos;
            }
            if (array_key_exists('color_primario', $datos)) {
                $fila->color_primario = $datos['color_primario'] ? strtoupper($datos['color_primario']) : null;
            }
            $fila->save();
        });

        return $this->publica();
    }

    public function subir(string $slot, UploadedFile $archivo): array
    {
        $contenido = $archivo->get();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido);
        $extensiones = ['image/png' => 'png', 'image/webp' => 'webp', 'image/jpeg' => 'jpg'];
        $dimensiones = @getimagesizefromstring($contenido);
        if (! isset($extensiones[$mime]) || ! $dimensiones || $dimensiones[0] > 8192 || $dimensiones[1] > 8192) {
            throw ValidationException::withMessages(['archivo' => 'Usa una imagen PNG, WebP o JPEG de hasta 8192 píxeles por lado.']);
        }
        $hash = hash('sha256', $contenido);
        $ruta = $archivo->storeAs('plataforma', (string) \Illuminate\Support\Str::uuid().'.'.$extensiones[$mime], self::DISCO);
        $anterior = null;
        try {
            DB::transaction(function () use ($slot, $ruta, $mime, $hash, &$anterior) {
                $fila = $this->filaBloqueada();
                $assets = $fila->assets ?? [];
                $anterior = $assets[$slot]['ruta'] ?? null;
                $assets[$slot] = ['ruta' => $ruta, 'mime' => $mime, 'hash' => $hash];
                $fila->assets = $assets;
                $fila->save();
            });
        } catch (Throwable $error) {
            $this->eliminarArchivo($ruta);
            throw $error;
        }
        $this->eliminarArchivo($anterior);

        return $this->publica();
    }

    public function quitar(string $slot): array
    {
        $ruta = null;
        DB::transaction(function () use ($slot, &$ruta) {
            $fila = $this->filaBloqueada();
            $assets = $fila->assets ?? [];
            $ruta = $assets[$slot]['ruta'] ?? null;
            unset($assets[$slot]);
            $fila->assets = $assets;
            $fila->save();
        });
        $this->eliminarArchivo($ruta);

        return $this->publica();
    }

    public function imagen(string $slot, string $version): array
    {
        $asset = AppPersonalizacion::find('plataforma')?->assets[$slot] ?? null;
        abort_unless($asset && hash_equals($asset['hash'], $version), 404);
        abort_unless(Storage::disk(self::DISCO)->exists($asset['ruta']), 404);

        return ['contenido' => Storage::disk(self::DISCO)->get($asset['ruta']), 'mime' => $asset['mime']];
    }

    public static function contrasteBlanco(string $hex): float
    {
        $rgb = array_map(function ($posicion) use ($hex) {
            $canal = hexdec(substr($hex, $posicion, 2)) / 255;

            return $canal <= 0.04045 ? $canal / 12.92 : (($canal + 0.055) / 1.055) ** 2.4;
        }, [1, 3, 5]);

        return 1.05 / (0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2] + 0.05);
    }

    private function filaBloqueada(): AppPersonalizacion
    {
        AppPersonalizacion::query()->insertOrIgnore(['clave' => 'plataforma', 'created_at' => now(), 'updated_at' => now()]);

        return AppPersonalizacion::query()->lockForUpdate()->findOrFail('plataforma');
    }

    private function eliminarArchivo(?string $ruta): void
    {
        if ($ruta === null) {
            return;
        }
        try {
            Storage::disk(self::DISCO)->delete($ruta);
        } catch (Throwable $error) {
            Log::warning('No se pudo eliminar una imagen de personalización', ['ruta' => $ruta]);
        }
    }
}
