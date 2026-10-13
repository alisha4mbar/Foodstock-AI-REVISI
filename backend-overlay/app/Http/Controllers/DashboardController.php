<?php

namespace App\Http\Controllers;

use App\Models\HasilForecast;
use App\Models\Stok;
use Illuminate\Http\JsonResponse;

/** FR-04: membaca hasil tersimpan (tanpa menghitung model saat request) agar respons < 3 detik. */
class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $rows = HasilForecast::latestRun()->orderBy('periode')->get();
        $per = $rows->groupBy(fn ($r) => $r->wilayah_id.':'.$r->komoditas_id)
            ->map(fn ($g) => $g->first(fn ($r) => $r->status !== 'aman') ?? $g->first());

        return response()->json(['data' => [
            'has_forecast' => $rows->isNotEmpty(),
            'last_forecast_at' => $rows->max('run_at')?->toIso8601String(),
            'last_stock_update' => Stok::max('recorded_at'),
            'counts' => [
                'aman' => $per->where('status', 'aman')->count(),
                'shortage' => $per->where('status', 'shortage')->count(),
                'surplus' => $per->where('status', 'surplus')->count(),
            ],
            'uses_simulated_stock' => Stok::where('source', 'simulasi')->exists(),
        ]]);
    }
}
