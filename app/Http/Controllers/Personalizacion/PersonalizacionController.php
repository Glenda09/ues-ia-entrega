<?php

namespace App\Http\Controllers\Personalizacion;

use App\Http\Controllers\Controller;
use App\Services\App\PersonalizacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PersonalizacionController extends Controller
{
    public function __construct(private PersonalizacionService $servicio) {}

    public function mostrar(): JsonResponse
    {
        return response()->json(['data' => $this->servicio->publica()])->header('Cache-Control', 'no-store');
    }

    public function guardar(Request $request): JsonResponse
    {
        $reglas = [
            'textos' => ['sometimes', 'array:'.implode(',', array_keys(PersonalizacionService::TEXTOS))],
            'color_primario' => ['bail', 'sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/', function ($atributo, $valor, $fallar) {
                if (preg_match('/^#[0-9a-fA-F]{6}$/', $valor) && PersonalizacionService::contrasteBlanco($valor) < 4.5) {
                    $fallar('El color debe permitir leer texto blanco con contraste mínimo de 4.5:1.');
                }
            }],
        ];
        foreach (PersonalizacionService::TEXTOS as $campo => $maximo) {
            $reglas['textos.'.$campo] = ['sometimes', 'nullable', 'string', 'max:'.$maximo, 'regex:/^[^<>\p{Cc}]*$/u'];
        }
        $datos = $request->validate($reglas);

        return response()->json(['message' => 'Personalización guardada', 'data' => $this->servicio->guardar($datos)]);
    }

    public function subir(Request $request, string $slot): JsonResponse
    {
        abort_unless(in_array($slot, PersonalizacionService::ASSETS, true), 404);
        $maximo = $slot === 'favicon' ? 256 : (str_starts_with($slot, 'fondo_') ? 6144 : 1024);
        $request->validate(['archivo' => ['required', 'file', 'max:'.$maximo]]);

        return response()->json(['message' => 'Imagen guardada', 'data' => $this->servicio->subir($slot, $request->file('archivo'))]);
    }

    public function quitar(string $slot): JsonResponse
    {
        abort_unless(in_array($slot, PersonalizacionService::ASSETS, true), 404);

        return response()->json(['message' => 'Imagen retirada', 'data' => $this->servicio->quitar($slot)]);
    }

    public function imagen(string $slot, string $version): Response
    {
        $imagen = $this->servicio->imagen($slot, $version);

        return response($imagen['contenido'], 200, [
            'Content-Type' => $imagen['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
