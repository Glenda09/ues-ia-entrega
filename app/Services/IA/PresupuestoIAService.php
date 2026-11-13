<?php

namespace App\Services\IA;

use App\Models\Academico\IaEjecucion;
use App\Models\Academico\IaMovimientoGasto;
use App\Models\Academico\IaPresupuesto;
use App\Models\Academico\IaTarifa;
use App\Support\Academico\DominioException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * IA-BASE-01: presupuesto global acumulado en USD (AI_BUDGET_USD), reservas y liquidaciones idempotentes.
 *
 * Saldo = límite − gasto confirmado − reservas activas/inciertas. Ámbitos `real` y `simulacion` separados.
 * El límite aplica al ámbito activo de la instalación (simulación cuando no hay proveedor real).
 */
class PresupuestoIAService
{
    private const ESC = 12;

    private const CACHE = 'ia:disponibilidad';

    /**
     * Normaliza un decimal (incluida notación científica de algunos drivers) a cadena bcmath.
     */
    public static function n(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '0';
        }
        $s = (string) $v;
        if (preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return $s;
        }

        return rtrim(rtrim(sprintf('%.12F', (float) $v), '0'), '.') ?: '0';
    }

    public function limite(): ?string
    {
        $v = config('ia.presupuesto_usd');
        if ($v === null || $v === '' || ! is_numeric($v) || bccomp((string) $v, '0', self::ESC) <= 0) {
            return null;
        }

        return bcadd((string) $v, '0', self::ESC);
    }

    public function fila(string $ambito): IaPresupuesto
    {
        DB::table('ia_presupuesto')->insertOrIgnore(['ambito' => $ambito, 'gastado' => 0, 'reservado' => 0, 'incidente_tarifa' => false, 'created_at' => now(), 'updated_at' => now()]);

        return IaPresupuesto::query()->where('ambito', $ambito)->firstOrFail();
    }

    /**
     * Estado de disponibilidad compartido (UI y API). Sin montos para usuarios comunes.
     *
     * @return array{disponible: bool, motivo: string}
     */
    public function disponibilidad(string $ambito): array
    {
        return Cache::remember(self::CACHE.':'.$ambito, 30, function () use ($ambito) {
            $limite = $this->limite();
            if ($limite === null) {
                return ['disponible' => false, 'motivo' => 'CONFIGURACION_INVALIDA'];
            }
            $f = $this->fila($ambito);
            if ($f->incidente_tarifa) {
                return ['disponible' => false, 'motivo' => 'INCIDENTE_TARIFA'];
            }
            $saldo = bcsub(bcsub($limite, self::n($f->gastado), self::ESC), self::n($f->reservado), self::ESC);

            return bccomp($saldo, '0', self::ESC) > 0 ? ['disponible' => true, 'motivo' => 'DISPONIBLE'] : ['disponible' => false, 'motivo' => 'PRESUPUESTO_AGOTADO'];
        });
    }

    public function invalidar(): void
    {
        Cache::forget(self::CACHE.':real');
        Cache::forget(self::CACHE.':simulacion');
    }

    public function tarifa(string $proveedor, string $modelo): ?IaTarifa
    {
        return IaTarifa::query()->where('proveedor', $proveedor)->where('modelo', $modelo)->where('vigente_desde', '<=', now())->orderByDesc('version')->first();
    }

    public static function costo(IaTarifa $t, int $entrada, int $salida): string
    {
        $e = bcdiv(bcmul((string) $entrada, self::n($t->precio_entrada_mtok), self::ESC), '1000000', self::ESC);
        $s = bcdiv(bcmul((string) $salida, self::n($t->precio_salida_mtok), self::ESC), '1000000', self::ESC);

        return bcadd($e, $s, self::ESC);
    }

    /**
     * Reserva la cota superior bajo bloqueo del registro global. 409 si no cabe.
     */
    public function reservar(IaEjecucion $ej, string $ambito, string $monto, int $inferencia): void
    {
        DB::transaction(function () use ($ej, $ambito, $monto, $inferencia) {
            $limite = $this->limite();
            if ($limite === null) {
                throw DominioException::conflicto('IA_PRESUPUESTO_AGOTADO', 'El asistente no está disponible en este momento.');
            }
            $this->fila($ambito);
            $f = IaPresupuesto::query()->where('ambito', $ambito)->lockForUpdate()->firstOrFail();
            if ($f->incidente_tarifa) {
                throw DominioException::conflicto('IA_PRESUPUESTO_AGOTADO', 'El asistente no está disponible en este momento.');
            }
            $saldo = bcsub(bcsub($limite, self::n($f->gastado), self::ESC), self::n($f->reservado), self::ESC);
            if (bccomp($saldo, '0', self::ESC) <= 0) {
                throw DominioException::conflicto('IA_PRESUPUESTO_AGOTADO', 'El presupuesto de IA está agotado.');
            }
            if (bccomp($monto, $saldo, self::ESC) > 0) {
                throw DominioException::conflicto('IA_PRESUPUESTO_INSUFICIENTE', 'El saldo de IA no cubre esta consulta.');
            }
            IaMovimientoGasto::create(['id_ejecucion' => $ej->id_ejecucion, 'ambito' => $ambito, 'tipo' => 'reserva', 'monto' => $monto, 'clave' => "reserva:{$ej->id_ejecucion}:$inferencia"]);
            $f->reservado = bcadd(self::n($f->reservado), $monto, self::ESC);
            $f->save();
            $ej->costo_reservado = bcadd(self::n($ej->costo_reservado), $monto, self::ESC);
            $ej->save();
        });
        $this->invalidar();
    }

    /**
     * Convierte la reserva de una inferencia en gasto real y libera el remanente. Idempotente por clave.
     */
    public function liquidar(IaEjecucion $ej, string $ambito, string $reservado, string $real, int $inferencia): void
    {
        DB::transaction(function () use ($ej, $ambito, $reservado, $real, $inferencia) {
            if (IaMovimientoGasto::query()->where('clave', "liquidacion:{$ej->id_ejecucion}:$inferencia")->exists()) {
                return;
            }
            $f = IaPresupuesto::query()->where('ambito', $ambito)->lockForUpdate()->firstOrFail();
            IaMovimientoGasto::create(['id_ejecucion' => $ej->id_ejecucion, 'ambito' => $ambito, 'tipo' => 'liquidacion', 'monto' => $real, 'clave' => "liquidacion:{$ej->id_ejecucion}:$inferencia"]);
            $f->reservado = bcsub(self::n($f->reservado), $reservado, self::ESC);
            if (bccomp(self::n($f->reservado), '0', self::ESC) < 0) {
                $f->reservado = '0';
            }
            $f->gastado = bcadd(self::n($f->gastado), $real, self::ESC);
            if (bccomp($real, $reservado, self::ESC) > 0) {
                $f->incidente_tarifa = true; // Consumo superó la reserva: bloquear y registrar costo completo.
            }
            $f->save();
            $ej->costo_real = bcadd(self::n($ej->costo_real), $real, self::ESC);
            $ej->save();
        });
        $this->invalidar();
    }

    /**
     * Libera una reserva por fallo comprobado antes del envío.
     */
    public function liberar(IaEjecucion $ej, string $ambito, string $monto, int $inferencia): void
    {
        DB::transaction(function () use ($ej, $ambito, $monto, $inferencia) {
            if (IaMovimientoGasto::query()->where('clave', "liberacion:{$ej->id_ejecucion}:$inferencia")->exists()) {
                return;
            }
            $f = IaPresupuesto::query()->where('ambito', $ambito)->lockForUpdate()->firstOrFail();
            IaMovimientoGasto::create(['id_ejecucion' => $ej->id_ejecucion, 'ambito' => $ambito, 'tipo' => 'liberacion', 'monto' => $monto, 'clave' => "liberacion:{$ej->id_ejecucion}:$inferencia"]);
            $f->reservado = bcsub(self::n($f->reservado), $monto, self::ESC);
            $f->save();
        });
        $this->invalidar();
    }

    /**
     * Reconciliador: tras 24 h sin evidencia liquida toda la reserva incierta como estimación conservadora.
     */
    public function reconciliar(): int
    {
        $n = 0;
        IaEjecucion::query()->where('estado', 'incierta')->where('updated_at', '<', now()->subDay())->get()->each(function ($ej) use (&$n) {
            $ambito = $ej->simulado ? 'simulacion' : 'real';
            $pendiente = bcsub(self::n($ej->costo_reservado), self::n($ej->costo_real), self::ESC);
            DB::transaction(function () use ($ej, $ambito, $pendiente) {
                if (IaMovimientoGasto::query()->where('clave', "ajuste:{$ej->id_ejecucion}:conservador")->exists()) {
                    return;
                }
                $f = IaPresupuesto::query()->where('ambito', $ambito)->lockForUpdate()->firstOrFail();
                IaMovimientoGasto::create(['id_ejecucion' => $ej->id_ejecucion, 'ambito' => $ambito, 'tipo' => 'ajuste', 'monto' => $pendiente, 'clave' => "ajuste:{$ej->id_ejecucion}:conservador"]);
                $f->reservado = bcsub(self::n($f->reservado), $pendiente, self::ESC);
                $f->gastado = bcadd(self::n($f->gastado), $pendiente, self::ESC);
                $f->save();
                $ej->update(['estado' => 'liquidada', 'costo_real' => bcadd(self::n($ej->costo_real), $pendiente, self::ESC)]);
            });
            $n++;
        });
        $this->invalidar();

        return $n;
    }

    /**
     * Consumo para administración (montos visibles con permiso).
     *
     * @return array<string, mixed>
     */
    public function consumo(string $ambito, string $proveedor, string $modelo): array
    {
        $f = $this->fila($ambito);
        $porAgente = IaEjecucion::query()->where('simulado', $ambito === 'simulacion')
            ->selectRaw('agente, COUNT(*) as ejecuciones, SUM(costo_real) as gasto, SUM(tokens_entrada) as entrada, SUM(tokens_salida) as salida')
            ->groupBy('agente')->get();

        return [
            'ambito' => $ambito,
            'proveedor' => $proveedor,
            'modelo' => $modelo,
            'limite_usd' => $this->limite(),
            'gastado_usd' => self::n($f->gastado),
            'reservado_usd' => self::n($f->reservado),
            'saldo_usd' => $this->limite() !== null ? bcsub(bcsub($this->limite(), self::n($f->gastado), self::ESC), self::n($f->reservado), self::ESC) : null,
            'incidente_tarifa' => (bool) $f->incidente_tarifa,
            'disponibilidad' => $this->disponibilidad($ambito),
            'por_agente' => $porAgente,
        ];
    }
}
