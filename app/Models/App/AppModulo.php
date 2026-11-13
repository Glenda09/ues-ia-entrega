<?php

namespace App\Models\App;

use Illuminate\Database\Eloquent\Model;

class AppModulo extends Model
{
    protected $table = 'app_modulo';
    protected $primaryKey = 'codigo';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'id_estado',
    ];
}
