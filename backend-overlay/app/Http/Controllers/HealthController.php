<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Models\HasilForecast;
use App\Models\Konsumsi;
use App\Models\NotificationLog;
use App\Models\Stok;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /** Ringkasan operasional untuk halaman Kesehatan Sistem (Admin). */
    public function details(): JsonResponse
    {
        return response()->json(['data' => [
            'database' => 'up',
            'last_forecast_at' => HasilForecast::max('run_at'),
            'stok_count' => Stok::count(),
            'konsumsi_count' => Konsumsi::count(),
            'email' => [
                'sent' => NotificationLog::where('status', 'SENT')->count(),
                'failed' => NotificationLog::where('status', 'FAILED')->count(),
                'total' => NotificationLog::count(),
            ],
            'users_pending' => User::where('status', 'pending')->count(),
            'server_time' => now()->toIso8601String(),
        ]]);
    }

    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
            $db = 'up';
        } catch (\Throwable) {
            $db = 'down';
        }

        return response()->json(['status' => $db === 'up' ? 'ok' : 'degraded', 'database' => $db, 'time' => now()->toIso8601String()], $db === 'up' ? 200 : 503);
    }
}
