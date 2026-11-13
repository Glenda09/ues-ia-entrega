<?php

namespace App\Support\Academico;

use Carbon\CarbonImmutable;

/**
 * Reloj institucional evaluable en pruebas.
 *
 * Usa el reloj de Laravel (`Carbon::setTestNow`) para que las pruebas fijen
 * el instante. Todos los instantes se devuelven en UTC.
 */
class Reloj
{
    public function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    /**
     * Convierte una fecha/hora local de la zona institucional a UTC.
     */
    public function aUtc(string $fechaLocal, string $zona): CarbonImmutable
    {
        return CarbonImmutable::parse($fechaLocal, $zona)->utc();
    }

    /**
     * Fecha local (Y-m-d) de un instante en la zona indicada.
     */
    public function fechaLocal(?CarbonImmutable $instante, string $zona): string
    {
        return ($instante ?? $this->ahora())->setTimezone($zona)->format('Y-m-d');
    }
}
