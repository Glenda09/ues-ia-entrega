<?php

namespace App\Services\Academico;

use App\Models\Academico\Carrera;
use App\Models\Academico\Materia;
use App\Models\Academico\Pensum;
use App\Models\Academico\PensumEquivalencia;
use App\Models\Academico\PensumMateria;
use App\Models\Academico\PensumRequisito;
use App\Support\Academico\DominioException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * GEN-03: versiones de pensum, contenido, dependencias y publicación atómica.
 */
class PensumService
{
    public function __construct(private AuditoriaService $auditoria, private CatalogoService $catalogo) {}

    /**
     * Crea un borrador, opcionalmente clonando contenido (no estado ni vigencia) de otra versión de la carrera.
     */
    public function crear(Carrera $carrera, string $versionCurricular, ?int $idOrigen, string $actor): Pensum
    {
        return DB::transaction(function () use ($carrera, $versionCurricular, $idOrigen, $actor) {
            if (Pensum::query()->where('id_carrera', $carrera->id_carrera)->where('version_curricular', $versionCurricular)->exists()) {
                throw ValidationException::withMessages(['version_curricular' => 'La versión ya existe para esta carrera.']);
            }
            $origen = null;
            if ($idOrigen) {
                $origen = Pensum::query()->where('id_carrera', $carrera->id_carrera)->find($idOrigen);
                if (! $origen) {
                    throw ValidationException::withMessages(['id_pensum_origen' => 'El pensum de origen no pertenece a la carrera.']);
                }
            }
            $pensum = Pensum::create([
                'id_carrera' => $carrera->id_carrera,
                'version_curricular' => $versionCurricular,
                'estado' => 'borrador',
                'id_pensum_origen' => $origen?->id_pensum,
            ]);
            if ($origen) {
                foreach ($origen->materias as $m) {
                    PensumMateria::create(['id_pensum' => $pensum->id_pensum] + $m->only(['id_materia', 'nivel', 'orden', 'creditos', 'obligatoria']));
                }
                foreach ($origen->requisitos as $r) {
                    PensumRequisito::create(['id_pensum' => $pensum->id_pensum] + $r->only(['id_materia', 'id_materia_requisito', 'tipo']));
                }
                foreach ($origen->equivalencias as $e) {
                    PensumEquivalencia::create(['id_pensum' => $pensum->id_pensum] + $e->only(['id_materia_origen', 'id_materia_destino']));
                }
            }
            $this->auditoria->registrar($actor, 'pensum.crear', 'pensum', $pensum->id_pensum, null, ['origen' => $origen?->id_pensum]);

            return $pensum;
        });
    }

