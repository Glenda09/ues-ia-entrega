<?php

namespace App\Services\Academico;

/**
 * Matriz fija de estados del estudiante (D06 / EXP-03).
 */
final class ExpedienteEstados
{
    public const ACTIVO = 'ACTIVO';

    public const INACTIVO = 'INACTIVO';

    public const EGRESADO = 'EGRESADO';

    public const RETIRADO = 'RETIRADO';

    public const ETIQUETAS = [
        self::ACTIVO => 'Activo',
        self::INACTIVO => 'Inactivo',
        self::EGRESADO => 'Egresado',
        self::RETIRADO => 'Retirado',
    ];

    /** @var array<string, array<int, string>> */
    public const MATRIZ = [
        self::ACTIVO => [self::INACTIVO, self::EGRESADO, self::RETIRADO],
        self::INACTIVO => [self::ACTIVO],
        self::EGRESADO => [self::ACTIVO, self::INACTIVO, self::RETIRADO],
        self::RETIRADO => [self::ACTIVO, self::INACTIVO],
    ];

    public static function permitida(string $origen, string $destino): bool
    {
        return in_array($destino, self::MATRIZ[$origen] ?? [], true);
    }
}
