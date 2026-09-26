<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SiteSetting extends Model
{
    protected $fillable = [
        'site_name',
        'tagline',
        'logo_path',
        'support_email',
        'support_phone',
    ];

    public static function current(): self
    {
        if (Schema::hasTable('site_settings') && ($settings = static::query()->first())) {
            return $settings;
        }

        return new static([
            'site_name' => 'Vua Beach',
            'tagline' => 'Đồ bơi hiện đại cho mọi hành trình mùa hè.',
            'support_email' => 'sp.doitheauto5s@gmail.com',
            'support_phone' => '0969 999 999',
        ]);
    }

    public function getLogoUrlAttribute(): ?string
    {
        // Use the host of the current request so the uploaded logo works both
        // locally and through the public HTTPS tunnel. The filesystem disk URL
        // is based on APP_URL and may otherwise point localhost to an old tunnel.
        return $this->logo_path ? url('/storage/'.ltrim($this->logo_path, '/')) : null;
    }
}
