<?php

namespace App\Models\App;

use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    protected $table = 'estado';
    protected $primaryKey = 'codigo';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre_estado',
        'label',
        'color',
        'icono',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    const ACTIVO = 'ACTIVO';
    const INACTIVO = 'INACTIVO';

    /**
     * Obtener el label como un array.
     *
     * @return array
     */
    public function getLabelAttribute($value)
    {
        return explode(',', $value);
    }
}
