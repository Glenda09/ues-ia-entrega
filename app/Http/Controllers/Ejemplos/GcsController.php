<?php

namespace App\Http\Controllers\Ejemplos;

use App\Http\Controllers\Controller;
use App\Services\Almacenamiento\GcsService;
use Illuminate\Http\Request;

class GcsController extends Controller
{
    public function __construct(private GcsService $gcs)
    {
    }

    /**
     * Guarda un archivo subido vía multipart/form-data en GCS.
     * Campo esperado: 'archivo' (file). Opcional: 'ruta' como subcarpeta dentro del bucket/prefijo.
     */
    public function guardar(Request $request)
    {
        $validated = $request->validate([
            'archivo' => 'required|file',
            'ruta' => 'sometimes|string|nullable',
        ]);

        try {
            $file = $request->file('archivo');
            $subruta = $validated['ruta'] ?? null;

            $resultado = $this->gcs->guardar($file, $subruta);

            return response()->json(array_merge([
                'ok' => true,
                'mensaje' => 'Archivo subido correctamente',
            ], $resultado));
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Error al guardar en GCS',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descarga un archivo por nombre dentro del bucket/prefijo.
     */
    public function descargar(Request $request, string $nombre)
    {
        $bucketName = config('services.gcs.bucket');
        if (empty($bucketName)) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Falta configurar el bucket: env GCS_BUCKET',
            ], 500);
        }

        try {
            $contenido = $this->gcs->descargar($nombre);
            $info = [];
            try {
                $info = $this->gcs->metadata($nombre);
            } catch (\Throwable $e) {
                // Si falla metadata, continuamos con defaults
            }

            $contentType = $info['contentType'] ?? 'application/octet-stream';
            $filename = basename($nombre);

            return response($contenido, 200)
                ->header('Content-Type', $contentType)
                ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No se pudo descargar el archivo',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Elimina un archivo por nombre dentro del bucket/prefijo.
     */
    public function eliminar(Request $request, string $nombre)
    {
        $bucketName = config('services.gcs.bucket');
        if (empty($bucketName)) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Falta configurar el bucket: env GCS_BUCKET',
            ], 500);
        }

        try {
            $this->gcs->eliminar($nombre);
            return response()->json([
                'ok' => true,
                'mensaje' => 'Archivo eliminado correctamente',
                'objeto' => $nombre,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No se pudo eliminar el archivo',
                'error' => $e->getMessage(),
            ], 404);
        }
    }
}
