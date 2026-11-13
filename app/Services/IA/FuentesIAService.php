<?php

namespace App\Services\IA;

use App\Models\Academico\Carrera;
use App\Models\Academico\ConvocatoriaIngreso;
use App\Models\Academico\IaFuente;
use App\Models\Academico\Pensum;
use App\Services\Academico\AuditoriaService;
use App\Services\Academico\ConfiguracionService;
use App\Services\Academico\ConvocatoriaService;
use App\Services\Academico\PensumService;
use App\Support\Academico\DominioException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * IAA-01: proyección pública versionada de carreras, pensums, requisitos y convocatorias (D09).
 *
 * Cada fuente conserva procedencia (tipo/id_origen), versión, hash del origen, vigencia y autoría.
 * Una fuente cuyo origen cambió (hash distinto) queda desactualizada y se excluye de la recuperación.
 */
class FuentesIAService
{
    public function __construct(private PensumService $pensums, private ConvocatoriaService $convocatorias, private ConfiguracionService $configuracion, private AuditoriaService $auditoria) {}

    /**
     * Candidatas a publicar (proyección actual del origen) con estado de la fuente publicada.
     *
     * @return array<int, array<string, mixed>>
     */
    public function candidatas(Carrera $carrera): array
    {
        $out = [];
        foreach ($this->proyectar($carrera) as $p) {
            $pub = IaFuente::query()->where('tipo', $p['tipo'])->where('id_origen', $p['id_origen'])->orderByDesc('version')->first();
            $out[] = $p + [
                'publicada' => $pub && $pub->estado === 'publicada' ? ['id_fuente' => $pub->id_fuente, 'version' => $pub->version, 'publicado_en' => $pub->publicado_en?->toIso8601String(), 'actualizada' => $pub->hash_origen === $p['hash_origen']] : null,
            ];
        }

        return $out;
    }

    /**
     * Publica o retira proyecciones versionadas.
     *
     * @param  array<int, array{tipo:string, id_origen:int}>  $fuentes
     */
    public function publicar(Carrera $carrera, array $fuentes, string $estado, string $actor): array
    {
        return DB::transaction(function () use ($carrera, $fuentes, $estado, $actor) {
            $proy = collect($this->proyectar($carrera))->keyBy(fn ($p) => $p['tipo'].':'.$p['id_origen']);
            $res = [];
            foreach ($fuentes as $f) {
                $k = $f['tipo'].':'.$f['id_origen'];
                $actual = IaFuente::query()->where('tipo', $f['tipo'])->where('id_origen', $f['id_origen'])->where('id_carrera', $carrera->id_carrera)->orderByDesc('version')->lockForUpdate()->first();
                if ($estado === 'retirada') {
                    if ($actual && $actual->estado === 'publicada') {
                        $actual->update(['estado' => 'retirada', 'retirado_por' => $actor, 'retirado_en' => now()]);
                        $this->auditoria->registrar($actor, 'fuente_ia.retirar', 'ia_fuente', $actual->id_fuente);
                    }
                    $res[] = $k;

                    continue;
                }
                $p = $proy[$k] ?? null;
                if (! $p) {
                    throw new DominioException('FUENTE_NO_PUBLICABLE', "La fuente $k no es publicable (origen no publicado o ajeno).", 422);
                }
                if ($actual && $actual->estado === 'publicada' && $actual->hash_origen === $p['hash_origen']) {
                    $res[] = $k;

                    continue;
                }
                if ($actual && $actual->estado === 'publicada') {
                    $actual->update(['estado' => 'retirada', 'retirado_por' => $actor, 'retirado_en' => now()]);
                }
                $nueva = IaFuente::create([
                    'id_carrera' => $carrera->id_carrera, 'tipo' => $p['tipo'], 'id_origen' => $p['id_origen'], 'version' => ($actual->version ?? 0) + 1,
                    'titulo' => $p['titulo'], 'contenido' => $p['contenido'], 'url' => $p['url'], 'hash_origen' => $p['hash_origen'],
                    'estado' => 'publicada', 'vigente_hasta' => $p['vigente_hasta'], 'publicado_por' => $actor, 'publicado_en' => now(),
                ]);
                $this->auditoria->registrar($actor, 'fuente_ia.publicar', 'ia_fuente', $nueva->id_fuente, null, ['tipo' => $p['tipo'], 'version' => $nueva->version]);
                $res[] = $k;
            }

            return $res;
        });
    }

