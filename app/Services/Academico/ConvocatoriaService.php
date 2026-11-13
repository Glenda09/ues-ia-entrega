<?php

namespace App\Services\Academico;

use App\Models\Academico\Carrera;
use App\Models\Academico\CarreraOfertaAdmision;
use App\Models\Academico\ConvocatoriaIngreso;
use App\Models\Academico\RequisitoIngreso;
use App\Support\Academico\DominioException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CAR-03: convocatorias de admisión, requisitos versionados y combinaciones de oferta.
 */
class ConvocatoriaService
{
    /** Campos del formulario que las condiciones pueden referenciar (FORMULARIO.md). */
    public const CAMPOS_CONDICION = ['modalidad_ingreso', 'modalidad_estudio', 'nacionalidad', 'mayor_edad', 'estado_graduacion', 'id_carrera', 'clasificacion_carrera'];

    public function __construct(private AuditoriaService $auditoria, private CatalogoService $catalogo, private ConfiguracionService $configuracion) {}

    /**
     * Requisitos iniciales derivados de la nota D03 (configurables por convocatoria).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function requisitosIniciales(): array
    {
        $origen = 'Nota formulario UES (D03)';
        $salvAdulto = ['all' => [['campo' => 'nacionalidad', 'op' => 'eq', 'valor' => 'salvadoreña'], ['campo' => 'mayor_edad', 'op' => 'eq', 'valor' => true]]];
        $extranjero = ['all' => [['campo' => 'nacionalidad', 'op' => 'neq', 'valor' => 'salvadoreña']]];
        $especial = ['all' => [['campo' => 'modalidad_ingreso', 'op' => 'eq', 'valor' => 'especial']]];

        return [
            ['codigo' => 'fotografia', 'tipo' => 'anexo', 'nombre' => 'Fotografía', 'descripcion' => 'JPG real de 150 × 195 píxeles, a color, presentación formal y fondo liso.', 'obligatorio' => true, 'etapa' => 'envio', 'condicion' => null, 'tipos_permitidos' => ['image/jpeg'], 'origen' => $origen],
            ['codigo' => 'dui', 'tipo' => 'anexo', 'nombre' => 'DUI', 'descripcion' => 'Documento Único de Identidad (salvadoreño mayor de edad).', 'obligatorio' => true, 'etapa' => 'envio', 'condicion' => $salvAdulto, 'tipos_permitidos' => ['application/pdf', 'image/jpeg', 'image/png'], 'origen' => $origen],
            ['codigo' => 'nie', 'tipo' => 'campo', 'nombre' => 'NIE', 'descripcion' => 'Número de Identificación Estudiantil.', 'obligatorio' => true, 'etapa' => 'envio', 'condicion' => null, 'tipos_permitidos' => null, 'origen' => $origen],
            ['codigo' => 'pasaporte', 'tipo' => 'anexo', 'nombre' => 'Pasaporte', 'descripcion' => 'Pasaporte vigente con país emisor (aspirante extranjero).', 'obligatorio' => true, 'etapa' => 'envio', 'condicion' => $extranjero, 'tipos_permitidos' => ['application/pdf', 'image/jpeg', 'image/png'], 'origen' => $origen],
            ['codigo' => 'titulo_bachiller', 'tipo' => 'anexo', 'nombre' => 'Título de bachiller', 'descripcion' => 'Graduados lo presentan al enviar; próximos a graduarse antes de la aceptación.', 'obligatorio' => true, 'etapa' => 'aceptacion', 'condicion' => null, 'tipos_permitidos' => ['application/pdf', 'image/jpeg', 'image/png'], 'origen' => $origen],
            ['codigo' => 'resultado_paes', 'tipo' => 'anexo', 'nombre' => 'Resultado PAES/AVANZO', 'descripcion' => 'Constancia del resultado de la prueba.', 'obligatorio' => false, 'etapa' => 'envio', 'condicion' => null, 'tipos_permitidos' => ['application/pdf', 'image/jpeg', 'image/png'], 'origen' => $origen],
            ['codigo' => 'comprobante_pago', 'tipo' => 'anexo', 'nombre' => 'Comprobante de pago', 'descripcion' => 'Comprobante de la orden de pago del proceso.', 'obligatorio' => true, 'etapa' => 'envio', 'condicion' => null, 'tipos_permitidos' => ['application/pdf', 'image/jpeg', 'image/png'], 'origen' => $origen],
            ['codigo' => 'anexo_especial', 'tipo' => 'anexo', 'nombre' => 'Anexo de modalidad especial', 'descripcion' => 'Socioeconómico, excelencia o vínculo laboral con la UES según la modalidad.', 'obligatorio' => true, 'etapa' => 'envio', 'condicion' => $especial, 'tipos_permitidos' => ['application/pdf', 'image/jpeg', 'image/png'], 'origen' => $origen],
        ];
    }

    public function crear(Carrera $carrera, array $d, string $actor): ConvocatoriaIngreso
    {
        if ($carrera->estado !== 'activa') {
            throw DominioException::conflicto('CARRERA_ARCHIVADA', 'La carrera está archivada.');
        }

        return DB::transaction(function () use ($carrera, $d, $actor) {
            if (ConvocatoriaIngreso::query()->where('id_carrera', $carrera->id_carrera)->where('codigo', $d['codigo'])->exists()) {
                throw ValidationException::withMessages(['codigo' => 'El código ya existe en la carrera.']);
            }
            $zona = $this->configuracion->zona();
            $conv = ConvocatoriaIngreso::create([
                'id_carrera' => $carrera->id_carrera,
                'codigo' => $d['codigo'],
                'titulo_publico' => $d['titulo_publico'],
                'descripcion_publica' => $d['descripcion_publica'] ?? null,
                'inicio' => isset($d['inicio']) ? CarbonImmutable::parse($d['inicio'], $zona)->utc() : null,
                'fin' => isset($d['fin']) ? CarbonImmutable::parse($d['fin'], $zona)->utc() : null,
                'estado' => 'borrador',
                'version_requisitos' => 1,
                'requiere_pago' => $d['requiere_pago'] ?? true,
                'monto_pago' => $d['monto_pago'] ?? $this->configuracion->valor('admision.monto_pago_usd'),
            ]);
            $this->guardarRequisitos($conv, 1, $d['requisitos'] ?? self::requisitosIniciales());
            $this->guardarOfertas($conv, $d['ofertas'] ?? []);
            $this->auditoria->registrar($actor, 'convocatoria.crear', 'convocatoria_ingreso', $conv->id_convocatoria);

            return $conv;
        });
    }

    /**
     * PATCH: datos, requisitos (nueva versión si ya se publicó), ofertas y transición.
     */
    public function actualizar(ConvocatoriaIngreso $conv, array $d, string $actor): ConvocatoriaIngreso
    {
        return DB::transaction(function () use ($conv, $d, $actor) {
            $conv = ConvocatoriaIngreso::query()->lockForUpdate()->findOrFail($conv->id_convocatoria);
            if (isset($d['estado']) && $d['estado'] === $conv->estado && count($d) === 2) {
                return $conv;
            }
            $this->catalogo->exigirVersion($conv, (int) $d['version']);
            if ($conv->estado === 'cerrada') {
                throw DominioException::conflicto('CONVOCATORIA_CERRADA', 'La convocatoria está cerrada.');
            }
            $zona = $this->configuracion->zona();
            foreach (['titulo_publico', 'descripcion_publica', 'requiere_pago', 'monto_pago'] as $c) {
                if (array_key_exists($c, $d)) {
                    $conv->{$c} = $d[$c];
                }
            }
            foreach (['inicio', 'fin'] as $c) {
                if (array_key_exists($c, $d)) {
                    $conv->{$c} = $d[$c] ? CarbonImmutable::parse($d[$c], $zona)->utc() : null;
                }
            }
            if (array_key_exists('requisitos', $d)) {
                // Una convocatoria publicada versiona requisitos; las solicitudes previas conservan su versión.
                $version = $conv->estado === 'publicada' ? $conv->version_requisitos + 1 : $conv->version_requisitos;
                if ($conv->estado !== 'publicada') {
                    RequisitoIngreso::query()->where('id_convocatoria', $conv->id_convocatoria)->where('version_requisitos', $version)->delete();
                }
                $this->guardarRequisitos($conv, $version, $d['requisitos']);
                $conv->version_requisitos = $version;
            }
            if (array_key_exists('ofertas', $d)) {
                if ($conv->estado === 'publicada' && DB::table('solicitud_ingreso')->where('id_convocatoria', $conv->id_convocatoria)->exists()) {
                    throw DominioException::conflicto('CONVOCATORIA_CON_SOLICITUDES', 'No se pueden cambiar las combinaciones con solicitudes recibidas.');
                }
                CarreraOfertaAdmision::query()->where('id_convocatoria', $conv->id_convocatoria)->delete();
                $this->guardarOfertas($conv, $d['ofertas']);
            }
            if (isset($d['estado']) && $d['estado'] !== $conv->estado) {
                $permitidas = ['borrador' => ['publicada'], 'publicada' => ['cerrada']];
                if (! in_array($d['estado'], $permitidas[$conv->estado] ?? [], true)) {
                    throw DominioException::conflicto('TRANSICION_INVALIDA', "No se permite pasar de {$conv->estado} a {$d['estado']}.");
                }
                if ($d['estado'] === 'publicada') {
                    $this->validarPublicacion($conv);
                    $conv->publicado_por = $actor;
                    $conv->publicado_en = now('UTC');
                }
                $conv->estado = $d['estado'];
            }
            if ($conv->fin && $conv->inicio && $conv->fin->lte($conv->inicio)) {
                throw ValidationException::withMessages(['fin' => 'El fin debe ser posterior al inicio.']);
            }
            $conv->version++;
            $conv->save();
            $this->auditoria->registrar($actor, 'convocatoria.actualizar', 'convocatoria_ingreso', $conv->id_convocatoria, null, ['estado' => $conv->estado, 'version_requisitos' => $conv->version_requisitos]);

            return $conv;
        });
    }

