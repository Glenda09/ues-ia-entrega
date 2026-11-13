<?php

namespace App\Support\Academico;

use App\Models\Academico\GestorCarrera;
use Illuminate\Database\Eloquent\Builder;

/**
 * Identidad resuelta por JWT para operaciones académicas.
 *
 * Los servicios reciben esta identidad y aplican el alcance explícitamente.
 */
final class Actor
{
    /** @var array<int,int>|null */
    private ?array $carrerasGestionadas = null;

    /**
     * @param  array<int, string>  $permisos  Permisos efectivos.
     */
    public function __construct(
        public readonly string $idUsuario,
        public readonly array $permisos,
    ) {}

    public function esRoot(): bool
    {
        return in_array('root', $this->permisos, true);
    }

    public function tiene(string ...$permisos): bool
    {
        if ($this->esRoot()) {
            return true;
        }

        return array_intersect($permisos, $this->permisos) !== [];
    }

    /**
     * Alcance institucional: administración general o cuenta técnica.
     * Se concede por un permiso institucional concreto de la operación.
     */
    public function institucional(string $permisoInstitucional): bool
    {
        return $this->esRoot() || in_array($permisoInstitucional, $this->permisos, true);
    }

    /**
     * IDs de carreras con vínculo vigente `gestor_carrera`.
     *
     * @return array<int, int>
     */
    public function carrerasGestionadas(): array
    {
        return $this->carrerasGestionadas ??= GestorCarrera::query()
            ->where('id_usuario', $this->idUsuario)
            ->pluck('id_carrera')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Comprueba alcance sobre una carrera: institucional por permiso dado o carrera asignada.
     */
    public function alcanzaCarrera(int $idCarrera, ?string $permisoInstitucional = 'academico_alcance_institucional'): bool
    {
        if ($permisoInstitucional && $this->institucional($permisoInstitucional)) {
            return true;
        }

        return in_array($idCarrera, $this->carrerasGestionadas(), true);
    }

    /**
     * Lanza 404 si la carrera está fuera de alcance (no revela existencia).
     */
    public function exigirCarrera(int $idCarrera, ?string $permisoInstitucional = 'academico_alcance_institucional'): void
    {
        if (! $this->alcanzaCarrera($idCarrera, $permisoInstitucional)) {
            throw DominioException::noEncontrado();
        }
    }

    /**
     * Restringe una consulta por carrera según el alcance. Debe aplicarse antes de filtros y paginación.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $consulta
     */
    public function limitarPorCarrera($consulta, string $columna = 'id_carrera', ?string $permisoInstitucional = 'academico_alcance_institucional')
    {
        if ($permisoInstitucional && $this->institucional($permisoInstitucional)) {
            return $consulta;
        }

        return $consulta->whereIn($columna, $this->carrerasGestionadas() ?: [0]);
    }
}
