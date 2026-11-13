<?php

namespace App\Console\Commands;

use App\Models\Academico\IaPresupuesto;
use App\Services\IA\PresupuestoIAService;
use App\Services\IA\ProxyIAService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Operación del presupuesto de IA del ámbito activo (simulación con el mock, real con OpenAI).
 *
 * - `estado`: muestra límite, gasto, reservas y disponibilidad.
 * - `agotar`: registra un ajuste administrativo de gasto igual al saldo restante (pruebas de ocultamiento).
 * - `restablecer`: elimina los ajustes administrativos de prueba y recalcula el gasto desde el libro.
 */
class IaPresupuestoCommand extends Command
{
    protected $signature = 'academico:ia-presupuesto {accion=estado : estado|agotar|restablecer} {--ambito= : simulacion|real; por defecto el ámbito del proveedor activo}';

    protected $description = 'Consulta o ajusta (pruebas) el presupuesto global de IA';

    public function handle(PresupuestoIAService $presupuesto, ProxyIAService $proxy): int
    {
        $ambito = (string) ($this->option('ambito') ?: $proxy->ambito());
        $fila = $presupuesto->fila($ambito);
        $accion = (string) $this->argument('accion');

        if ($accion === 'agotar') {
            $limite = $presupuesto->limite() ?? '0';
            DB::transaction(function () use ($ambito, $limite) {
                $f = IaPresupuesto::query()->where('ambito', $ambito)->lockForUpdate()->firstOrFail();
                $saldo = bcsub(bcsub($limite, PresupuestoIAService::n($f->gastado), 12), PresupuestoIAService::n($f->reservado), 12);
                if (bccomp($saldo, '0', 12) > 0) {
                    $f->gastado = bcadd(PresupuestoIAService::n($f->gastado), $saldo, 12);
                    $f->save();
                    DB::table('academico_auditoria')->insert(['operacion' => 'ia.presupuesto.ajuste_prueba', 'entidad' => 'ia_presupuesto', 'id_entidad' => $ambito, 'metadatos' => json_encode(['ajuste' => $saldo]), 'instante' => now()]);
                }
            });
        } elseif ($accion === 'restablecer') {
            DB::transaction(function () use ($ambito) {
                $f = IaPresupuesto::query()->where('ambito', $ambito)->lockForUpdate()->firstOrFail();
                $ajustes = DB::table('academico_auditoria')->where('operacion', 'ia.presupuesto.ajuste_prueba')->where('id_entidad', $ambito)->get();
                $total = $ajustes->reduce(fn ($c, $a) => bcadd($c, (string) (json_decode($a->metadatos, true)['ajuste'] ?? '0'), 12), '0');
                $f->gastado = bcsub(PresupuestoIAService::n($f->gastado), $total, 12);
                $f->save();
                DB::table('academico_auditoria')->where('operacion', 'ia.presupuesto.ajuste_prueba')->where('id_entidad', $ambito)->update(['operacion' => 'ia.presupuesto.ajuste_prueba_revertido']);
            });
        }
        $presupuesto->invalidar();
        $f = $fila->fresh();
        $this->line(json_encode([
            'ambito' => $ambito, 'limite' => $presupuesto->limite(), 'gastado' => (string) $f->gastado, 'reservado' => (string) $f->reservado,
            'disponibilidad' => $presupuesto->disponibilidad($ambito),
        ]));

        return self::SUCCESS;
    }
}
