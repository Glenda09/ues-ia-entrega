<?php

namespace App\Support\IA;

/**
 * Normalización de citas de las respuestas de IA.
 *
 * El contrato canónico es la marca `[fuente:ID]`. Los modelos reales a veces escriben variantes
 * (`[Fuente: a, b]`, `[fuente: a; b]`, `【fuente:a】`). Esta clase las reconoce, conserva solo IDs
 * entregados por el servidor en el turno y reescribe el texto con marcas canónicas.
 */
final class Citas
{
    /** Bloque de cita en cualquiera de sus variantes; el grupo 1 contiene uno o varios IDs. */
    private const PATRON = '/[\[【]\s*fuentes?\s*:\s*([^\]】]+?)\s*[\]】]/iu';

    /**
     * IDs citados en el texto, en orden de aparición y sin duplicados.
     *
     * @return array<int, string>
     */
    public static function extraer(string $texto): array
    {
        preg_match_all(self::PATRON, $texto, $m);
        $ids = [];
        foreach ($m[1] ?? [] as $grupo) {
            foreach (self::separar($grupo) as $id) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Reescribe las citas como `[fuente:ID]` y elimina las que no correspondan a IDs válidos.
     *
     * @param  callable(string): bool  $valido
     */
    public static function normalizar(string $texto, callable $valido): string
    {
        // Corchetes con solo IDs válidos y sin prefijo (p. ej. `[avance:1]`, `[F4, F5]`): el modelo omitió `fuente:`.
        // No se toca un enlace Markdown `[texto](url)` ni corchetes con texto que no sea un ID entregado.
        $texto = preg_replace_callback('/\[([A-Za-z0-9:_\-]+(?:\s*[,;]\s*[A-Za-z0-9:_\-]+)*)\](?!\()/u', function ($m) use ($valido) {
            $ids = self::separar($m[1]);

            return $ids && count(array_filter($ids, $valido)) === count($ids) ? '[fuente:'.implode(', ', $ids).']' : $m[0];
        }, $texto);

        $texto = preg_replace_callback(self::PATRON, function ($m) use ($valido) {
            $ids = array_filter(self::separar($m[1]), $valido);

            return implode('', array_map(fn ($id) => "[fuente:$id]", $ids));
        }, $texto);

        // Espacios sobrantes que deja una cita eliminada antes de un signo o al final de línea.
        return trim(preg_replace(['/[ \t]+([.,;:])/u', '/[ \t]+$/mu'], ['$1', ''], $texto));
    }

    /**
     * @return array<int, string>
     */
    private static function separar(string $grupo): array
    {
        $partes = preg_split('/\s*[,;]\s*|\s+y\s+|\s+/u', trim($grupo)) ?: [];
        $partes = array_map(fn ($p) => preg_replace('/^fuentes?\s*:\s*/iu', '', trim($p, " \t\"'`")), $partes);

        return array_values(array_filter($partes, fn ($p) => $p !== '' && preg_match('/^[A-Za-z0-9:_\-]+$/', $p)));
    }
}
