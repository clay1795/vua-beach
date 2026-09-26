<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookReceipt extends Model
{
    protected $fillable = [
        'provider', 'event_key', 'payload_hash', 'status', 'response_code',
        'response_body', 'attempts', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['response_body' => 'array', 'processed_at' => 'datetime'];
    }
}
