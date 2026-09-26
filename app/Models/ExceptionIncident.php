<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExceptionIncident extends Model
{
    protected $fillable = [
        'fingerprint',
        'exception_class',
        'location',
        'route',
        'environment',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
        'last_alerted_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'occurrences' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_alerted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
