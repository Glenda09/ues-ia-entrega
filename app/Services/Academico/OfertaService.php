<?php

namespace App\Services\Academico;

use App\Models\Academico\AsignacionDocente;
use App\Models\Academico\CicloCarrera;
use App\Models\Academico\Docente;
use App\Models\Academico\Grupo;
use App\Models\Academico\HorarioGrupo;
use App\Models\Academico\Inscripcion;
use App\Models\Academico\Materia;
use App\Models\Academico\OfertaMateria;
use App\Support\Academico\DominioException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CAR-02: oferta de materias, grupos, horarios y asignación docente.
 */
class OfertaService
{
    public function __construct(private AuditoriaService $auditoria, private CatalogoService $catalogo, private ConfiguracionService $configuracion) {}

    public function crearOferta(CicloCarrera $ciclo, int $idMateria, string $actor): OfertaMateria
    {
        return DB::transaction(function () use ($ciclo, $idMateria, $actor) {
            $ciclo = CicloCarrera::query()->lockForUpdate()->findOrFail($ciclo->id_ciclo_carrera);
            if ($ciclo->estado === 'cerrado') {
                throw DominioException::conflicto('CICLO_CERRADO', 'El ciclo está cerrado.');
            }
            $this->exigirMateriaOfrecible($ciclo, $idMateria);
            $existe = OfertaMateria::query()->where(['id_ciclo_carrera' => $ciclo->id_ciclo_carrera, 'id_materia' => $idMateria])->first();
            if ($existe) {
                return $existe;
            }
            $oferta = OfertaMateria::create(['id_ciclo_carrera' => $ciclo->id_ciclo_carrera, 'id_materia' => $idMateria, 'creado_por' => $actor]);
            $this->auditoria->registrar($actor, 'oferta.crear', 'oferta_materia', $oferta->id_oferta_materia);

            return $oferta;
        });
    }

    /**
     * @param  array{codigo:string, cupo?:int, horarios?:array, id_docente?:int}  $d
     */
    public function crearGrupo(OfertaMateria $oferta, array $d, string $actor): Grupo
    {
        return DB::transaction(function () use ($oferta, $d, $actor) {
            if (Grupo::query()->where('id_oferta_materia', $oferta->id_oferta_materia)->where('codigo', $d['codigo'])->exists()) {
                throw ValidationException::withMessages(['codigo' => 'El código de grupo ya existe en la oferta.']);
            }
            $grupo = Grupo::create([
                'id_oferta_materia' => $oferta->id_oferta_materia,
                'codigo' => $d['codigo'],
                'cupo' => $d['cupo'] ?? (int) $this->configuracion->valor('inscripcion.cupo_default', 30),
                'estado' => 'borrador',
            ]);
            if (isset($d['horarios'])) {
                $this->guardarHorarios($grupo, $d['horarios']);
            }
            if (! empty($d['id_docente'])) {
                $this->asignarDocente($grupo, (int) $d['id_docente'], $actor);
            }
            $this->auditoria->registrar($actor, 'grupo.crear', 'grupo', $grupo->id_grupo);

            return $grupo;
        });
    }

    /**
     * PATCH de grupo: cupo, horarios, docente y transición de estado con versión.
     */
    public function actualizarGrupo(Grupo $grupo, array $d, string $actor): Grupo
    {
        return DB::transaction(function () use ($grupo, $d, $actor) {
            $ciclo = CicloCarrera::query()->lockForUpdate()->findOrFail($grupo->oferta->id_ciclo_carrera);
            $grupo = Grupo::query()->lockForUpdate()->findOrFail($grupo->id_grupo);
            if (isset($d['estado']) && $d['estado'] === $grupo->estado && count($d) === 2) {
                return $grupo;
            }
            $this->catalogo->exigirVersion($grupo, (int) $d['version']);
            $activos = Inscripcion::query()->where('id_grupo', $grupo->id_grupo)->where('estado', 'activa')->count();
            if ($grupo->estado === 'cancelado') {
                throw DominioException::conflicto('GRUPO_CANCELADO', 'El grupo está cancelado.');
            }
            if (isset($d['cupo'])) {
                if ((int) $d['cupo'] < $activos) {
                    throw DominioException::conflicto('CUPO_INFERIOR_A_INSCRITOS', 'El cupo no puede ser menor que los inscritos activos.', ['inscritos' => $activos]);
                }
                $grupo->cupo = (int) $d['cupo'];
            }
            if (array_key_exists('horarios', $d)) {
                if ($activos > 0 && $this->configuracion->valor('inscripcion.validar_choque_horario')) {
                    $this->validarCambioHorarioConInscritos($grupo, $d['horarios']);
                }
                $this->guardarHorarios($grupo, $d['horarios']);
            }
            if (! empty($d['id_docente'])) {
                $this->asignarDocente($grupo, (int) $d['id_docente'], $actor);
            }
            if (isset($d['estado']) && $d['estado'] !== $grupo->estado) {
                $permitidas = ['borrador' => ['publicado', 'cancelado'], 'publicado' => ['cancelado']];
                if (! in_array($d['estado'], $permitidas[$grupo->estado] ?? [], true)) {
                    throw DominioException::conflicto('TRANSICION_INVALIDA', "No se permite pasar de {$grupo->estado} a {$d['estado']}.");
                }
                if ($d['estado'] === 'cancelado' && $activos > 0) {
                    throw DominioException::conflicto('GRUPO_CON_INSCRIPCIONES', 'El grupo tiene inscripciones activas.');
                }
                if ($d['estado'] === 'publicado') {
                    $this->validarPublicacionGrupo($grupo, $ciclo);
                }
                $grupo->estado = $d['estado'];
            }
            $grupo->version++;
            $grupo->save();
            $this->auditoria->registrar($actor, 'grupo.actualizar', 'grupo', $grupo->id_grupo, null, ['estado' => $grupo->estado, 'cupo' => $grupo->cupo]);

            return $grupo;
        });
    }

