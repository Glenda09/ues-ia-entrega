<?php

namespace App\Http\Controllers\Academico;

use App\Models\Academico\ConvocatoriaIngreso;
use App\Services\Academico\ConvocatoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * EXP-01: información pública de convocatorias abiertas.
 */
class PublicoController extends AcademicoController
{
    public function __construct(private ConvocatoriaService $convocatorias) {}

    public function convocatorias(Request $request): JsonResponse
    {
        $ahora = now('UTC');
        $q = ConvocatoriaIngreso::query()->where('estado', 'publicada')
            ->where('inicio', '<=', $ahora)->where('fin', '>', $ahora)
            ->orderBy('fin')->orderBy('id_convocatoria');

        return $this->paginado($request, $q, fn ($c) => $this->convocatorias->json($c, true));
    }

    public function convocatoria(ConvocatoriaIngreso $convocatoria): JsonResponse
    {
        abort_unless($convocatoria->estado === 'publicada' && $this->convocatorias->abierta($convocatoria), 404);

        return $this->ok($this->convocatorias->json($convocatoria, true));
    }
}
