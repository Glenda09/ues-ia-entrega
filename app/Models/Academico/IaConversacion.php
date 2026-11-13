<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Conversación de IA (tabla `ia_conversacion`), propiedad de un usuario o una sesión pública.
 */
class IaConversacion extends Model
{
    use HasUuids;

    protected $table = 'ia_conversacion';

    protected $primaryKey = 'id_conversacion';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
