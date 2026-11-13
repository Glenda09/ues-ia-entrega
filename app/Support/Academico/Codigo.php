<?php

namespace App\Support\Academico;

use Normalizer;

/**
 * Normalización de códigos e iniciales.
 */
final class Codigo
{
    /**
     * Código de catálogo: trim, Unicode NFKC y mayúsculas.
     */
    public static function normalizar(string $codigo): string
    {
        $c = trim($codigo);
        if (class_exists(Normalizer::class)) {
            $c = Normalizer::normalize($c, Normalizer::FORM_KC) ?: $c;
        }

        return mb_strtoupper($c);
    }

    /**
     * Letra inicial sin diacríticos, en mayúscula (D01). `X` si no hay letra.
     */
    public static function inicial(?string $texto): string
    {
        $t = trim((string) $texto);
        if ($t === '') {
            return 'X';
        }
        if (class_exists(Normalizer::class)) {
            $t = Normalizer::normalize($t, Normalizer::FORM_D) ?: $t;
        }
        $t = preg_replace('/\p{Mn}+/u', '', $t);
        $t = preg_replace('/[^A-Za-z]/', '', $t);

        return $t === '' ? 'X' : strtoupper($t[0]);
    }
}
