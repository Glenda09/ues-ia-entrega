<?php

namespace App\Services\IA\Adaptadores;

use RuntimeException;

/**
 * Fallo comprobado antes de enviar la petición al proveedor: libera la reserva.
 */
class FalloAntesDeEnvio extends RuntimeException {}
