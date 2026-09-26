<?php

namespace App\Http\Controllers;

use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(HealthCheckService $health): JsonResponse
    {
        $result = $health->run();

        return response()->json([
            'status' => $result['healthy'] ? 'ok' : 'degraded',
            'checked_at' => now()->toIso8601String(),
            'checks' => $result['checks'],
        ], $result['healthy'] ? 200 : 503);
    }
}
