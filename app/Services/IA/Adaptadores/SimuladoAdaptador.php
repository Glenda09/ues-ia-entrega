<?php

namespace App\Services\IA\Adaptadores;

/**
 * Adaptador simulado determinista (D10: pruebas y entornos sin API key).
 *
 * Comportamiento:
 * 1. Si el último mensaje es del usuario, elige herramientas por palabras clave y las solicita.
 * 2. Si ya hay resultados de herramientas, redacta una respuesta en español citando las fuentes
 *    recibidas (`[fuente:ID]`), sin inventar datos fuera de esos resultados.
 * El consumo se estima de forma determinista a partir de la longitud del contexto.
 *
 * Fallos verificables: un mensaje de usuario que contenga `__fallo_previo__` o `__fallo_posterior__`
 * fuerza un fallo antes o después del envío.
 */
class SimuladoAdaptador implements AdaptadorIA
{
    public function __construct(private string $modelo = 'mock-academico-1') {}

    public function proveedor(): string
    {
        return 'mock';
    }

    public function modelo(): string
    {
        return $this->modelo;
    }

    public function simulado(): bool
    {
        return true;
    }

    public function generar(array $mensajes, array $herramientas, int $maxSalida): array
    {
        $ultimo = end($mensajes);
        $usuario = collect($mensajes)->where('rol', 'user')->last()['contenido'] ?? '';
        if (str_contains($usuario, '__fallo_previo__')) {
            throw new FalloAntesDeEnvio('Fallo simulado antes del envío.');
        }
        if (str_contains($usuario, '__fallo_posterior__')) {
            throw new \RuntimeException('Timeout simulado después del envío.');
        }
        $entrada = (int) ceil(mb_strlen(collect($mensajes)->pluck('contenido')->implode("\n")) / 4);
        $nombres = array_column($herramientas, 'nombre');

        if ($ultimo['rol'] === 'user' && $nombres) {
            $llamadas = $this->elegirHerramientas(mb_strtolower($usuario), $nombres);
            if ($llamadas) {
                return ['texto' => '', 'llamadas' => $llamadas, 'citas' => [], 'uso' => ['entrada' => $entrada, 'salida' => 20], 'id_respuesta' => 'mock_'.substr(sha1($usuario), 0, 12)];
            }
        }

        $resultados = collect($mensajes)->where('rol', 'tool')->map(fn ($m) => json_decode($m['contenido'], true) ?: [])->values();
        [$texto, $citas] = $this->redactar(mb_strtolower($usuario), $resultados->all());
        $salida = min($maxSalida, (int) ceil(mb_strlen($texto) / 4));

        return ['texto' => $texto, 'llamadas' => [], 'citas' => $citas, 'uso' => ['entrada' => $entrada, 'salida' => $salida], 'id_respuesta' => 'mock_'.substr(sha1($usuario.count($mensajes)), 0, 12)];
    }

    /**
     * @param  array<int, string>  $disponibles
     * @return array<int, array{nombre:string, argumentos:array}>
     */
    private function elegirHerramientas(string $q, array $disponibles): array
    {
        $llamadas = [];
        $agregar = function (string $nombre, array $args = []) use (&$llamadas, $disponibles) {
            if (in_array($nombre, $disponibles, true)) {
                $llamadas[] = ['nombre' => $nombre, 'argumentos' => $args];
            }
        };
        // Agente de aspirantes.
        if (in_array('buscarFuentes', $disponibles, true)) {
            $agregar('buscarFuentes', ['consulta' => $q]);
            if (preg_match('/mi solicitud|mi folio|mis requisitos|mi estado/u', $q)) {
                $agregar('consultarMiSolicitud', []);
            }

            return $llamadas;
        }
        // Agente de estudiantes.
        if (preg_match('/avance|aprob|crédit|credit|pensum|llevo|pendiente/u', $q)) {
            $agregar('consultarMiAvance');
        }
        if (preg_match('/calendario|fecha|periodo|plazo|cuando|cuándo/u', $q)) {
            $agregar('consultarMiCalendario');
        }
        if (preg_match('/inscrib|puedo|elegib|materias.*(ciclo|disponible)|propuesta|recomienda|planific|cupo|inscríbeme/u', $q)) {
            $agregar('consultarMiElegibilidad', []);
        }
        if (preg_match('/inscrita|inscritas|mis inscripciones|estoy cursando|curso actual/u', $q)) {
            $agregar('consultarMisInscripciones');
        }
        if (preg_match('/mi carrera|la carrera|pensum|plan de estudio|facultad|sede|prerrequisit|requisito de|de qué trata|de que trata|convocatoria/u', $q)) {
            $agregar('consultarMiCarrera');
        }
        if (! $llamadas) {
            $agregar('consultarMiAvance');
        }

        return $llamadas;
    }

    /**
     * @return array{0:string, 1:array<int, string>}
     */
    private function redactar(string $q, array $resultados): array
    {
        $citas = [];
        $partes = [];
        foreach ($resultados as $r) {
            foreach ($r['fuentes'] ?? [] as $f) {
                $citas[] = (string) $f['id'];
                $partes[] = '- '.$f['resumen'].' [fuente:'.$f['id'].']';
            }
            if (isset($r['resumen'])) {
                $partes[] = $r['resumen'].(isset($r['referencia']) ? ' [fuente:'.$r['referencia'].']' : '');
                if (isset($r['referencia'])) {
                    $citas[] = (string) $r['referencia'];
                }
            }
        }
        if (! $partes) {
            return ['No encontré información publicada suficiente para responder con certeza. Indica la carrera o el tema que te interesa, o consulta el contacto oficial.', []];
        }

        return ["Con base en la información disponible:\n".implode("\n", array_unique($partes)), array_values(array_unique($citas))];
    }
}
