<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

use App\Models\App\AppModulo;

class ModuloController extends Controller
{
    public function index()
    {
        $ttl = (int) config('catalog.cache_ttl');
        $version = (int) Cache::get('catalog:app_modulo:version', 1);
        $cacheKey = 'catalog:app_modulo:v' . $version . ':index:' . 'app_modulo';

        $payload = Cache::remember($cacheKey, $ttl, function () {
            $query = AppModulo::query();
            $items = $query->get();

            return $items->values()->all();
        });

        return response()->json($payload);
    }
}
