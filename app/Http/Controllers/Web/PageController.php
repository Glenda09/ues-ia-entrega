<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * Entrega el shell MVC. La página se toma de los valores por defecto de la ruta para
     * admitir rutas con parámetros (p. ej. /academico/expedientes/{expediente}).
     */
    public function mostrar(Request $request): View
    {
        $page = (string) ($request->route()?->defaults['page'] ?? 'not-found');
        $marca = app(\App\Services\App\PersonalizacionService::class)->publica();

        return view('pages.mvc', ['page' => $page, 'marca' => $marca]);
    }

    public function noEncontrada(Request $request): View
    {
        $marca = app(\App\Services\App\PersonalizacionService::class)->publica();

        return view('pages.mvc', ['page' => 'not-found', 'marca' => $marca]);
    }
}