    /**
     * Cierra la asignación vigente y crea la nueva. Docente inactivo no puede asignarse.
     */
    public function asignarDocente(Grupo $grupo, int $idDocente, string $actor): void
    {
        $docente = Docente::query()->findOrFail($idDocente);
        if ($docente->estado !== 'activo') {
            throw DominioException::conflicto('DOCENTE_INACTIVO', 'El docente está inactivo.');
        }
        $vigente = AsignacionDocente::query()->where('id_grupo', $grupo->id_grupo)->whereNull('hasta')->lockForUpdate()->first();
        if ($vigente && (int) $vigente->id_docente === $idDocente) {
            return;
        }
        if ($vigente) {
            $vigente->update(['hasta' => now('UTC')]);
        }
        AsignacionDocente::create(['id_grupo' => $grupo->id_grupo, 'id_docente' => $idDocente, 'desde' => now('UTC'), 'asignado_por' => $actor]);
        $this->auditoria->registrar($actor, 'grupo.asignar_docente', 'grupo', $grupo->id_grupo, null, ['id_docente' => $idDocente, 'anterior' => $vigente?->id_docente]);
    }

    public function docenteVigente(int $idGrupo): ?Docente
    {
        $a = AsignacionDocente::query()->where('id_grupo', $idGrupo)->whereNull('hasta')->first();

        return $a ? Docente::find($a->id_docente) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function grupoJson(Grupo $g): array
    {
        $docente = $this->docenteVigente($g->id_grupo);
        $inscritos = Inscripcion::query()->where('id_grupo', $g->id_grupo)->where('estado', 'activa')->count();

        return [
            'id_grupo' => $g->id_grupo,
            'id_oferta_materia' => $g->id_oferta_materia,
            'codigo' => $g->codigo,
            'cupo' => (int) $g->cupo,
            'inscritos' => $inscritos,
            'disponibles' => max(0, (int) $g->cupo - $inscritos),
            'estado' => $g->estado,
            'version' => (int) $g->version,
            'horarios' => HorarioGrupo::query()->where('id_grupo', $g->id_grupo)->orderBy('dia')->orderBy('hora_inicio')->get(['dia', 'hora_inicio', 'hora_fin']),
            'docente' => $docente ? ['id_docente' => $docente->id_docente, 'codigo' => $docente->codigo, 'nombre_academico' => $docente->nombre_academico, 'estado' => $docente->estado] : null,
        ];
    }

    /**
     * Materia ofrecible: pertenece a un pensum publicado de la carrera.
     */
    private function exigirMateriaOfrecible(CicloCarrera $ciclo, int $idMateria): void
    {
        $materia = Materia::query()->findOrFail($idMateria);
        if ($materia->estado !== 'activa') {
            throw DominioException::conflicto('MATERIA_NO_OFRECIBLE', 'La materia está archivada.');
        }
        $pertenece = DB::table('pensum_materia as pm')->join('pensum as p', 'p.id_pensum', '=', 'pm.id_pensum')
            ->where('p.id_carrera', $ciclo->id_carrera)->where('p.estado', 'publicado')->where('pm.id_materia', $idMateria)->exists();
        if (! $pertenece) {
            throw DominioException::conflicto('MATERIA_NO_OFRECIBLE', 'La materia no pertenece a un pensum publicado de la carrera.');
        }
    }

    private function validarPublicacionGrupo(Grupo $grupo, CicloCarrera $ciclo): void
    {
        if ($ciclo->estado !== 'publicado') {
            throw DominioException::conflicto('CICLO_NO_PUBLICADO', 'El ciclo debe estar publicado.');
        }
        $this->exigirMateriaOfrecible($ciclo, (int) $grupo->oferta->id_materia);
        if ((int) $grupo->cupo <= 0) {
            throw ValidationException::withMessages(['cupo' => 'El cupo debe ser positivo.']);
        }
        if (! HorarioGrupo::query()->where('id_grupo', $grupo->id_grupo)->exists()) {
            throw ValidationException::withMessages(['horarios' => 'El grupo requiere al menos un horario.']);
        }
        $docente = $this->docenteVigente($grupo->id_grupo);
        if (! $docente) {
            throw ValidationException::withMessages(['id_docente' => 'El grupo requiere docente responsable.']);
        }
        if ($docente->estado !== 'activo') {
            throw DominioException::conflicto('DOCENTE_INACTIVO', 'El docente asignado está inactivo.');
        }
    }

    /**
     * Valida y reemplaza horarios: día 1-7, HH:mm, inicio < fin, sin solapes internos.
     */
    private function guardarHorarios(Grupo $grupo, array $horarios): void
    {
        $norm = self::normalizarHorarios($horarios);
        HorarioGrupo::query()->where('id_grupo', $grupo->id_grupo)->delete();
        foreach ($norm as $h) {
            HorarioGrupo::create(['id_grupo' => $grupo->id_grupo] + $h);
        }
    }

    /**
     * @return array<int, array{dia:int, hora_inicio:string, hora_fin:string}>
     */
    public static function normalizarHorarios(array $horarios): array
    {
        $norm = [];
        foreach ($horarios as $i => $h) {
            $dia = (int) ($h['dia'] ?? 0);
            $ini = (string) ($h['hora_inicio'] ?? '');
            $fin = (string) ($h['hora_fin'] ?? '');
            if ($dia < 1 || $dia > 7 || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $ini) || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$|^24:00$/', $fin) || $ini >= $fin) {
                throw ValidationException::withMessages(["horarios.$i" => 'Horario inválido: día 1-7 y hora inicial anterior a la final (HH:mm).']);
            }
            foreach ($norm as $otro) {
                if ($otro['dia'] === $dia && $ini < $otro['hora_fin'] && $otro['hora_inicio'] < $fin) {
                    throw ValidationException::withMessages(["horarios.$i" => 'Horarios superpuestos en el grupo.']);
                }
            }
            $norm[] = ['dia' => $dia, 'hora_inicio' => $ini, 'hora_fin' => $fin];
        }

        return $norm;
    }

