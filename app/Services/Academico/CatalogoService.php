<?php

namespace App\Services\Academico;

use App\Models\Academico\Carrera;
use App\Models\Academico\CicloCarrera;
use App\Models\Academico\ConvocatoriaIngreso;
use App\Models\Academico\Docente;
use App\Models\Academico\GestorCarrera;
use App\Models\Academico\Grupo;
use App\Models\Academico\Materia;
use App\Models\Auth\AuthUsuario;
use App\Models\Auth\AuthUsuarioPerfil;
use App\Services\Auth\PermisosEfectivosService;
use App\Support\Academico\Codigo;
use App\Support\Academico\DominioException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * GEN-01 y GEN-02: docentes, carreras, materias, sedes, facultades y gestores.
 */
class CatalogoService
{
    public function __construct(private AuditoriaService $auditoria, private PermisosEfectivosService $permisos) {}

    /**
     * Alta de docente vinculando una cuenta existente. Reintento equivalente devuelve el registro.
     *
     * @param  array{id_usuario:string,codigo:string,nombre_academico:string}  $d
     * @return array{0: Docente, 1: bool} [docente, creado]
     */
    public function crearDocente(array $d, string $actor): array
    {
        $codigo = Codigo::normalizar($d['codigo']);

        return DB::transaction(function () use ($d, $codigo, $actor) {
            AuthUsuario::query()->lockForUpdate()->findOrFail($d['id_usuario']);
            $existente = Docente::query()->where('id_usuario', $d['id_usuario'])->first();
            if ($existente) {
                if ($existente->codigo === $codigo && $existente->nombre_academico === $d['nombre_academico']) {
                    return [$existente, false];
                }
                throw DominioException::conflicto('DOCENTE_EXISTENTE', 'La cuenta ya tiene un registro docente con datos distintos.');
            }
            if (Docente::query()->where('codigo', $codigo)->exists()) {
                throw ValidationException::withMessages(['codigo' => 'El código docente ya existe.']);
            }
            $docente = Docente::create([
                'id_usuario' => $d['id_usuario'],
                'codigo' => $codigo,
                'nombre_academico' => $d['nombre_academico'],
                'estado' => 'activo',
                'creado_por' => $actor,
            ]);
            $this->asegurarPerfil($d['id_usuario'], 'academico_docente', $actor);
            $this->auditoria->registrar($actor, 'docente.crear', 'docente', $docente->id_docente);

            return [$docente, true];
        });
    }

    /**
     * Edición de docente con versión; desactivar bloquea operación y nuevas asignaciones.
     *
     * @return array{docente: Docente, grupos_reasignar: array<int, int>}
     */
    public function actualizarDocente(Docente $docente, array $d, string $actor): array
    {
        return DB::transaction(function () use ($docente, $d, $actor) {
            $docente = Docente::query()->lockForUpdate()->findOrFail($docente->id_docente);
            $this->exigirVersion($docente, (int) $d['version']);
            if (isset($d['nombre_academico'])) {
                $docente->nombre_academico = $d['nombre_academico'];
            }
            if (isset($d['estado'])) {
                $docente->estado = $d['estado'];
            }
            if ($docente->isDirty()) {
                $docente->version++;
                $docente->save();
                $this->auditoria->registrar($actor, 'docente.actualizar', 'docente', $docente->id_docente, null, ['estado' => $docente->estado]);
            }
            $grupos = [];
            if ($docente->estado === 'inactivo') {
                $grupos = DB::table('asignacion_docente as a')->join('grupo as g', 'g.id_grupo', '=', 'a.id_grupo')
                    ->where('a.id_docente', $docente->id_docente)->whereNull('a.hasta')->where('g.estado', 'publicado')
                    ->pluck('g.id_grupo')->map(fn ($i) => (int) $i)->all();
            }

            return ['docente' => $docente, 'grupos_reasignar' => $grupos];
        });
    }

    /**
     * Crea un registro de catálogo con código normalizado único.
     *
     * @param  class-string<Model>  $modelo
     */
    public function crearCatalogo(string $modelo, array $d, string $actor): Model
    {
        $d['codigo'] = Codigo::normalizar($d['codigo']);
        if ($modelo::query()->where('codigo', $d['codigo'])->exists()) {
            throw ValidationException::withMessages(['codigo' => 'El código ya existe.']);
        }

        return DB::transaction(function () use ($modelo, $d, $actor) {
            $sedes = $d['sedes'] ?? null;
            unset($d['sedes']);
            $registro = $modelo::create($d);
            if ($registro instanceof Carrera && is_array($sedes)) {
                $registro->sedes()->sync($sedes);
            }
            $this->auditoria->registrar($actor, $registro->getTable().'.crear', $registro->getTable(), $registro->getKey());

            return $registro;
        });
    }

