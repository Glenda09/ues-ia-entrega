<?php

namespace App\Services\IA;

use App\Models\Academico\IaConversacion;
use App\Models\Academico\IaEjecucion;
use App\Models\Academico\IaMensaje;
use App\Services\IA\Adaptadores\AdaptadorIA;
use App\Services\IA\Adaptadores\FalloAntesDeEnvio;
use App\Services\IA\Adaptadores\OpenAIAdaptador;
use App\Services\IA\Adaptadores\SimuladoAdaptador;
use App\Support\Academico\DominioException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IA-BASE-01: proxy compartido. Turno idempotente, reserva antes de cada inferencia,
 * herramientas autorizadas en servidor, liquidación única y conversación en PostgreSQL.
 */
class ProxyIAService
{
    private ?AdaptadorIA $adaptador = null;

    public function __construct(private PresupuestoIAService $presupuesto) {}

    /**
     * Resuelve el adaptador: OpenAI con key; simulado sin key o con AI_PROVIDER=mock.
     */
    public function adaptador(): AdaptadorIA
    {
        if ($this->adaptador) {
            return $this->adaptador;
        }
        $proveedor = (string) config('ia.proveedor');
        $key = (string) config('ia.openai_api_key');
        if ($proveedor === 'openai' && $key !== '') {
            return $this->adaptador = new OpenAIAdaptador($key, (string) config('ia.modelo'), (int) config('ia.timeout_segundos'));
        }

        return $this->adaptador = new SimuladoAdaptador($proveedor === 'mock' ? (string) config('ia.modelo') : 'mock-academico-1');
    }

    public function usarAdaptador(AdaptadorIA $a): void
    {
        $this->adaptador = $a;
    }

    public function ambito(): string
    {
        return $this->adaptador()->simulado() ? 'simulacion' : 'real';
    }

    /**
     * @return array{disponible: bool, motivo: string}
     */
    public function disponibilidad(): array
    {
        return $this->presupuesto->disponibilidad($this->ambito());
    }

