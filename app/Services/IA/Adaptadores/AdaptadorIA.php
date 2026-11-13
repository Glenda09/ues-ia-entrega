<?php

namespace App\Services\IA\Adaptadores;

/**
 * Contrato común de proveedores de IA (IA-BASE-01).
 *
 * `generar` recibe mensajes normalizados (rol system|user|assistant|tool) y definiciones
 * de herramientas, y devuelve texto, llamadas a herramientas, citas propuestas y uso.
 */
interface AdaptadorIA
{
    public function proveedor(): string;

    public function modelo(): string;

    public function simulado(): bool;

    /**
     * @param  array<int, array{rol:string, contenido:string, nombre?:string}>  $mensajes
     * @param  array<int, array{nombre:string, descripcion:string, parametros:array}>  $herramientas
     * @return array{texto:string, llamadas:array<int, array{nombre:string, argumentos:array}>, citas:array<int, string>, uso:array{entrada:int, salida:int}, id_respuesta:?string}
     *
     * @throws FalloAntesDeEnvio cuando se comprueba que la petición no salió.
     * @throws \Throwable cualquier otro fallo se trata como posterior al envío (reserva incierta).
     */
    public function generar(array $mensajes, array $herramientas, int $maxSalida): array;
}
