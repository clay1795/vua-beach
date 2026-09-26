<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['admin_user_id', 'action', 'subject_type', 'subject_id', 'description', 'metadata', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function adminUser()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