    /**
     * Ejecuta un turno completo.
     *
     * @param  array{tipo:string, id:string}  $propietario
     * @param  callable(string $nombre, array $args): array  $ejecutarHerramienta  Autoriza y ejecuta en servidor.
     * @param  array<int, array{nombre:string, descripcion:string, parametros:array}>  $herramientas
     * @return array{0:int, 1:array} [estado HTTP, cuerpo]
     */
    public function turno(array $propietario, string $agente, ?int $idExpediente, ?string $idConversacion, string $mensaje, string $clave, string $sistema, array $herramientas, callable $ejecutarHerramienta, callable $postprocesar): array
    {
        $max = (int) config('ia.max_caracteres_mensaje', 8000);
        $len = mb_strlen(trim($mensaje));
        if ($len < 1 || $len > $max) {
            throw new DominioException('MENSAJE_INVALIDO', "El mensaje debe tener entre 1 y $max caracteres.", 422);
        }
        $prop = $propietario['tipo'].':'.$propietario['id'];
        $hash = hash('sha256', json_encode([$mensaje, $idConversacion]));

        // Recuperación idempotente del turno.
        $previo = IaEjecucion::query()->where(['propietario' => $prop, 'agente' => $agente, 'id_expediente' => $idExpediente, 'clave_turno' => $clave])->first();
        if ($previo) {
            return $this->resolverPrevio($previo, $hash);
        }
        if (! $this->disponibilidad()['disponible']) {
            throw DominioException::conflicto('IA_PRESUPUESTO_AGOTADO', 'El asistente no está disponible porque se agotó el presupuesto de IA.');
        }

        $adaptador = $this->adaptador();
        $tarifa = $this->presupuesto->tarifa($adaptador->proveedor(), $adaptador->modelo());
        if (! $tarifa) {
            throw DominioException::configuracionIncompleta(['ia_tarifa:'.$adaptador->proveedor().'/'.$adaptador->modelo()]);
        }

        // 1. Turno y conversación bajo el propietario.
        try {
            [$ej, $conv] = DB::transaction(function () use ($propietario, $prop, $agente, $idExpediente, $idConversacion, $mensaje, $clave, $hash, $adaptador, $tarifa) {
                $conv = $idConversacion ? $this->conversacionPropia($propietario, $agente, $idExpediente, $idConversacion) : IaConversacion::create([
                    'propietario_tipo' => $propietario['tipo'], 'propietario_id' => $propietario['id'], 'agente' => $agente,
                    'id_expediente' => $idExpediente, 'titulo' => mb_substr($mensaje, 0, 80),
                ]);
                $ej = IaEjecucion::create([
                    'propietario' => $prop, 'agente' => $agente, 'id_expediente' => $idExpediente, 'clave_turno' => $clave, 'hash_cuerpo' => $hash,
                    'id_conversacion' => $conv->id_conversacion, 'proveedor' => $adaptador->proveedor(), 'modelo' => $adaptador->modelo(),
                    'simulado' => $adaptador->simulado(), 'estado' => 'reservada', 'id_tarifa' => $tarifa->id_tarifa,
                ]);
                $this->agregarMensaje($conv, 'user', $mensaje);

                return [$ej, $conv];
            });
        } catch (UniqueConstraintViolationException) {
            $previo = IaEjecucion::query()->where(['propietario' => $prop, 'agente' => $agente, 'id_expediente' => $idExpediente, 'clave_turno' => $clave])->firstOrFail();

            return $this->resolverPrevio($previo, $hash);
        }

        $ambito = $adaptador->simulado() ? 'simulacion' : 'real';
        $contexto = $this->contexto($conv, $sistema);
        $maxSalida = (int) config('ia.max_tokens_salida', 1000);
        $herramientasUsadas = 0;
        $ejecutadas = [];
        $repeticiones = 0;
        $herramientasTurno = $herramientas;
        $fuentes = [];
        $advertencias = [];
        $resultadoFinal = null;

        for ($i = 1; $i <= (int) config('ia.max_inferencias_turno', 4); $i++) {
            // 2. Cota superior y reserva antes de cada inferencia.
            $entradaEstimada = (int) ceil(mb_strlen(collect($contexto)->pluck('contenido')->implode("\n")) / 3) + 200;
            $entradaEstimada = min($entradaEstimada, (int) config('ia.max_tokens_entrada', 8000));
            $cota = $this->redondearArriba(PresupuestoIAService::costo($tarifa, $entradaEstimada, $maxSalida));
            try {
                $this->presupuesto->reservar($ej, $ambito, $cota, $i);
            } catch (DominioException $e) {
                $ej->update(['estado' => $i === 1 ? 'fallida' : 'liquidada', 'error' => $e->codigo]);
                if ($i === 1) {
                    throw $e;
                }
                $advertencias[] = 'El turno se interrumpió por falta de saldo.';
                break;
            }
            // 3. Persistir "enviada" antes de la red.
            $ej->update(['estado' => 'enviada', 'inferencias' => $i]);
            try {
                // Última inferencia permitida: sin herramientas, para que el modelo redacte con lo ya consultado.
                $ultima = $i === (int) config('ia.max_inferencias_turno', 4);
                $r = $adaptador->generar($contexto, $ultima ? [] : $herramientasTurno, $maxSalida);
            } catch (FalloAntesDeEnvio $e) {
                $this->presupuesto->liberar($ej, $ambito, $cota, $i);
                $ej->update(['estado' => 'fallida', 'error' => mb_substr($e->getMessage(), 0, 500)]);
                throw new DominioException('IA_NO_DISPONIBLE', 'El proveedor de IA no está disponible. Intenta más tarde.', 503);
            } catch (\Throwable $e) {
                // 5. Fallo posterior al envío: reserva incierta, sin reenvío automático.
                $ej->update(['estado' => 'incierta', 'error' => mb_substr($e->getMessage(), 0, 500)]);
                Log::warning('Inferencia incierta', ['id_ejecucion' => $ej->id_ejecucion]);
                throw new DominioException('IA_RESPUESTA_INCIERTA', 'No se obtuvo respuesta del asistente. Puedes reintentar el turno con la misma clave más tarde.', 504, ['id_ejecucion' => $ej->id_ejecucion]);
            }
            // 4. Liquidación con consumo verificable.
            $real = PresupuestoIAService::costo($tarifa, (int) $r['uso']['entrada'], (int) $r['uso']['salida']);
            $this->presupuesto->liquidar($ej, $ambito, $cota, $real, $i);
            $ej->update([
                'tokens_entrada' => $ej->tokens_entrada + (int) $r['uso']['entrada'], 'tokens_salida' => $ej->tokens_salida + (int) $r['uso']['salida'],
                'id_respuesta_proveedor' => $r['id_respuesta'], 'estado' => 'liquidada',
            ]);

            if ($r['llamadas']) {
                // Herramienta ya ejecutada en este turno con los mismos argumentos: se reutiliza el resultado
                // para cortar bucles de llamadas repetidas y no recalcular.
                foreach ($r['llamadas'] as $llamada) {
                    $idLlamada = $llamada['id_llamada'] ?? null;
                    $firma = $llamada['nombre'].':'.json_encode((array) $llamada['argumentos']);
                    $repetida = isset($ejecutadas[$firma]);
                    if ($repetida) {
                        $repeticiones++;
                    }
                    if ($idLlamada) {
                        $contexto[] = ['rol' => 'tool_call', 'id_llamada' => $idLlamada, 'nombre' => $llamada['nombre'], 'contenido' => json_encode((object) $llamada['argumentos'])];
                    }
                    if (! $repetida && ++$herramientasUsadas > (int) config('ia.max_herramientas_turno', 8)) {
                        // Límite alcanzado: se responde la llamada con un error (el proveedor exige salida por call_id)
                        // y la siguiente inferencia va sin herramientas para que redacte con lo ya consultado.
                        $contexto[] = ['rol' => 'tool', 'id_llamada' => $idLlamada, 'nombre' => $llamada['nombre'], 'contenido' => json_encode(['error' => 'Límite de herramientas del turno alcanzado; responde con los datos ya obtenidos.'])];
                        $herramientasTurno = [];

                        continue;
                    }
                    if ($repetida) {
                        $contexto[] = ['rol' => 'tool', 'id_llamada' => $idLlamada, 'nombre' => $llamada['nombre'], 'contenido' => $ejecutadas[$firma]];

                        continue;
                    }
                    $nombres = array_column($herramientas, 'nombre');
                    try {
                        $salida = in_array($llamada['nombre'], $nombres, true)
                            ? $ejecutarHerramienta($llamada['nombre'], (array) $llamada['argumentos'])
                            : ['error' => 'Herramienta no permitida.'];
                    } catch (DominioException $e) {
                        // Argumentos inválidos o fuera de alcance propuestos por el modelo: el error vuelve al modelo
                        // como resultado de la herramienta y el turno continúa (sin exponer datos ajenos).
                        $salida = ['error' => $e->getMessage(), 'code' => $e->codigo];
                    }
                    $fuentes = array_merge($fuentes, $salida['__fuentes'] ?? []);
                    unset($salida['__fuentes']);
                    // Marca de cita lista para copiar: evita que el modelo cite el ID sin el prefijo `fuente:`.
                    if (isset($salida['referencia']) && ! isset($salida['cita'])) {
                        $salida['cita'] = '[fuente:'.$salida['referencia'].']';
                    }
                    foreach ($salida['fuentes'] ?? [] as $k => $f) {
                        if (is_array($f) && isset($f['id']) && ! isset($f['cita'])) {
                            $salida['fuentes'][$k]['cita'] = '[fuente:'.$f['id'].']';
                        }
                    }
                    $ejecutadas[$firma] = json_encode($salida, JSON_UNESCAPED_UNICODE);
                    $contexto[] = ['rol' => 'tool', 'id_llamada' => $idLlamada, 'nombre' => $llamada['nombre'], 'contenido' => $ejecutadas[$firma]];
                }
                // Si el modelo solo repite llamadas ya resueltas, la siguiente inferencia se pide sin herramientas
                // para forzar la redacción de la respuesta con los resultados disponibles.
                if ($repeticiones > 0) {
                    $herramientasTurno = [];
                }

                continue;
            }
            $resultadoFinal = $r;
            break;
        }
        if (! $resultadoFinal) {
            $advertencias[] = 'Se alcanzó el límite de inferencias del turno.';
            $resultadoFinal = ['texto' => 'No pude completar la respuesta dentro de los límites del turno. Reformula la pregunta de forma más específica.', 'citas' => []];
        }

        $cuerpo = $postprocesar($resultadoFinal, $fuentes, $advertencias) + ['id_conversacion' => $conv->id_conversacion];
        DB::transaction(function () use ($conv, $cuerpo, $ej) {
            $this->agregarMensaje($conv, 'assistant', $cuerpo['respuesta'], ['fuentes' => $cuerpo['fuentes'], 'propuesta' => $cuerpo['propuesta'] ?? null, 'advertencias' => $cuerpo['advertencias']]);
            $ej->update(['resultado' => $cuerpo]);
            $conv->touch();
        });
        $cuerpo['disponibilidad'] = $this->disponibilidad();

        return [200, ['message' => 'Respuesta generada', 'data' => $cuerpo]];
    }

