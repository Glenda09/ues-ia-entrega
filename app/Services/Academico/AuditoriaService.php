<?php

namespace App\Services\Academico;

use App\Models\Academico\Auditoria;

/**
 * Auditoría de dominio académico (BASE-01).
 *
 * Registra actor, operación, entidad e instante con metadatos mínimos de negocio.
 * Nunca debe recibir payloads completos de solicitudes, expedientes, notas o conversaciones.
 */
class AuditoriaService
{
    /**
     * @param  string|null  $idUsuario  Actor; null para procesos públicos o de sistema.
     * @param  array<string, scalar|null|array>  $metadatos  Metadatos de negocio no sensibles.
     */
    public function registrar(?string $idUsuario, string $operacion, string $entidad, string|int|null $idEntidad = null, ?string $motivo = null, array $metadatos = []): Auditoria
    {
        return Auditoria::create([
            'id_usuario' => $idUsuario,
            'operacion' => $operacion,
            'entidad' => $entidad,
            'id_entidad' => $idEntidad !== null ? (string) $idEntidad : null,
            'motivo' => $motivo,
            'metadatos' => $metadatos ?: null,
            'instante' => now('UTC'),
        ]);
    }
}