    /**
     * Reemplaza contenido y vigencia de un borrador como un conjunto validado.
     *
     * @param  array{version:int, vigente_desde?:?string, vigente_hasta?:?string, materias?:array, requisitos?:array, equivalencias?:array}  $d
     */
    public function actualizarBorrador(Pensum $pensum, array $d, string $actor): Pensum
    {
        return DB::transaction(function () use ($pensum, $d, $actor) {
            $pensum = Pensum::query()->lockForUpdate()->findOrFail($pensum->id_pensum);
            $this->catalogo->exigirVersion($pensum, (int) $d['version']);
            if ($pensum->estado !== 'borrador') {
                throw DominioException::conflicto('PENSUM_NO_EDITABLE', 'Solo se edita un pensum en borrador. Clona la versión para cambiarla.');
            }
            foreach (['vigente_desde', 'vigente_hasta'] as $campo) {
                if (array_key_exists($campo, $d)) {
                    $pensum->{$campo} = $d[$campo];
                }
            }
            if (array_key_exists('materias', $d)) {
                $materias = collect($d['materias']);
                if ($materias->pluck('id_materia')->duplicates()->isNotEmpty()) {
                    throw ValidationException::withMessages(['materias' => 'Hay materias repetidas en la versión.']);
                }
                $inactivas = Materia::query()->whereIn('id_materia', $materias->pluck('id_materia'))->where('estado', '!=', 'activa')->pluck('codigo');
                if ($inactivas->isNotEmpty()) {
                    throw ValidationException::withMessages(['materias' => 'Materias archivadas: '.$inactivas->implode(', ')]);
                }
                PensumRequisito::query()->where('id_pensum', $pensum->id_pensum)->delete();
                PensumEquivalencia::query()->where('id_pensum', $pensum->id_pensum)->delete();
                PensumMateria::query()->where('id_pensum', $pensum->id_pensum)->delete();
                foreach ($materias as $m) {
                    PensumMateria::create([
                        'id_pensum' => $pensum->id_pensum,
                        'id_materia' => $m['id_materia'],
                        'nivel' => $m['nivel'],
                        'orden' => $m['orden'] ?? 1,
                        'creditos' => $m['creditos'],
                        'obligatoria' => $m['obligatoria'] ?? true,
                    ]);
                }
            }
            $ids = PensumMateria::query()->where('id_pensum', $pensum->id_pensum)->pluck('id_materia')->map(fn ($i) => (int) $i)->all();
            if (array_key_exists('requisitos', $d)) {
                PensumRequisito::query()->where('id_pensum', $pensum->id_pensum)->delete();
                $vistos = [];
                foreach ($d['requisitos'] as $i => $r) {
                    $destino = (int) $r['id_materia'];
                    $req = (int) $r['id_materia_requisito'];
                    if ($destino === $req) {
                        throw ValidationException::withMessages(["requisitos.$i" => 'Una materia no puede depender de sí misma.']);
                    }
                    if (! in_array($destino, $ids, true) || ! in_array($req, $ids, true)) {
                        throw ValidationException::withMessages(["requisitos.$i" => 'Ambas materias deben pertenecer a la versión.']);
                    }
                    if (isset($vistos["$destino-$req"])) {
                        throw ValidationException::withMessages(["requisitos.$i" => 'Dependencia duplicada.']);
                    }
                    $vistos["$destino-$req"] = true;
                    PensumRequisito::create(['id_pensum' => $pensum->id_pensum, 'id_materia' => $destino, 'id_materia_requisito' => $req, 'tipo' => $r['tipo'] ?? 'prerrequisito']);
                }
                $this->validarAciclico($pensum->id_pensum);
            }
            if (array_key_exists('equivalencias', $d)) {
                PensumEquivalencia::query()->where('id_pensum', $pensum->id_pensum)->delete();
                $origenes = [];
                foreach ($d['equivalencias'] as $i => $e) {
                    $destino = (int) $e['id_materia_destino'];
                    if (! in_array($destino, $ids, true)) {
                        throw ValidationException::withMessages(["equivalencias.$i" => 'La materia destino debe pertenecer a la versión.']);
                    }
                    if (isset($origenes[$e['id_materia_origen']])) {
                        throw ValidationException::withMessages(["equivalencias.$i" => 'Un origen no puede acreditar dos destinos del mismo pensum.']);
                    }
                    $origenes[$e['id_materia_origen']] = true;
                    PensumEquivalencia::create(['id_pensum' => $pensum->id_pensum, 'id_materia_origen' => $e['id_materia_origen'], 'id_materia_destino' => $destino]);
                }
            }
            $pensum->version++;
            $pensum->save();
            $this->auditoria->registrar($actor, 'pensum.actualizar', 'pensum', $pensum->id_pensum);

            return $pensum;
        });
    }

    /**
     * Transición borrador → publicado → retirado. Estado actual es reintento equivalente.
     */
    public function transicion(Pensum $pensum, string $estado, int $version, string $actor): Pensum
    {
        return DB::transaction(function () use ($pensum, $estado, $version, $actor) {
            // Serializa publicaciones concurrentes de la carrera.
            Carrera::query()->lockForUpdate()->findOrFail($pensum->id_carrera);
            $pensum = Pensum::query()->lockForUpdate()->findOrFail($pensum->id_pensum);
            if ($pensum->estado === $estado) {
                return $pensum;
            }
            $this->catalogo->exigirVersion($pensum, $version);
            $permitidas = ['borrador' => ['publicado'], 'publicado' => ['retirado']];
            if (! in_array($estado, $permitidas[$pensum->estado] ?? [], true)) {
                throw DominioException::conflicto('TRANSICION_INVALIDA', "No se permite pasar de {$pensum->estado} a {$estado}.");
            }
            if ($estado === 'publicado') {
                $this->validarPublicacion($pensum);
                $pensum->publicado_por = $actor;
                $pensum->publicado_en = now('UTC');
            } else {
                $pensum->vigente_hasta ??= now()->toDateString();
            }
            $pensum->estado = $estado;
            $pensum->version++;
            $pensum->save();
            $this->auditoria->registrar($actor, 'pensum.transicion', 'pensum', $pensum->id_pensum, null, ['estado' => $estado]);

            return $pensum;
        });
    }