    /**
     * Actualiza un registro de catálogo con versión. El código es inmutable si está referenciado.
     */
    public function actualizarCatalogo(Model $registro, array $d, string $actor): Model
    {
        return DB::transaction(function () use ($registro, $d, $actor) {
            $registro = $registro->newQuery()->lockForUpdate()->findOrFail($registro->getKey());
            $this->exigirVersion($registro, (int) $d['version']);
            unset($d['version']);
            $sedes = $d['sedes'] ?? null;
            unset($d['sedes']);

            if (isset($d['codigo'])) {
                $d['codigo'] = Codigo::normalizar($d['codigo']);
                if ($d['codigo'] !== $registro->codigo) {
                    if ($this->referenciado($registro)) {
                        throw ValidationException::withMessages(['codigo' => 'El código está referenciado y no puede modificarse.']);
                    }
                    if ($registro->newQuery()->where('codigo', $d['codigo'])->exists()) {
                        throw ValidationException::withMessages(['codigo' => 'El código ya existe.']);
                    }
                }
            }
            if (($d['estado'] ?? null) === 'archivada' && $registro->estado !== 'archivada') {
                $this->validarArchivado($registro);
            }
            $registro->fill($d);
            $cambio = $registro->isDirty();
            if ($registro instanceof Carrera && is_array($sedes)) {
                $cambios = $registro->sedes()->sync($sedes);
                $cambio = $cambio || array_filter($cambios) !== [];
            }
            if ($cambio) {
                $registro->version++;
                $registro->save();
                $this->auditoria->registrar($actor, $registro->getTable().'.actualizar', $registro->getTable(), $registro->getKey(), null, ['estado' => $registro->estado ?? null]);
            }

            return $registro;
        });
    }

    /**
     * Asigna (idempotente) un gestor a una carrera. La cuenta debe tener un perfil de gestor.
     */
    public function asignarGestor(Carrera $carrera, string $idUsuario, string $actor): GestorCarrera
    {
        return DB::transaction(function () use ($carrera, $idUsuario, $actor) {
            AuthUsuario::query()->lockForUpdate()->findOrFail($idUsuario);
            $tienePerfil = AuthUsuarioPerfil::query()->where('id_usuario', $idUsuario)
                ->whereIn('codigo_perfil', ['academico_gestor_carrera', 'academico_gestor_expediente'])->exists();
            if (! $tienePerfil) {
                throw ValidationException::withMessages(['id_usuario' => 'La cuenta requiere un perfil de gestor de carrera o de expediente.']);
            }
            $vinculo = GestorCarrera::query()->firstOrCreate(
                ['id_usuario' => $idUsuario, 'id_carrera' => $carrera->id_carrera],
                ['asignado_por' => $actor]
            );
            if ($vinculo->wasRecentlyCreated) {
                $this->auditoria->registrar($actor, 'gestor.asignar', 'carrera', $carrera->id_carrera, null, ['id_usuario' => $idUsuario]);
            }

            return $vinculo;
        });
    }

    public function revocarGestor(Carrera $carrera, string $idUsuario, string $actor): void
    {
        DB::transaction(function () use ($carrera, $idUsuario, $actor) {
            $borrados = GestorCarrera::query()->where(['id_usuario' => $idUsuario, 'id_carrera' => $carrera->id_carrera])->delete();
            if ($borrados) {
                $this->auditoria->registrar($actor, 'gestor.revocar', 'carrera', $carrera->id_carrera, null, ['id_usuario' => $idUsuario]);
            }
        });
    }

    public function asegurarPerfil(string $idUsuario, string $perfil, string $actor): void
    {
        $existe = DB::table('auth_usuario_perfil')->where(['id_usuario' => $idUsuario, 'codigo_perfil' => $perfil])->first();
        if ($existe && $existe->deleted_at) {
            DB::table('auth_usuario_perfil')->where(['id_usuario' => $idUsuario, 'codigo_perfil' => $perfil])->update(['deleted_at' => null, 'updated_at' => now()]);
        } elseif (! $existe) {
            AuthUsuarioPerfil::create(['id_usuario' => $idUsuario, 'codigo_perfil' => $perfil, 'creado_por' => $actor]);
        }
        $this->permisos->invalidarUsuario($idUsuario);
    }

    public function exigirVersion(Model $registro, int $version): void
    {
        if ((int) $registro->version !== $version) {
            throw DominioException::conflicto('VERSION_CONFLICTO', 'El registro fue modificado por otra persona. Recarga los datos.', ['version_actual' => (int) $registro->version]);
        }
    }

    private function referenciado(Model $registro): bool
    {
        if ($registro instanceof Carrera) {
            return DB::table('pensum')->where('id_carrera', $registro->id_carrera)->exists()
                || DB::table('convocatoria_ingreso')->where('id_carrera', $registro->id_carrera)->exists();
        }
        if ($registro instanceof Materia) {
            return DB::table('pensum_materia')->where('id_materia', $registro->id_materia)->exists();
        }

        return false;
    }

    private function validarArchivado(Model $registro): void
    {
        if ($registro instanceof Carrera) {
            $abierta = ConvocatoriaIngreso::query()->where('id_carrera', $registro->id_carrera)->where('estado', 'publicada')->exists();
            $ciclo = CicloCarrera::query()->where('id_carrera', $registro->id_carrera)->where('estado', 'publicado')->exists();
            if ($abierta || $ciclo) {
                throw DominioException::conflicto('CARRERA_CON_OPERACION_VIGENTE', 'La carrera tiene una convocatoria abierta o un ciclo publicado.');
            }
        }
        if ($registro instanceof Materia) {
            $vigente = Grupo::query()->where('estado', 'publicado')
                ->whereHas('oferta', fn ($q) => $q->where('id_materia', $registro->id_materia)->whereHas('ciclo', fn ($c) => $c->where('estado', 'publicado')))
                ->exists();
            if ($vigente) {
                throw DominioException::conflicto('MATERIA_CON_GRUPO_VIGENTE', 'La materia tiene grupos vigentes.');
            }
        }
    }
}