    /**
     * Conversación del propietario o 404.
     */
    public function conversacionPropia(array $propietario, string $agente, ?int $idExpediente, string $id): IaConversacion
    {
        $c = IaConversacion::query()->whereKey($id)->where('propietario_tipo', $propietario['tipo'])->where('propietario_id', $propietario['id'])
            ->where('agente', $agente)->when($idExpediente !== null, fn ($q) => $q->where('id_expediente', $idExpediente))->first();
        if (! $c) {
            throw DominioException::noEncontrado();
        }

        return $c;
    }

    public function agregarMensaje(IaConversacion $c, string $rol, string $contenido, ?array $referencias = null): IaMensaje
    {
        IaConversacion::query()->whereKey($c->id_conversacion)->lockForUpdate()->first();
        $seq = (int) IaMensaje::query()->where('id_conversacion', $c->id_conversacion)->max('secuencia') + 1;

        return IaMensaje::create(['id_conversacion' => $c->id_conversacion, 'secuencia' => $seq, 'rol' => $rol, 'contenido' => $contenido, 'referencias' => $referencias]);
    }

    /**
     * Contexto: sistema + últimos mensajes dentro del límite (los originales se conservan en PostgreSQL).
     *
     * @return array<int, array{rol:string, contenido:string}>
     */
    private function contexto(IaConversacion $c, string $sistema): array
    {
        $limite = (int) config('ia.max_tokens_entrada', 8000) * 3;
        $mensajes = IaMensaje::query()->where('id_conversacion', $c->id_conversacion)->orderByDesc('secuencia')->limit(30)->get();
        $sel = [];
        $total = mb_strlen($sistema);
        foreach ($mensajes as $m) {
            $total += mb_strlen($m->contenido);
            if ($total > $limite && $sel) {
                break;
            }
            array_unshift($sel, ['rol' => $m->rol, 'contenido' => $m->contenido]);
        }

        return array_merge([['rol' => 'system', 'contenido' => $sistema]], $sel);
    }

    private function resolverPrevio(IaEjecucion $previo, string $hash): array
    {
        if (! hash_equals($previo->hash_cuerpo, $hash)) {
            throw DominioException::conflicto('IDEMPOTENCIA_CONFLICTO', 'La clave de idempotencia ya se usó con otro contenido.');
        }
        if ($previo->resultado) {
            return [200, ['message' => 'Respuesta generada', 'data' => $previo->resultado + ['disponibilidad' => $this->disponibilidad()]]];
        }
        if (in_array($previo->estado, ['fallida'], true)) {
            throw DominioException::conflicto('IA_TURNO_FALLIDO', 'El turno anterior falló; usa una nueva clave para reintentar.');
        }

        return [202, ['message' => 'El turno está en proceso o pendiente de reconciliación.', 'data' => ['id_ejecucion' => $previo->id_ejecucion, 'id_conversacion' => $previo->id_conversacion, 'estado' => $previo->estado]]];
    }

    private function redondearArriba(string $v): string
    {
        $r = bcadd($v, '0', 12);

        return bccomp($r, $v, 14) < 0 ? bcadd($r, '0.000000000001', 12) : $r;
    }
}
