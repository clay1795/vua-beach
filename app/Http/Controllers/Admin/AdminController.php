<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

abstract class AdminController extends Controller
{
    protected function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->is_admin, 403, 'Chỉ quản trị viên được truy cập khu vực này.');
    }

    protected function audit(string $action, ?Model $subject, string $description, array $metadata = []): void
    {
        $request = request();
        ActivityLog::create([
            'admin_user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'metadata' => $metadata ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
