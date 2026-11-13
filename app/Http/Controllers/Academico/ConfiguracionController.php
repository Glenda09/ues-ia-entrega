<?php

namespace App\Http\Controllers\Academico;

use App\Models\Academico\ConfiguracionAcademicaVersion;
use App\Models\Academico\EscalaCalificacion;
use App\Services\Academico\ConfiguracionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BASE-02: configuración general académica.
 */
class ConfiguracionController extends AcademicoController
{
    public function __construct(private ConfiguracionService $configuracion) {}

    public function mostrar(): JsonResponse
    {
        $fila = $this->configuracion->vigente();

        return $this->ok([
            'version' => (int) $fila->version,
            'valores' => $this->configuracion->valores(),
            'escalas' => EscalaCalificacion::query()->orderBy('codigo')->orderBy('version_politica')->get(),
            'catalogos' => $this->configuracion->catalogos(),
        ]);
    }

    public function actualizar(Request $request): JsonResponse
    {
        $datos = $this->validarEstricto($request, [
            'version' => 'required|integer|min:1',
            'motivo' => 'required|string|min:3|max:1000',
            'grupos' => 'required|array|min:1',
        ]);
        $fila = $this->configuracion->actualizar($datos['grupos'], (int) $datos['version'], $datos['motivo'], $this->actor($request)->idUsuario);

        return $this->ok([
            'version' => (int) $fila->version,
            'valores' => $this->configuracion->valores(),
        ], 'Configuración actualizada');
    }

    public function revisiones(Request $request): JsonResponse
    {
        return $this->paginado($request, ConfiguracionAcademicaVersion::query()->orderByDesc('revision'), fn ($r) => [
            'revision' => $r->revision,
            'motivo' => $r->motivo,
            'id_usuario' => $r->id_usuario,
            'instante' => $r->instante?->toIso8601String(),
        ]);
    }

    public function crearEscala(Request $request): JsonResponse
    {
        $datos = $this->validarEstricto($request, [
            'codigo' => 'required|string|max:50',
            'nombre' => 'required|string|max:200',
            'minimo' => 'required|numeric',
            'maximo' => 'required|numeric',
            'umbral_aprobacion' => 'required|numeric',
            'precision' => 'required|integer',
            'redondeo' => 'required|string',
            'ponderacion_total' => 'required|numeric',
            'ausencia' => 'required|string',
        ]);

        return $this->creado($this->configuracion->crearEscala($datos, $this->actor($request)->idUsuario), 'Escala creada');
    }
}
