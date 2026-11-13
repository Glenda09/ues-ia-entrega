<?php

namespace App\Http\Controllers\Academico;

use App\Models\Academico\Carrera;
use App\Models\Academico\Docente;
use App\Models\Academico\Facultad;
use App\Models\Academico\GestorCarrera;
use App\Models\Academico\Materia;
use App\Models\Academico\Pensum;
use App\Models\Academico\Sede;
use App\Models\Auth\AuthUsuario;
use App\Services\Academico\CatalogoService;
use App\Services\Academico\PensumService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GEN-01, GEN-02 y GEN-03: administración general.
 */
class AdministracionController extends AcademicoController
{
    private const CATALOGOS = ['carreras' => Carrera::class, 'materias' => Materia::class, 'sedes' => Sede::class, 'facultades' => Facultad::class];

    public function __construct(private CatalogoService $catalogo, private PensumService $pensums) {}

    // ---------------- GEN-01 Docentes ----------------

    public function docentes(Request $request): JsonResponse
    {
        $f = $request->validate(['buscar' => 'nullable|string|max:100', 'estado' => 'nullable|in:activo,inactivo']);
        $q = Docente::query()->with('usuario:id_usuario,email,username')
            ->when($f['buscar'] ?? null, fn ($q, $b) => $q->where(fn ($w) => $w->whereRaw('LOWER(codigo) LIKE ?', ['%'.mb_strtolower($b).'%'])->orWhereRaw('LOWER(nombre_academico) LIKE ?', ['%'.mb_strtolower($b).'%'])))
            ->when($f['estado'] ?? null, fn ($q, $e) => $q->where('estado', $e))
            ->orderBy('id_docente');

        return $this->paginado($request, $q, fn ($d) => $this->docenteJson($d));
    }

    public function crearDocente(Request $request): JsonResponse
    {
        $d = $this->validarEstricto($request, [
            'id_usuario' => 'required|uuid|exists:auth_usuario,id_usuario',
            'codigo' => 'required|string|min:1|max:50',
            'nombre_academico' => 'required|string|min:1|max:200',
        ]);
        [$docente, $creado] = $this->catalogo->crearDocente($d, $this->actor($request)->idUsuario);

        return $creado ? $this->creado($this->docenteJson($docente->load('usuario')), 'Docente creado') : $this->ok($this->docenteJson($docente->load('usuario')), 'Docente existente');
    }

    public function docente(Docente $docente): JsonResponse
    {
        return $this->ok($this->docenteJson($docente->load('usuario')));
    }

    public function actualizarDocente(Request $request, Docente $docente): JsonResponse
    {
        $d = $this->validarEstricto($request, [
            'version' => 'required|integer|min:1',
            'nombre_academico' => 'sometimes|string|min:1|max:200',
            'estado' => 'sometimes|in:activo,inactivo',
        ]);
        $r = $this->catalogo->actualizarDocente($docente, $d, $this->actor($request)->idUsuario);

        return $this->ok($this->docenteJson($r['docente']->load('usuario')), 'Docente actualizado', ['grupos_reasignar' => $r['grupos_reasignar']]);
    }

    /** Cuentas candidatas para vincular (búsqueda por correo/usuario). */
    public function cuentas(Request $request): JsonResponse
    {
        $f = $request->validate(['buscar' => 'required|string|min:2|max:100']);
        $b = '%'.mb_strtolower($f['buscar']).'%';
        $cuentas = AuthUsuario::query()->where(fn ($q) => $q->whereRaw('LOWER(email) LIKE ?', [$b])->orWhereRaw('LOWER(username) LIKE ?', [$b]))
            ->orderBy('email')->limit(20)->get(['id_usuario', 'email', 'username']);

        return $this->ok($cuentas);
    }

    private function docenteJson(Docente $d): array
    {
        return [
            'id_docente' => $d->id_docente,
            'id_usuario' => $d->id_usuario,
            'codigo' => $d->codigo,
            'nombre_academico' => $d->nombre_academico,
            'estado' => $d->estado,
            'version' => (int) $d->version,
            'cuenta' => $d->usuario ? ['email' => $d->usuario->email, 'username' => $d->usuario->username] : null,
        ];
    }