    /**
     * Errores de publicación por materia/campo.
     */
    public function validarPublicacion(Pensum $pensum): void
    {
        $errores = [];
        $carrera = Carrera::find($pensum->id_carrera);
        if ($carrera?->estado !== 'activa') {
            $errores['carrera'][] = 'La carrera está archivada.';
        }
        if (! $pensum->vigente_desde) {
            $errores['vigente_desde'][] = 'La vigencia inicial es obligatoria para publicar.';
        }
        $materias = $pensum->materias()->with('materia')->get();
        if ($materias->isEmpty()) {
            $errores['materias'][] = 'El pensum requiere al menos una materia.';
        }
        foreach ($materias as $m) {
            if ((float) $m->creditos <= 0) {
                $errores['materias.'.$m->materia->codigo][] = 'Créditos deben ser positivos.';
            }
            if ($m->materia->estado !== 'activa') {
                $errores['materias.'.$m->materia->codigo][] = 'Materia archivada.';
            }
        }
        if ($pensum->vigente_desde) {
            $desde = $pensum->vigente_desde->toDateString();
            $hasta = $pensum->vigente_hasta?->toDateString();
            $solapa = Pensum::query()->where('id_carrera', $pensum->id_carrera)->where('estado', 'publicado')
                ->where('id_pensum', '!=', $pensum->id_pensum)
                ->where(function ($q) use ($desde) {
                    $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', $desde);
                })
                ->when($hasta, fn ($q) => $q->where('vigente_desde', '<', $hasta))
                ->get();
            foreach ($solapa as $otro) {
                // Una versión abierta anterior se cierra de forma auditada en la fecha efectiva.
                if ($otro->vigente_hasta === null && $otro->vigente_desde->toDateString() < $desde) {
                    $otro->vigente_hasta = $desde;
                    $otro->version++;
                    $otro->save();
                    $this->auditoria->registrar($pensum->publicado_por, 'pensum.cerrar_vigencia', 'pensum', $otro->id_pensum, 'Cierre por publicación de nueva versión', ['hasta' => $desde, 'por' => $pensum->id_pensum]);

                    continue;
                }
                $errores['vigente_desde'][] = "La vigencia se solapa con la versión {$otro->version_curricular}.";
            }
        }
        try {
            $this->validarAciclico($pensum->id_pensum);
        } catch (ValidationException $e) {
            $errores['requisitos'][] = 'Prerrequisitos cíclicos.';
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * Pensum aplicable para una fecha de ingreso (una única versión por fecha).
     */
    public function aplicable(int $idCarrera, string $fecha): ?Pensum
    {
        return Pensum::query()->where('id_carrera', $idCarrera)->where('estado', 'publicado')
            ->where('vigente_desde', '<=', $fecha)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', $fecha))
            ->orderByDesc('vigente_desde')->first();
    }

    /**
     * Detalle con materias por nivel y dependencias.
     *
     * @return array<string, mixed>
     */
    public function detalle(Pensum $pensum): array
    {
        $materias = $pensum->materias()->with('materia')->orderBy('nivel')->orderBy('orden')->get();
        $porId = $materias->keyBy('id_materia');
        $req = $pensum->requisitos()->get();

        return [
            'id_pensum' => $pensum->id_pensum,
            'id_carrera' => $pensum->id_carrera,
            'version_curricular' => $pensum->version_curricular,
            'vigente_desde' => $pensum->vigente_desde?->toDateString(),
            'vigente_hasta' => $pensum->vigente_hasta?->toDateString(),
            'estado' => $pensum->estado,
            'version' => (int) $pensum->version,
            'id_pensum_origen' => $pensum->id_pensum_origen,
            'creditos_obligatorios' => (float) $materias->where('obligatoria', true)->sum('creditos'),
            'materias' => $materias->map(fn ($m) => [
                'id_materia' => $m->id_materia,
                'codigo' => $m->materia->codigo,
                'nombre' => $m->materia->nombre,
                'nivel' => $m->nivel,
                'orden' => $m->orden,
                'creditos' => (float) $m->creditos,
                'obligatoria' => (bool) $m->obligatoria,
                'requisitos' => $req->where('id_materia', $m->id_materia)->map(fn ($r) => [
                    'id_materia' => $r->id_materia_requisito,
                    'codigo' => $porId[$r->id_materia_requisito]->materia->codigo ?? null,
                    'tipo' => $r->tipo,
                ])->values(),
            ])->values(),
            'requisitos' => $req->map->only(['id_materia', 'id_materia_requisito', 'tipo'])->values(),
            'equivalencias' => $pensum->equivalencias()->get()->map->only(['id_materia_origen', 'id_materia_destino'])->values(),
        ];
    }

    private function validarAciclico(int $idPensum): void
    {
        $aristas = PensumRequisito::query()->where('id_pensum', $idPensum)->where('tipo', 'prerrequisito')->get(['id_materia', 'id_materia_requisito']);
        $grafo = [];
        foreach ($aristas as $a) {
            $grafo[$a->id_materia][] = $a->id_materia_requisito;
        }
        $estado = [];
        $visitar = function ($n) use (&$visitar, &$estado, $grafo) {
            $estado[$n] = 1;
            foreach ($grafo[$n] ?? [] as $m) {
                if (($estado[$m] ?? 0) === 1) {
                    throw ValidationException::withMessages(['requisitos' => 'Los prerrequisitos forman un ciclo.']);
                }
                if (($estado[$m] ?? 0) === 0) {
                    $visitar($m);
                }
            }
            $estado[$n] = 2;
        };
        foreach (array_keys($grafo) as $n) {
            if (($estado[$n] ?? 0) === 0) {
                $visitar($n);
            }
        }
    }
}
