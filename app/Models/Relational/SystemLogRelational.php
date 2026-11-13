<?php

namespace App\Models\Relational;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SystemLogRelational extends Model
{
    use HasUuids;

    protected $table = 'system_logs';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'message',
        'context',
        'level',
        'level_name',
        'channel',
        'datetime',
        'extra',
    ];

    protected $casts = [
        'context' => 'array',
        'extra' => 'array',
        'datetime' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection((string) config('audit.relational_connection', config('database.default')));
    }
}