    // ---------------- GEN-02 Catálogos ----------------

    public function listar(Request $request): JsonResponse
    {
        $modelo = $this->modeloCatalogo($request);
        $f = $request->validate(['buscar' => 'nullable|string|max:100', 'estado' => 'nullable|in:activa,archivada']);
        $q = $modelo::query()
            ->when($f['buscar'] ?? null, fn ($q, $b) => $q->where(fn ($w) => $w->whereRaw('LOWER(codigo) LIKE ?', ['%'.mb_strtolower($b).'%'])->orWhereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($b).'%'])))
            ->when($f['estado'] ?? null, fn ($q, $e) => $q->where('estado', $e))
            ->when($modelo === Carrera::class, fn ($q) => $q->with(['sedes:id_sede,codigo,nombre', 'facultad:id_facultad,codigo,nombre']))
            ->orderBy((new $modelo)->getKeyName());

        return $this->paginado($request, $q, fn ($r) => $r->toArray());
    }

    public function crear(Request $request): JsonResponse
    {
        $modelo = $this->modeloCatalogo($request);
        $d = $this->validarEstricto($request, $this->reglasCatalogo($modelo, true));
        $r = $this->catalogo->crearCatalogo($modelo, $d, $this->actor($request)->idUsuario);

        return $this->creado($r->fresh()->load($modelo === Carrera::class ? ['sedes', 'facultad'] : []));
    }

    public function mostrar(Request $request, string $id): JsonResponse
    {
        $modelo = $this->modeloCatalogo($request);

        return $this->ok($modelo::query()->when($modelo === Carrera::class, fn ($q) => $q->with(['sedes', 'facultad']))->findOrFail($id));
    }

    public function actualizar(Request $request, string $id): JsonResponse
    {
        $modelo = $this->modeloCatalogo($request);
        $registro = $modelo::query()->findOrFail($id);
        $d = $this->validarEstricto($request, $this->reglasCatalogo($modelo, false));
        $r = $this->catalogo->actualizarCatalogo($registro, $d, $this->actor($request)->idUsuario);

        return $this->ok($r->fresh()->load($modelo === Carrera::class ? ['sedes', 'facultad'] : []), 'Registro actualizado');
    }

    /** @return class-string<Model> */
    private function modeloCatalogo(Request $request): string
    {
        return self::CATALOGOS[$request->route()->defaults['catalogo']];
    }

    private function reglasCatalogo(string $modelo, bool $alta): array
    {
        $req = $alta ? 'required' : 'sometimes';
        $reglas = [
            'codigo' => "$req|string|min:1|max:50",
            'nombre' => "$req|string|min:1|max:200",
            'estado' => 'sometimes|in:activa,archivada',
        ];
        if (! $alta) {
            $reglas['version'] = 'required|integer|min:1';
        }
        if (in_array($modelo, [Carrera::class, Materia::class], true)) {
            $reglas['descripcion'] = 'nullable|string|max:5000';
        }
        if ($modelo === Carrera::class) {
            $reglas['descripcion_publica'] = 'nullable|string|max:5000';
            $reglas['clasificacion'] = 'sometimes|in:general,profesorado';
            $reglas['id_facultad'] = 'nullable|integer|exists:facultad,id_facultad';
            $reglas['sedes'] = 'sometimes|array';
            $reglas['sedes.*'] = 'integer|exists:sede,id_sede';
        }

        return $reglas;
    }

    // ---------------- Gestores ----------------

    public function misCarreras(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $q = $actor->limitarPorCarrera(Carrera::query(), 'id_carrera', null)->orderBy('id_carrera');

        return $this->paginado($request, $q, fn ($c) => $c->only(['id_carrera', 'codigo', 'nombre', 'estado', 'descripcion_publica']));
    }

    public function gestores(Carrera $carrera): JsonResponse
    {
        $g = GestorCarrera::query()->where('id_carrera', $carrera->id_carrera)
            ->join('auth_usuario as u', 'u.id_usuario', '=', 'gestor_carrera.id_usuario')
            ->orderBy('gestor_carrera.id_gestor_carrera')
            ->get(['gestor_carrera.id_usuario', 'u.email', 'u.username', 'gestor_carrera.created_at']);

        return $this->ok($g);
    }

    public function asignarGestor(Request $request, Carrera $carrera, string $usuario): JsonResponse
    {
        $this->validarEstricto($request, []);
        $v = $this->catalogo->asignarGestor($carrera, $usuario, $this->actor($request)->idUsuario);

        return $v->wasRecentlyCreated ? $this->creado($v, 'Gestor asignado') : $this->ok($v, 'Gestor ya asignado');
    }

    public function revocarGestor(Request $request, Carrera $carrera, string $usuario): JsonResponse
    {
        $this->catalogo->revocarGestor($carrera, $usuario, $this->actor($request)->idUsuario);

        return $this->ok(null, 'Gestor revocado');
    }

    // ---------------- GEN-03 Pensums ----------------

    public function pensumsCarrera(Request $request, Carrera $carrera): JsonResponse
    {
        $this->actor($request)->exigirCarrera($carrera->id_carrera, 'academico_pensum_ver');

        return $this->paginado($request, Pensum::query()->where('id_carrera', $carrera->id_carrera)->orderBy('id_pensum'), fn ($p) => $p->toArray());
    }

    public function crearPensum(Request $request, Carrera $carrera): JsonResponse
    {
        $d = $this->validarEstricto($request, [
            'version_curricular' => 'required|string|min:1|max:50',
            'id_pensum_origen' => 'nullable|integer',
        ]);
        $p = $this->pensums->crear($carrera, $d['version_curricular'], $d['id_pensum_origen'] ?? null, $this->actor($request)->idUsuario);

        return $this->creado($this->pensums->detalle($p), 'Pensum creado');
    }

    public function pensum(Request $request, Pensum $pensum): JsonResponse
    {
        $this->actor($request)->exigirCarrera($pensum->id_carrera, 'academico_pensum_ver');

        return $this->ok($this->pensums->detalle($pensum));
    }

    public function actualizarPensum(Request $request, Pensum $pensum): JsonResponse
    {
        $d = $this->validarEstricto($request, [
            'version' => 'required|integer|min:1',
            'vigente_desde' => 'sometimes|nullable|date_format:Y-m-d',
            'vigente_hasta' => 'sometimes|nullable|date_format:Y-m-d|after:vigente_desde',
            'materias' => 'sometimes|array',
            'materias.*.id_materia' => 'required|integer|exists:materia,id_materia',
            'materias.*.nivel' => 'required|integer|min:1|max:20',
            'materias.*.orden' => 'nullable|integer|min:1|max:100',
            'materias.*.creditos' => 'required|numeric|gt:0|max:99',
            'materias.*.obligatoria' => 'nullable|boolean',
            'requisitos' => 'sometimes|array',
            'requisitos.*.id_materia' => 'required|integer',
            'requisitos.*.id_materia_requisito' => 'required|integer',
            'requisitos.*.tipo' => 'nullable|in:prerrequisito,correquisito',
            'equivalencias' => 'sometimes|array',
            'equivalencias.*.id_materia_origen' => 'required|integer|exists:materia,id_materia',
            'equivalencias.*.id_materia_destino' => 'required|integer',
        ]);
        $p = $this->pensums->actualizarBorrador($pensum, $d, $this->actor($request)->idUsuario);

        return $this->ok($this->pensums->detalle($p), 'Pensum actualizado');
    }

    public function transicionPensum(Request $request, Pensum $pensum): JsonResponse
    {
        $d = $this->validarEstricto($request, ['estado' => 'required|in:publicado,retirado', 'version' => 'required|integer|min:1']);
        $p = $this->pensums->transicion($pensum, $d['estado'], (int) $d['version'], $this->actor($request)->idUsuario);

        return $this->ok($this->pensums->detalle($p), 'Estado del pensum actualizado');
    }
}
