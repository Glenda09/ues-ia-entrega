<?php

namespace Database\Seeders;

use App\Models\Academico\Carrera;
use App\Models\Academico\Facultad;
use App\Models\Academico\Materia;
use App\Models\Academico\Pensum;
use App\Models\Academico\PensumMateria;
use App\Models\Academico\PensumRequisito;
use App\Models\Academico\Sede;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Catálogo documental UES: no crea cuentas, expedientes, inscripciones ni notas. */
class PensumsUesSeeder extends Seeder
{
    public function run(): void
    {
        $catalogo = json_decode(file_get_contents(__DIR__.'/data/ues/pensums.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->validar($catalogo);

        DB::transaction(function () use ($catalogo) {
            $facultad = Facultad::query()->firstOrCreate(['codigo' => $catalogo['facultad']['codigo']], ['nombre' => $catalogo['facultad']['nombre']]);
            $sede = Sede::query()->firstOrCreate(['codigo' => $catalogo['sede']['codigo']], ['nombre' => $catalogo['sede']['nombre']]);

            foreach ($catalogo['planes'] as $plan) {
                // El prefijo evita confundir carreras demo y planes de otras facultades.
                $codigoCarrera = 'UES-FMP-'.$plan['codigo'];
                $carrera = Carrera::query()->firstOrCreate(['codigo' => $codigoCarrera], [
                    'nombre' => $plan['nombre'],
                    'id_facultad' => $facultad->id_facultad,
                    'descripcion' => "Código UES: {$plan['codigo']}. Plan {$plan['plan']}. ".($plan['modalidad'] ? "Modalidad: {$plan['modalidad']}. " : '')."Fuente: {$plan['fuente']['url']}\n".implode("\n", $plan['notas']),
                    'descripcion_publica' => $plan['nombre'].' — Facultad Multidisciplinaria Paracentral, Universidad de El Salvador.',
                ]);
                if ($carrera->nombre !== $plan['nombre'] || (int) $carrera->id_facultad !== (int) $facultad->id_facultad) {
                    throw new RuntimeException("Conflicto con el catálogo existente: $codigoCarrera.");
                }
                $carrera->sedes()->syncWithoutDetaching([$sede->id_sede]);
                $pensum = Pensum::query()->firstOrCreate([
                    'id_carrera' => $carrera->id_carrera,
                    'version_curricular' => $plan['plan'],
                ]);
                // Una versión ya importada o editada nunca se reescribe, aunque siga en borrador.
                if (! $pensum->wasRecentlyCreated) {
                    continue;
                }

                $porCorrelativo = [];
                foreach ($plan['materias'] as $materia) {
                    // Los espacios sin asignatura elegida se conservan en el JSON documental.
                    if ($materia['tipo'] === 'espacio_electivo') {
                        continue;
                    }
                    $registro = $this->materia($plan, $materia['codigo'], $materia['nombre'], $materia['codigo_oficial']);
                    $porCorrelativo[$materia['correlativo']] = $registro->id_materia;
                    PensumMateria::create([
                        'id_pensum' => $pensum->id_pensum, 'id_materia' => $registro->id_materia,
                        'nivel' => $materia['nivel'], 'orden' => $materia['orden'],
                        'creditos' => $materia['creditos'], 'obligatoria' => true,
                    ]);
                }
                foreach ($plan['materias'] as $materia) {
                    if (! isset($porCorrelativo[$materia['correlativo']])) {
                        continue;
                    }
                    foreach ($materia['prerrequisitos'] as $requisito) {
                        if (isset($porCorrelativo[$requisito])) {
                            PensumRequisito::create([
                                'id_pensum' => $pensum->id_pensum,
                                'id_materia' => $porCorrelativo[$materia['correlativo']],
                                'id_materia_requisito' => $porCorrelativo[$requisito],
                                'tipo' => 'prerrequisito',
                            ]);
                        }
                    }
                }
                foreach ($plan['opciones'] as $indice => $opcion) {
                    $registro = $this->materia($plan, $opcion['codigo_oficial'] ?? 'OPCION-'.($indice + 1), $opcion['nombre'], $opcion['codigo_oficial']);
                    $espacio = collect($plan['materias'])->firstWhere('codigo', $opcion['espacio'] ?? 'ELECTIVA-I');
                    PensumMateria::create([
                        'id_pensum' => $pensum->id_pensum, 'id_materia' => $registro->id_materia,
                        'nivel' => $espacio['nivel'], 'orden' => 100 + $indice,
                        'creditos' => $opcion['creditos'], 'obligatoria' => false,
                    ]);
                }
            }
        });

        $this->command?->info('Catálogo UES cargado: 4 planes en borrador. Fuentes y reglas pendientes: docs/pensums-ues.md.');
    }

    private function materia(array $plan, string $clave, string $nombre, ?string $codigoOficial): Materia
    {
        // Un mismo código corto puede tener otro significado en otro plan o en los datos demo.
        $codigo = 'UES-FMP-'.$plan['codigo'].'-'.$clave;
        $materia = Materia::query()->firstOrCreate(['codigo' => $codigo], [
            'nombre' => $nombre,
            'descripcion' => ($codigoOficial ? "Código oficial UES: $codigoOficial." : 'La fuente no publica código para esta opción; identificador interno.')." Plan {$plan['plan']}. Fuente: {$plan['fuente']['url']}",
        ]);
        if ($materia->nombre !== $nombre || $materia->estado !== 'activa') {
            throw new RuntimeException("Materia existente incompatible: $codigo.");
        }

        return $materia;
    }

    private function validar(array $catalogo): void
    {
        foreach ($catalogo['planes'] as $plan) {
            $materias = collect($plan['materias']);
            $ids = $materias->pluck('correlativo')->all();
            sort($ids);
            if ($materias->count() !== $plan['total_asignaturas']
                || $materias->sum('creditos') !== $plan['total_uv']
                || $ids !== range(1, $plan['total_asignaturas'])
                || $materias->pluck('codigo')->duplicates()->isNotEmpty()) {
                throw new RuntimeException("Totales o identificadores inválidos en {$plan['codigo']}.");
            }
            $porId = $materias->keyBy('correlativo');
            $estado = [];
            $visitar = function (int $id) use (&$visitar, &$estado, $porId): void {
                if (($estado[$id] ?? 0) === 1) {
                    throw new RuntimeException('Prerrequisitos cíclicos en el catálogo UES.');
                }
                if (($estado[$id] ?? 0) === 2) {
                    return;
                }
                $estado[$id] = 1;
                foreach ($porId[$id]['prerrequisitos'] as $requisito) {
                    if (! $porId->has($requisito) || $requisito === $id) {
                        throw new RuntimeException('Prerrequisito inválido en el catálogo UES.');
                    }
                    $visitar($requisito);
                }
                $estado[$id] = 2;
            };
            foreach ($ids as $id) {
                $visitar($id);
            }
        }
    }
}
