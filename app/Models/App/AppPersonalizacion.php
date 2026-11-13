<?php

namespace App\Models\App;

use Illuminate\Database\Eloquent\Model;

class AppPersonalizacion extends Model
{
    protected $table = 'app_personalizacion';

    protected $primaryKey = 'clave';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = ['textos' => 'array', 'assets' => 'array'];
}
