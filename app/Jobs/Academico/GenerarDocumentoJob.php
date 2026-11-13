<?php

namespace App\Jobs\Academico;

use App\Services\Academico\DocumentoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * EXP-04: genera el PDF de un documento de expediente (carga el registro por ID).
 */
class GenerarDocumentoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $idDocumento) {}

    public function handle(DocumentoService $documentos): void
    {
        $documentos->generar($this->idDocumento);
    }
}
