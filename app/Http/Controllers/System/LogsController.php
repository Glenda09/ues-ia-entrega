<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditJobsRepository;
use App\Services\Audit\RequestLogRepository;
use App\Services\Audit\SystemLogRepository;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LogsController extends Controller
{
    public function __construct(
        private RequestLogRepository $requestLogRepository,
        private SystemLogRepository $systemLogRepository,
        private AuditJobsRepository $auditJobsRepository
    ) {}

    /**
     * Consultar logs de requests con filtros
     */
    public function consultarRequestLogs(Request $request)
    {
        try {
            $query = $this->requestLogRepository->query();

            // Filtro por URI
            if ($request->filled('uri')) {
                $query->where('uri', 'like', '%' . $request->uri . '%');
            }

            // Filtro por type (método HTTP)
            if ($request->filled('type')) {
                $query->where('method', strtoupper($request->type));
            }

            // Filtro por fecha de creación (solo fecha, sin hora)
            if ($request->filled('creation_date')) {
                $fecha = Carbon::parse($request->creation_date)->format('Y-m-d');
                $fechaInicio = Carbon::parse($fecha)->startOfDay();
                $fechaFin = Carbon::parse($fecha)->endOfDay();

                $query->whereBetween('creation_date', [$fechaInicio, $fechaFin]);
            }

            // Ordenamiento (por defecto del más reciente al más antiguo)
            $ordenamiento = $request->get('order', 'desc');
            if (in_array(strtolower($ordenamiento), ['asc', 'desc'])) {
                $query->orderBy('creation_date', strtolower($ordenamiento));
            } else {
                $query->orderBy('creation_date', 'desc');
            }

            // Paginación
            $perPage = $request->get('per_page', 10);
            $perPage = min(max($perPage, 1), 100); // Límite entre 1 y 100

            $logs = $query->paginate($perPage);

            return $this->formatearRespuestaExitosa($logs, 'Logs consultados exitosamente');
        } catch (\Exception $e) {
            return $this->formatearRespuestaError(
                'Error al consultar logs',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Formatear respuesta exitosa
     */
    private function formatearRespuestaExitosa($data, string $message = 'Logs consultados exitosamente')
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    /**
     * Formatear respuesta de error
     */
    private function formatearRespuestaError($mensaje, $detalle = null, $codigo = 400)
    {
        $respuesta = [
            'success' => false,
            'message' => $mensaje
        ];

        if ($detalle && config('app.debug')) {
            $respuesta['error'] = $detalle;
        }

        return response()->json($respuesta, $codigo);
    }

    /**
     * Consultar logs del sistema con filtros
     * Filtros: level, channel, datetime (fecha)
     */
    public function consultarSystemLogs(Request $request)
    {
        try {
            $query = $this->systemLogRepository->query();

            // Filtro por nivel (asegurar comparación numérica)
            if ($request->filled('level')) {
                $nivel = is_numeric($request->level) ? (int) $request->level : $request->level;
                $query->where('level', $nivel);
            }

            // Filtro por canal
            if ($request->filled('channel')) {
                $query->where('channel', $request->channel);
            }

            // Filtro por fecha (solo fecha, sin hora)
            if ($request->filled('datetime')) {
                $fecha = Carbon::parse($request->datetime)->format('Y-m-d');
                $fechaInicio = Carbon::parse($fecha)->startOfDay();
                $fechaFin = Carbon::parse($fecha)->endOfDay();

                $query->whereBetween('datetime', [$fechaInicio, $fechaFin]);
            }

            // Ordenamiento por fecha
            $ordenamiento = $request->get('order', 'desc');
            if (in_array(strtolower($ordenamiento), ['asc', 'desc'])) {
                $query->orderBy('datetime', strtolower($ordenamiento));
            } else {
                $query->orderBy('datetime', 'desc');
            }

            // Paginación
            $perPage = $request->get('per_page', 10);
            $perPage = min(max((int)$perPage, 1), 100);

            $logs = $query->paginate($perPage);

            return $this->formatearRespuestaExitosa($logs, 'Logs consultados exitosamente');
        } catch (\Exception $e) {
            return $this->formatearRespuestaError(
                'Error al consultar system logs',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Consultar jobs en cola con filtros
     * Filtros: queue, created_at (fecha)
     */
    public function consultarJobs(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $perPage = min(max((int) $perPage, 1), 100);

            $jobs = $this->auditJobsRepository->paginate(
                [
                    'queue' => $request->get('queue'),
                    'created_at' => $request->get('created_at'),
                ],
                $perPage,
                (string) $request->get('order', 'desc')
            );

            return $this->formatearRespuestaExitosa($jobs, 'Jobs consultados exitosamente');
        } catch (\Exception $e) {
            return $this->formatearRespuestaError(
                'Error al consultar jobs',
                $e->getMessage(),
                500
            );
        }
    }
}
