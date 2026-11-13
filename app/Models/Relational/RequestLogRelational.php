<?php

namespace App\Models\Relational;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RequestLogRelational extends Model
{
    use HasUuids;

    protected $table = 'request_logs';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = true;

    const CREATED_AT = 'creation_date';

    const UPDATED_AT = 'updated_date';

    protected $fillable = [
        'uuid',
        'method',
        'uri',
        'headers',
        'query',
        'payload',
        'response',
        'type',
        'status_code',
        'creation_date',
        'updated_date',
    ];

    protected $casts = [
        'headers' => 'array',
        'query' => 'array',
        'payload' => 'array',
        'response' => 'array',
        'creation_date' => 'datetime',
        'updated_date' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection((string) config('audit.relational_connection', config('database.default')));
    }
}
