<?php

namespace App\Http\Controllers\Academico;

use App\Models\Academico\Carrera;
use App\Models\Academico\IaConversacion;
use App\Models\Academico\IaMensaje;
use App\Models\Academico\IaSesionPublica;
use App\Models\Academico\IaTarifa;
use App\Models\Auth\AuthUsuario;
use App\Services\Academico\ElegibilidadService;
use App\Services\Academico\IdempotenciaService;
use App\Services\Auth\PermisosEfectivosService;
use App\Services\IA\Agentes\AgenteAspirantes;
use App\Services\IA\Agentes\AgenteEstudiantes;
use App\Services\IA\FuentesIAService;
use App\Services\IA\PresupuestoIAService;
use App\Services\IA\ProxyIAService;
use App\Support\Academico\DominioException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * IA-BASE-01, IAA-01, IAA-02 e IAE-01.
 */
class IAController extends AcademicoController
{
    public const COOKIE = 'ues_asistente_sesion';

    public function __construct(private ProxyIAService $proxy, private PresupuestoIAService $presupuesto, private FuentesIAService $fuentes) {}

    // ---------------- Disponibilidad, consumo, tarifas ----------------

    public function disponibilidadPublica(): JsonResponse
    {
        return $this->ok($this->proxy->disponibilidad());
    }

    public function disponibilidadAcademica(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        return $this->ok($this->proxy->disponibilidad() + ['agente_estudiantes' => $actor->tiene('academico_mi_asistente_usar')]);
    }

    public function consumo(): JsonResponse
    {
        $a = $this->proxy->adaptador();

        return $this->ok($this->presupuesto->consumo($this->proxy->ambito(), $a->proveedor(), $a->modelo()));
    }

    public function tarifas(Request $request): JsonResponse
    {
        return $this->paginado($request, IaTarifa::query()->orderBy('proveedor')->orderBy('modelo')->orderByDesc('version'), fn ($t) => $t->toArray());
    }

    public function crearTarifa(Request $request): JsonResponse
    {
        $d = $this->validarEstricto($request, [
            'proveedor' => 'required|string|max:40', 'modelo' => 'required|string|max:100',
            'precio_entrada_mtok' => 'required|numeric|min:0', 'precio_entrada_cache_mtok' => 'nullable|numeric|min:0', 'precio_salida_mtok' => 'required|numeric|min:0',
            'fuente' => 'nullable|string|max:300',
        ]);
        $version = (int) IaTarifa::query()->where('proveedor', $d['proveedor'])->where('modelo', $d['modelo'])->max('version') + 1;
        $t = IaTarifa::create($d + ['version' => $version, 'precio_entrada_cache_mtok' => $d['precio_entrada_cache_mtok'] ?? 0, 'vigente_desde' => now(), 'creado_por' => $this->actor($request)->idUsuario]);
        $this->presupuesto->invalidar();

        return $this->creado($t, 'Tarifa registrada');
    }

    // ---------------- IAA-01 Fuentes ----------------

    public function fuentesCarrera(Request $request, Carrera $carrera): JsonResponse
    {
        $this->actor($request)->exigirCarrera($carrera->id_carrera);

        return $this->ok($this->fuentes->candidatas($carrera));
    }

    public function publicarFuentes(Request $request, Carrera $carrera): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($carrera->id_carrera);
        $d = $this->validarEstricto($request, [
            'estado' => 'required|in:publicada,retirada',
            'fuentes' => 'required|array|min:1',
            'fuentes.*.tipo' => 'required|in:carrera,pensum,convocatoria,requisitos',
            'fuentes.*.id_origen' => 'required|integer',
        ]);
        $this->fuentes->publicar($carrera, $d['fuentes'], $d['estado'], $actor->idUsuario);

