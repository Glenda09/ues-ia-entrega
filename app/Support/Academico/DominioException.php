<?php

namespace App\Support\Academico;

use RuntimeException;

/**
 * Error de regla de negocio académica con código estable.
 *
 * Se renderiza como `{ "message": string, "code": string, "meta"?: object }`
 * con el estado HTTP indicado (409 por defecto).
 */
class DominioException extends RuntimeException
{
    /**
     * @param  string  $codigo  Código de negocio estable, p. ej. `TRANSICION_INVALIDA`.
     * @param  string  $mensaje  Mensaje legible en español.
     * @param  int  $estado  Estado HTTP de la respuesta.
     * @param  array<string, mixed>  $meta  Datos adicionales seguros para el cliente.
     */
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
        public readonly int $estado = 409,
        public readonly array $meta = [],
    ) {
        parent::__construct($mensaje);
    }

    public static function conflicto(string $codigo, string $mensaje, array $meta = []): self
    {
        return new self($codigo, $mensaje, 409, $meta);
    }

    public static function noEncontrado(string $mensaje = 'Registro no encontrado.'): self
    {
        return new self('NO_ENCONTRADO', $mensaje, 404);
    }

    public static function prohibido(string $mensaje = 'No tienes permisos para esta operación.'): self
    {
        return new self('PROHIBIDO', $mensaje, 403);
    }

    public static function configuracionIncompleta(array $faltantes): self
    {
        return new self('CONFIGURACION_INCOMPLETA', 'La configuración académica requerida está incompleta.', 409, ['faltantes' => array_values($faltantes)]);
    }

    /** @return array<string, mixed> */
    public function cuerpo(): array
    {
        $cuerpo = ['message' => $this->getMessage(), 'code' => $this->codigo];
        if ($this->meta !== []) {
            $cuerpo['meta'] = $this->meta;
        }

        return $cuerpo;
    }
}