    public function abierta(ConvocatoriaIngreso $conv, ?CarbonImmutable $instante = null): bool
    {
        $t = $instante ?? CarbonImmutable::now('UTC');

        return $conv->estado === 'publicada' && $conv->inicio && $conv->fin && $t->gte($conv->inicio) && $t->lt($conv->fin);
    }

    /**
     * @return Collection<int, RequisitoIngreso>
     */
    public function requisitos(ConvocatoriaIngreso $conv, ?int $version = null)
    {
        return RequisitoIngreso::query()->where('id_convocatoria', $conv->id_convocatoria)
            ->where('version_requisitos', $version ?? $conv->version_requisitos)->orderBy('id_requisito')->get();
    }

    /**
     * Evalúa una condición estructurada (igualdad/pertenencia, all/any) sobre el contexto.
     */
    public static function evaluarCondicion(?array $cond, array $ctx): bool
    {
        if (! $cond) {
            return true;
        }
        if (isset($cond['all'])) {
            foreach ($cond['all'] as $c) {
                if (! self::evaluarCondicion($c, $ctx)) {
                    return false;
                }
            }

            return true;
        }
        if (isset($cond['any'])) {
            foreach ($cond['any'] as $c) {
                if (self::evaluarCondicion($c, $ctx)) {
                    return true;
                }
            }

            return false;
        }
        $valor = $ctx[$cond['campo']] ?? null;
        if (is_string($valor)) {
            $valor = mb_strtolower($valor);
        }
        $esperado = is_string($cond['valor'] ?? null) ? mb_strtolower($cond['valor']) : ($cond['valor'] ?? null);

        return match ($cond['op']) {
            'eq' => $valor === $esperado,
            'neq' => $valor !== $esperado,
            'in' => in_array($valor, array_map(fn ($v) => is_string($v) ? mb_strtolower($v) : $v, (array) $esperado), true),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function json(ConvocatoriaIngreso $c, bool $publico = false): array
    {
        $req = $this->requisitos($c)->map(fn ($r) => [
            'codigo' => $r->codigo, 'tipo' => $r->tipo, 'nombre' => $r->nombre, 'descripcion' => $r->descripcion,
            'obligatorio' => (bool) $r->obligatorio, 'etapa' => $r->etapa, 'condicion' => $r->condicion,
            'max_bytes' => $r->max_bytes, 'tipos_permitidos' => $r->tipos_permitidos,
        ] + ($publico ? [] : ['origen' => $r->origen]));
        $ofertas = CarreraOfertaAdmision::query()->where('id_convocatoria', $c->id_convocatoria)
            ->join('sede', 'sede.id_sede', '=', 'carrera_oferta_admision.id_sede')
            ->join('facultad', 'facultad.id_facultad', '=', 'carrera_oferta_admision.id_facultad')
            ->get(['carrera_oferta_admision.*', 'sede.nombre as sede', 'facultad.nombre as facultad']);
        $carrera = Carrera::find($c->id_carrera);

        return [
            'id_convocatoria' => $c->id_convocatoria,
            'id_carrera' => $c->id_carrera,
            'carrera' => ['codigo' => $carrera?->codigo, 'nombre' => $carrera?->nombre, 'clasificacion' => $carrera?->clasificacion],
            'codigo' => $c->codigo,
            'titulo_publico' => $c->titulo_publico,
            'descripcion_publica' => $c->descripcion_publica,
            'inicio' => $c->inicio?->toIso8601String(),
            'fin' => $c->fin?->toIso8601String(),
            'zona' => $this->configuracion->zona(),
            'estado' => $c->estado,
            'abierta' => $this->abierta($c),
            'version_requisitos' => (int) $c->version_requisitos,
            'requiere_pago' => (bool) $c->requiere_pago,
            'monto_pago' => $c->monto_pago,
            'requisitos' => $req->values(),
            'ofertas' => $ofertas->map(fn ($o) => [
                'id_sede' => $o->id_sede, 'sede' => $o->sede, 'id_facultad' => $o->id_facultad, 'facultad' => $o->facultad,
                'id_carrera' => $o->id_carrera, 'modalidad_estudio' => $o->modalidad_estudio, 'modalidad_ingreso' => $o->modalidad_ingreso,
            ])->values(),
        ] + ($publico ? [] : ['version' => (int) $c->version, 'publicado_en' => $c->publicado_en?->toIso8601String()]);
    }

    private function guardarRequisitos(ConvocatoriaIngreso $conv, int $version, array $requisitos): void
    {
        $codigos = [];
        foreach ($requisitos as $i => $r) {
            if (isset($codigos[$r['codigo']])) {
                throw ValidationException::withMessages(["requisitos.$i.codigo" => 'Código de requisito repetido.']);
            }
            $codigos[$r['codigo']] = true;
            $this->validarCondicion($r['condicion'] ?? null, "requisitos.$i.condicion");
            RequisitoIngreso::create([
                'id_convocatoria' => $conv->id_convocatoria,
                'version_requisitos' => $version,
                'codigo' => $r['codigo'],
                'tipo' => $r['tipo'],
                'nombre' => $r['nombre'],
                'descripcion' => $r['descripcion'] ?? null,
                'obligatorio' => $r['obligatorio'] ?? true,
                'etapa' => $r['etapa'] ?? 'envio',
                'condicion' => $r['condicion'] ?? null,
                'max_bytes' => $r['max_bytes'] ?? null,
                'tipos_permitidos' => $r['tipos_permitidos'] ?? null,
                'origen' => $r['origen'] ?? null,
            ]);
        }
    }

    private function validarCondicion(?array $cond, string $clave): void
    {
        if (! $cond) {
            return;
        }
        foreach (['all', 'any'] as $op) {
            if (isset($cond[$op])) {
                foreach ((array) $cond[$op] as $c) {
                    $this->validarCondicion($c, $clave);
                }

                return;
            }
        }
        if (! in_array($cond['campo'] ?? null, self::CAMPOS_CONDICION, true) || ! in_array($cond['op'] ?? null, ['eq', 'neq', 'in'], true)) {
            throw ValidationException::withMessages([$clave => 'Condición inválida o referencia a campo inexistente.']);
        }
    }

    private function guardarOfertas(ConvocatoriaIngreso $conv, array $ofertas): void
    {
        foreach ($ofertas as $i => $o) {
            $relacion = DB::table('carrera_sede')->where('id_carrera', $conv->id_carrera)->where('id_sede', $o['id_sede'])->exists();
            $carrera = Carrera::find($conv->id_carrera);
            if (! $relacion || ($carrera->id_facultad && (int) $carrera->id_facultad !== (int) $o['id_facultad'])) {
                throw ValidationException::withMessages(["ofertas.$i" => 'La sede o facultad no corresponde a la carrera.']);
            }
            CarreraOfertaAdmision::create([
                'id_convocatoria' => $conv->id_convocatoria,
                'id_carrera' => $conv->id_carrera,
                'id_sede' => $o['id_sede'],
                'id_facultad' => $o['id_facultad'],
                'modalidad_estudio' => $o['modalidad_estudio'],
                'modalidad_ingreso' => $o['modalidad_ingreso'],
            ]);
        }
    }

    private function validarPublicacion(ConvocatoriaIngreso $conv): void
    {
        if (Carrera::find($conv->id_carrera)?->estado !== 'activa') {
            throw DominioException::conflicto('CARRERA_ARCHIVADA', 'La carrera está archivada.');
        }
        $e = [];
        if (! $conv->inicio || ! $conv->fin || $conv->fin->lte($conv->inicio)) {
            $e['fin'][] = 'La convocatoria requiere un intervalo de recepción válido.';
        }
        if ($this->requisitos($conv)->isEmpty()) {
            $e['requisitos'][] = 'La convocatoria requiere requisitos.';
        }
        if (! CarreraOfertaAdmision::query()->where('id_convocatoria', $conv->id_convocatoria)->exists()) {
            $e['ofertas'][] = 'Defina al menos una combinación de sede/facultad/modalidad.';
        }
        if ($conv->requiere_pago && (float) $conv->monto_pago <= 0) {
            $e['monto_pago'][] = 'El monto de pago debe ser positivo.';
        }
        if ($e) {
            throw ValidationException::withMessages($e);
        }
    }
}
