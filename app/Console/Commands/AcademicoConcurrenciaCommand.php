<?php

namespace App\Console\Commands;

use App\Models\Academico\CicloCarrera;
use App\Models\Academico\Expediente;
use App\Models\Academico\Grupo;
use App\Models\Academico\IaEjecucion;
use App\Models\Academico\SolicitudIngreso;
use App\Services\Academico\CalificacionService;
use App\Services\Academico\DecisionAdmisionService;
use App\Services\Academico\InscripcionService;
use App\Services\IA\PresupuestoIAService;
use App\Support\Academico\Actor;
use App\Support\Academico\DominioException;
use Illuminate\Console\Command;

/**
 * Ejecuta UNA operación de dominio en un proceso independiente para pruebas de concurrencia
 * real sobre PostgreSQL (cada proceso tiene su propia conexión y transacción).
 *
 * Espera hasta el instante `--en` (microtime) para que varios procesos compitan a la vez.
 * Imprime una línea JSON: {"ok":bool,"code":string|null,"data":mixed}.
 */
class AcademicoConcurrenciaCommand extends Command
{
    protected $signature = 'academico:concurrencia {operacion} {--en=} {--json=}';

    protected $description = 'Operación de dominio aislada para pruebas concurrentes (solo pruebas)';

    public function handle(): int
    {
        $args = json_decode((string) $this->option('json'), true) ?: [];
        $en = (float) $this->option('en');
        while ($en > 0 && microtime(true) < $en) {
            usleep(500);
        }
        try {
            $data = match ($this->argument('operacion')) {
                'inscribir' => $this->inscribir($args),
                'aceptar' => $this->aceptar($args),
                'publicar' => $this->publicar($args),
                'reservar' => $this->reservar($args),
            };
            $this->line(json_encode(['ok' => true, 'code' => null, 'data' => $data]));
        } catch (DominioException $e) {
            $this->line(json_encode(['ok' => false, 'code' => $e->codigo, 'data' => null]));
        } catch (\Throwable $e) {
            $this->line(json_encode(['ok' => false, 'code' => 'EXCEPCION:'.class_basename($e).':'.mb_substr($e->getMessage(), 0, 160), 'data' => null]));
        }

        return self::SUCCESS;
    }

    private function inscribir(array $a): mixed
    {
        $actor = new Actor($a['id_usuario'], ['academico_mi_inscripcion_gestionar']);
        $r = app(InscripcionService::class)->confirmar($actor, Expediente::findOrFail($a['id_expediente']), CicloCarrera::findOrFail($a['id_ciclo']), $a['grupos']);

        return array_map(fn ($i) => $i->id_inscripcion, $r);
    }

    private function aceptar(array $a): mixed
    {
        $s = SolicitudIngreso::findOrFail($a['id_solicitud']);

        return app(DecisionAdmisionService::class)->aceptar($s, (int) $a['version'], $a['fecha_ingreso'], $a['actor'])->id_expediente;
    }

    private function publicar(array $a): mixed
    {
        $actor = new Actor($a['actor'], ['academico_acta_publicar']);

        return app(CalificacionService::class)->publicar($actor, Grupo::findOrFail($a['id_grupo']), (int) $a['version'])->id_acta;
    }

    private function reservar(array $a): mixed
    {
        config(['ia.presupuesto_usd' => $a['limite']]);
        app(PresupuestoIAService::class)->reservar(IaEjecucion::findOrFail($a['id_ejecucion']), 'simulacion', $a['monto'], 1);

        return $a['id_ejecucion'];
    }
}
