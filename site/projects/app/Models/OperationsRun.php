<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationsRun extends Model
{
    protected $table = 'ops_runs';

    protected $fillable = [
        'run_type',
        'source',
        'provider',
        'event',
        'command',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'summary',
        'output',
        'error',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
