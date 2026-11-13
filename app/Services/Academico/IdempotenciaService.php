<?php

namespace App\Services\Academico;

use App\Models\Academico\Idempotencia;
use App\Support\Academico\DominioException;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Idempotencia acotada por propietario, operación y recurso (contratos comunes).
 *
 * Guarda el hash del cuerpo normalizado y el resultado dentro de la misma transacción
 * de negocio. Un cuerpo distinto con la misma clave devuelve `IDEMPOTENCIA_CONFLICTO`.
 */
class IdempotenciaService
{
    /**
     * Valida el formato de la clave recibida.
     */
    public function validarClave(?string $clave): string
    {
        $clave = trim((string) $clave);
        if ($clave === '' || mb_strlen($clave) > 128) {
            throw new DominioException('IDEMPOTENCIA_CLAVE_INVALIDA', 'La cabecera Idempotency-Key es obligatoria (1-128 caracteres).', 422);
        }

        return $clave;
    }

    public function hashCuerpo(array $cuerpo): string
    {
        return hash('sha256', json_encode($this->normalizar($cuerpo)));
    }

    /**
     * Ejecuta `$operacion` una sola vez por alcance/clave.
     *
     * @param  Closure(): array{0:int,1:array}  $operacion  Devuelve [estado HTTP, cuerpo JSON]. Se ejecuta dentro de la transacción.
     * @return array{0:int,1:array} Resultado nuevo o almacenado.
     */
    public function ejecutar(string $propietario, string $operacion, string $recurso, string $clave, array $cuerpo, Closure $ejecutar): array
    {
        $hash = $this->hashCuerpo($cuerpo);

        $previo = $this->buscar($propietario, $operacion, $recurso, $clave);
        if ($previo) {
            return $this->resolverPrevio($previo, $hash);
        }

        try {
            return DB::transaction(function () use ($propietario, $operacion, $recurso, $clave, $hash, $ejecutar) {
                $registro = Idempotencia::create([
                    'propietario' => $propietario,
                    'operacion' => $operacion,
                    'recurso' => $recurso,
                    'clave' => $clave,
                    'hash_cuerpo' => $hash,
                ]);
                [$estado, $respuesta] = $ejecutar();
                $registro->update(['estado_http' => $estado, 'respuesta' => $respuesta]);

                return [$estado, $respuesta];
            });
        } catch (UniqueConstraintViolationException $e) {
            $previo = $this->buscar($propietario, $operacion, $recurso, $clave);
            if ($previo) {
                return $this->resolverPrevio($previo, $hash);
            }
            throw $e;
        }
    }

    private function buscar(string $propietario, string $operacion, string $recurso, string $clave): ?Idempotencia
    {
        return Idempotencia::query()
            ->where(compact('propietario', 'operacion', 'recurso', 'clave'))
            ->first();
    }

    private function resolverPrevio(Idempotencia $previo, string $hash): array
    {
        if (! hash_equals($previo->hash_cuerpo, $hash)) {
            throw DominioException::conflicto('IDEMPOTENCIA_CONFLICTO', 'La clave de idempotencia ya se usó con otro contenido.');
        }
        if ($previo->estado_http === null) {
            throw DominioException::conflicto('OPERACION_EN_CURSO', 'La operación con esta clave está en curso.');
        }

        return [$previo->estado_http === 201 ? 200 : $previo->estado_http, $previo->respuesta ?? []];
    }

    private function normalizar(mixed $valor): mixed
    {
        if (is_array($valor)) {
            if (array_is_list($valor)) {
                return array_map(fn ($v) => $this->normalizar($v), $valor);
            }
            ksort($valor);

            return array_map(fn ($v) => $this->normalizar($v), $valor);
        }

        return $valor;
    }
}
