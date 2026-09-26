<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminCredentialReadiness
{
    /** @var list<string> */
    private const KNOWN_DEFAULT_PASSWORDS = [
        'admin123',
        'admin123456',
        'Admin123!',
        'password',
        '12345678',
        'changeme',
    ];

    public function usesKnownDefaultPassword(User $admin): bool
    {
        foreach (self::KNOWN_DEFAULT_PASSWORDS as $password) {
            if (Hash::check($password, $admin->password)) {
                return true;
            }
        }

        return false;
    }
}
