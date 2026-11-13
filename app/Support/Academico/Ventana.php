<?php

namespace App\Support\Academico;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Regla compartida de plazos (BASE-01).
 *
 * Intervalos semiabiertos `[inicio, fin)`. Un recurso sin publicar o con plazos
 * faltantes queda `cerrado`. Sin fin de gracia, el fin efectivo es el fin regular.
 */
final class Ventana
{
    public const ANTICIPADO = 'anticipado';

    public const REGULAR = 'regular';

    public const GRACIA = 'gracia';

    public const CERRADO = 'cerrado';

    /**
     * Evalúa el estado de una ventana en un instante.
     */
    public static function evaluar(
        ?DateTimeInterface $inicio,
        ?DateTimeInterface $finRegular,
        ?DateTimeInterface $finGracia,
        bool $publicado,
        DateTimeInterface $instante,
    ): string {
        if (! $publicado || $inicio === null || $finRegular === null) {
            return self::CERRADO;
        }
        $t = CarbonImmutable::instance($instante)->utc();
        $ini = CarbonImmutable::instance($inicio)->utc();
        $reg = CarbonImmutable::instance($finRegular)->utc();
        $efectivo = $finGracia ? CarbonImmutable::instance($finGracia)->utc() : $reg;

        if ($t->lt($ini)) {
            return self::ANTICIPADO;
        }
        if ($t->lt($reg)) {
            return self::REGULAR;
        }
        if ($t->lt($efectivo)) {
            return self::GRACIA;
        }

        return self::CERRADO;
    }

    /**
     * Decide si un actor puede operar en el estado de ventana dado.
     *
     * @param  array<int, string>  $permisosActor  Permisos efectivos del actor.
     * @param  array<int, string>  $permisosGracia  Lista explícita de permisos habilitados en gracia. Vacía no habilita a nadie.
     */
    public static function puedeOperar(string $estado, array $permisosActor, array $permisosGracia): bool
    {
        if ($estado === self::REGULAR) {
            return true;
        }
        if ($estado !== self::GRACIA || $permisosGracia === []) {
            return false;
        }
        if (in_array('root', $permisosActor, true)) {
            return true;
        }

        return array_intersect($permisosActor, $permisosGracia) !== [];
    }
}