        return $this->ok($this->fuentes->candidatas($carrera), $d['estado'] === 'publicada' ? 'Fuentes publicadas' : 'Fuentes retiradas');
    }

    public function carrerasPublicas(): JsonResponse
    {
        return $this->ok($this->fuentes->catalogoPublico());
    }

    public function carreraPublica(Carrera $carrera): JsonResponse
    {
        $d = $this->fuentes->detallePublico($carrera->id_carrera);
        abort_unless($d, 404);

        return $this->ok($d);
    }

    // ---------------- IAA-02 Asistente público ----------------

    public function mensajePublico(Request $request): JsonResponse
    {
        $d = $this->validarEstricto($request, ['mensaje' => 'required|string', 'id_conversacion' => 'nullable|uuid']);
        [$propietario, $cookie] = $this->propietarioPublico($request);
        $this->limitar($propietario, $request->ip());
        $clave = app(IdempotenciaService::class)->validarClave($request->header('Idempotency-Key'));
        [$estado, $cuerpo] = app(AgenteAspirantes::class)->responder($propietario, $d['id_conversacion'] ?? null, $d['mensaje'], $clave);

        return $this->conCookie(response()->json($cuerpo, $estado), $cookie);
    }

    public function conversacionesPublicas(Request $request): JsonResponse
    {
        [$propietario, $cookie] = $this->propietarioPublico($request);
        $q = IaConversacion::query()->where('propietario_tipo', $propietario['tipo'])->where('propietario_id', $propietario['id'])->where('agente', 'aspirantes')->orderByDesc('updated_at')->orderByDesc('id_conversacion');

        return $this->conCookie($this->paginado($request, $q, fn ($c) => $c->only(['id_conversacion', 'titulo', 'created_at', 'updated_at'])), $cookie);
    }

    public function mensajesPublicos(Request $request, string $conversacion): JsonResponse
    {
        [$propietario, $cookie] = $this->propietarioPublico($request);
        $c = $this->proxy->conversacionPropia($propietario, 'aspirantes', null, $conversacion);

        return $this->conCookie($this->paginado($request, IaMensaje::query()->where('id_conversacion', $c->id_conversacion)->orderBy('secuencia'), fn ($m) => $m->only(['secuencia', 'rol', 'contenido', 'referencias', 'created_at'])), $cookie);
    }

    /**
     * Propietario: JWT válido prevalece (inválido → 401); si no, sesión pública por cookie opaca (hash en servidor).
     *
     * @return array{0: array{tipo:string,id:string}, 1: ?Cookie}
     */
    private function propietarioPublico(Request $request): array
    {
        if ($request->bearerToken()) {
            try {
                $sub = (string) JWTAuth::setToken($request->bearerToken())->getPayload()->get('sub');
                if (! AuthUsuario::find($sub)) {
                    throw new \RuntimeException('Usuario inexistente');
                }
                $permisos = app(PermisosEfectivosService::class)->paraUsuario($sub);
                if (in_array('academico_mi_solicitud_gestionar', $permisos, true) || in_array('root', $permisos, true)) {
                    return [['tipo' => 'usuario', 'id' => $sub], null];
                }
            } catch (\Throwable) {
                throw new DominioException('NO_AUTENTICADO', 'Sesión no válida.', 401);
            }
        }
        $token = (string) $request->cookie(self::COOKIE);
        $sesion = $token !== '' ? IaSesionPublica::query()->where('token_hash', hash('sha256', $token))->where('expira_en', '>', now())->first() : null;
        $cookie = null;
        if (! $sesion) {
            $token = bin2hex(random_bytes(32));
            $sesion = IaSesionPublica::create(['token_hash' => hash('sha256', $token), 'expira_en' => now()->addDays(30)]);
            $cookie = cookie(self::COOKIE, $token, 60 * 24 * 30, '/', null, $request->isSecure(), true, false, 'lax');
        }

        return [['tipo' => 'sesion_publica', 'id' => (string) $sesion->id_sesion_publica], $cookie];
    }

    private function conCookie(JsonResponse $r, ?Cookie $c): JsonResponse
    {
        return $c ? $r->withCookie($c) : $r;
    }

    private function limitar(array $propietario, ?string $ip): void
    {
        foreach (['p:'.$propietario['tipo'].':'.$propietario['id'] => (int) config('ia.limite_turnos_propietario_minuto', 10), 'ip:'.$ip => (int) config('ia.limite_turnos_ip_minuto', 30)] as $k => $max) {
            if (RateLimiter::tooManyAttempts('ia:'.$k, $max)) {
                throw new DominioException('LIMITE_FRECUENCIA', 'Demasiados mensajes. Espera un momento.', 429, ['retry_after' => RateLimiter::availableIn('ia:'.$k)]);
            }
            RateLimiter::hit('ia:'.$k, 60);
        }
    }

    // ---------------- IAE-01 Asistente estudiantil ----------------

    public function mensajeEstudiante(Request $request): JsonResponse
    {
        $d = $this->validarEstricto($request, ['mensaje' => 'required|string', 'id_expediente' => 'required|integer', 'id_conversacion' => 'nullable|uuid']);
        $actor = $this->actor($request);
        $exp = app(ElegibilidadService::class)->expedientePropio($actor, (int) $d['id_expediente']);
        $this->limitar(['tipo' => 'usuario', 'id' => $actor->idUsuario], $request->ip());
        $clave = app(IdempotenciaService::class)->validarClave($request->header('Idempotency-Key'));
        [$estado, $cuerpo] = app(AgenteEstudiantes::class)->responder($actor, $exp, $d['id_conversacion'] ?? null, $d['mensaje'], $clave);

        return response()->json($cuerpo, $estado);
    }

    public function conversacionesEstudiante(Request $request): JsonResponse
    {
        $f = $request->validate(['expediente' => 'required|integer']);
        $actor = $this->actor($request);
        $exp = app(ElegibilidadService::class)->expedientePropio($actor, (int) $f['expediente']);
        $q = IaConversacion::query()->where('propietario_tipo', 'usuario')->where('propietario_id', $actor->idUsuario)->where('agente', 'estudiantes')
            ->where('id_expediente', $exp->id_expediente)->orderByDesc('updated_at')->orderByDesc('id_conversacion');

        return $this->paginado($request, $q, fn ($c) => $c->only(['id_conversacion', 'titulo', 'id_expediente', 'created_at', 'updated_at']));
    }

    public function mensajesEstudiante(Request $request, string $conversacion): JsonResponse
    {
        $f = $request->validate(['expediente' => 'required|integer']);
        $actor = $this->actor($request);
        $exp = app(ElegibilidadService::class)->expedientePropio($actor, (int) $f['expediente']);
        $c = $this->proxy->conversacionPropia(['tipo' => 'usuario', 'id' => $actor->idUsuario], 'estudiantes', $exp->id_expediente, $conversacion);

        return $this->paginado($request, IaMensaje::query()->where('id_conversacion', $c->id_conversacion)->orderBy('secuencia'), fn ($m) => $m->only(['secuencia', 'rol', 'contenido', 'referencias', 'created_at']));
    }
}