    /**
     * Semiabiertos: adyacentes no chocan.
     */
    public static function chocan(array $a, array $b): bool
    {
        foreach ($a as $x) {
            foreach ($b as $y) {
                if ((int) $x['dia'] === (int) $y['dia'] && $x['hora_inicio'] < $y['hora_fin'] && $y['hora_inicio'] < $x['hora_fin']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Un cambio de horario no puede generar choque para estudiantes inscritos activos.
     */
    private function validarCambioHorarioConInscritos(Grupo $grupo, array $nuevos): void
    {
        $nuevos = self::normalizarHorarios($nuevos);
        $expedientes = Inscripcion::query()->where('id_grupo', $grupo->id_grupo)->where('estado', 'activa')->orderBy('id_expediente')->pluck('id_expediente');
        DB::table('expediente')->whereIn('id_expediente', $expedientes)->orderBy('id_expediente')->lockForUpdate()->get(['id_expediente']);
        $otras = Inscripcion::query()->whereIn('id_expediente', $expedientes)->where('estado', 'activa')
            ->where('id_ciclo_carrera', $grupo->oferta->id_ciclo_carrera)->where('id_grupo', '!=', $grupo->id_grupo)->pluck('id_grupo')->unique();
        foreach ($otras as $idOtro) {
            $h = HorarioGrupo::query()->where('id_grupo', $idOtro)->get(['dia', 'hora_inicio', 'hora_fin'])->toArray();
            if (self::chocan($nuevos, $h)) {
                throw DominioException::conflicto('HORARIO_AFECTA_INSCRIPCIONES', 'El nuevo horario choca con inscripciones vigentes de estudiantes del grupo.');
            }
        }
    }
}
