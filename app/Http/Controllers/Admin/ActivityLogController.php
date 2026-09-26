<?php

namespace App\Http\Controllers\Admin;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends AdminController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $logs = ActivityLog::with('adminUser')
            ->when($request->filled('q'), fn ($query) => $query->where('description', 'like', '%'.$request->string('q').'%'))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.activity-logs.index', compact('logs'));
    }
}