    /**
     * Fuentes publicadas, vigentes y actualizadas, filtradas por carrera/tema.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recuperar(string $consulta, ?int $idCarrera = null, int $limite = 6): array
    {
        $q = mb_strtolower($consulta);
        $fuentes = IaFuente::query()->where('estado', 'publicada')
            ->where(fn ($w) => $w->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', now()))
            ->when($idCarrera, fn ($w) => $w->where('id_carrera', $idCarrera))
            ->orderBy('id_fuente')->get()
            ->filter(fn ($f) => $this->vigenteFrenteAlOrigen($f));

        $palabras = array_filter(preg_split('/[^\p{L}\p{N}]+/u', $q), fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, ['cuáles', 'cuales', 'sobre', 'para', 'quiero', 'saber', 'tiene', 'carrera', 'información', 'informacion'], true));
        $temas = [
            'requisitos' => '/requisit|documento|dui|nie|pasaporte|foto/u', 'convocatoria' => '/fecha|plazo|convocatoria|ingreso|admisi|inscrip|cuando|cuándo|pago/u',
            'pensum' => '/materia|pensum|plan|asignatura|crédit|credit|nivel/u', 'carrera' => '/carrera|descrip|estudiar|ofrece/u',
        ];
        $puntuadas = $fuentes->map(function ($f) use ($palabras, $temas, $q) {
            $texto = mb_strtolower($f->titulo.' '.$f->contenido);
            $p = 0;
            foreach ($palabras as $w) {
                $p += substr_count($texto, $w);
            }
            if (isset($temas[$f->tipo]) && preg_match($temas[$f->tipo], $q)) {
                $p += 5;
            }

            return ['f' => $f, 'p' => $p];
        })->filter(fn ($x) => $x['p'] > 0)->sortByDesc('p')->take($limite);

        return $puntuadas->map(fn ($x) => $this->fuenteJson($x['f']))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function fuenteJson(IaFuente $f): array
    {
        return ['id' => 'F'.$f->id_fuente, 'id_fuente' => $f->id_fuente, 'tipo' => $f->tipo, 'titulo' => $f->titulo, 'url' => $f->url, 'version' => $f->version,
            'resumen' => mb_substr($f->contenido, 0, 600), 'vigente_hasta' => $f->vigente_hasta?->toIso8601String(), 'actualizado_en' => $f->publicado_en?->toIso8601String()];
    }

    /**
     * Catálogo público (misma proyección que consume el agente).
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalogoPublico(): array
    {
        return IaFuente::query()->where('estado', 'publicada')->where('tipo', 'carrera')->orderBy('id_carrera')->get()
            ->filter(fn ($f) => $this->vigenteFrenteAlOrigen($f))
            ->map(fn ($f) => ['id_carrera' => $f->id_carrera, 'titulo' => $f->titulo, 'resumen' => mb_substr($f->contenido, 0, 300), 'url' => $f->url, 'version' => $f->version])
            ->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detallePublico(int $idCarrera): ?array
    {
        $fuentes = IaFuente::query()->where('estado', 'publicada')->where('id_carrera', $idCarrera)
            ->where(fn ($w) => $w->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', now()))
            ->orderBy('id_fuente')->get()->filter(fn ($f) => $this->vigenteFrenteAlOrigen($f));
        if (! $fuentes->contains('tipo', 'carrera')) {
            return null;
        }

        return [
            'id_carrera' => $idCarrera,
            'contacto' => $this->configuracion->grupo('credenciales'),
            'secciones' => $fuentes->map(fn ($f) => ['ancla' => $f->tipo.'-'.$f->id_origen, 'tipo' => $f->tipo, 'titulo' => $f->titulo, 'contenido' => $f->contenido, 'version' => $f->version, 'id_fuente' => $f->id_fuente, 'vigente_hasta' => $f->vigente_hasta?->toIso8601String()])->values(),
        ];
    }

    private function vigenteFrenteAlOrigen(IaFuente $f): bool
    {
        $carrera = Carrera::find($f->id_carrera);
        if (! $carrera || $carrera->estado !== 'activa') {
            return false;
        }
        $p = collect($this->proyectar($carrera))->first(fn ($x) => $x['tipo'] === $f->tipo && (int) $x['id_origen'] === (int) $f->id_origen);

        return $p && $p['hash_origen'] === $f->hash_origen;
    }

    /**
     * Proyección actual desde orígenes publicados (solo campos públicos).
     *
     * @return array<int, array<string, mixed>>
     */
    public function proyectar(Carrera $carrera): array
    {
        if ($carrera->estado !== 'activa') {
            return [];
        }
        $base = '/carreras/'.$carrera->id_carrera;
        $out = [];
        $contenido = trim($carrera->nombre.'. '.($carrera->descripcion_publica ?: 'Sin descripción pública registrada.').' Clasificación: '.$carrera->clasificacion.'.');
        $out[] = ['tipo' => 'carrera', 'id_origen' => $carrera->id_carrera, 'titulo' => 'Carrera: '.$carrera->nombre, 'contenido' => $contenido, 'url' => $base.'#carrera-'.$carrera->id_carrera, 'vigente_hasta' => null];

        foreach (Pensum::query()->where('id_carrera', $carrera->id_carrera)->where('estado', 'publicado')->orderBy('id_pensum')->get() as $p) {
            $d = $this->pensums->detalle($p);
            $lineas = collect($d['materias'])->map(fn ($m) => sprintf('Nivel %d: %s %s (%s créditos%s%s)', $m['nivel'], $m['codigo'], $m['nombre'], $m['creditos'], $m['obligatoria'] ? '' : ', optativa',
                count($m['requisitos']) ? '; requiere '.collect($m['requisitos'])->pluck('codigo')->implode(', ') : ''))->implode('. ');
            $out[] = ['tipo' => 'pensum', 'id_origen' => $p->id_pensum, 'titulo' => "Plan de estudios {$p->version_curricular} de {$carrera->nombre}",
                'contenido' => "Plan {$p->version_curricular}, vigente desde {$d['vigente_desde']}. Créditos obligatorios: {$d['creditos_obligatorios']}. $lineas.", 'url' => $base.'#pensum-'.$p->id_pensum, 'vigente_hasta' => null];
        }
        foreach (ConvocatoriaIngreso::query()->where('id_carrera', $carrera->id_carrera)->whereIn('estado', ['publicada', 'cerrada'])->orderBy('id_convocatoria')->get() as $c) {
            $j = $this->convocatorias->json($c, true);
            $zona = $j['zona'];
            $fmt = fn ($iso) => $iso ? CarbonImmutable::parse($iso)->setTimezone($zona)->format('Y-m-d H:i') : 'sin fecha';
            $out[] = ['tipo' => 'convocatoria', 'id_origen' => $c->id_convocatoria, 'titulo' => $c->titulo_publico,
                'contenido' => sprintf('%s. Recepción de solicitudes del %s al %s (%s). Estado: %s. %s', $c->titulo_publico, $fmt($j['inicio']), $fmt($j['fin']), $zona, $c->estado,
                    $c->requiere_pago ? 'Pago de proceso: USD '.$c->monto_pago.'.' : 'Sin pago de proceso.'), 'url' => $base.'#convocatoria-'.$c->id_convocatoria, 'vigente_hasta' => $c->fin];
            $req = collect($j['requisitos'])->map(fn ($r) => $r['nombre'].($r['obligatorio'] ? '' : ' (opcional)').($r['condicion'] ? ' [según condición]' : '').': '.($r['descripcion'] ?? ''))->implode('; ');
            $out[] = ['tipo' => 'requisitos', 'id_origen' => $c->id_convocatoria, 'titulo' => 'Requisitos: '.$c->titulo_publico, 'contenido' => "Requisitos (versión {$j['version_requisitos']}): $req.", 'url' => $base.'#requisitos-'.$c->id_convocatoria, 'vigente_hasta' => $c->fin];
        }

        return array_map(fn ($p) => $p + ['hash_origen' => hash('sha256', $p['titulo'].'|'.$p['contenido'])], $out);
    }
}
