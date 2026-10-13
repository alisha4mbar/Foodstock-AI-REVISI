<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Models\HasilForecast;
use App\Services\AuditService;
use App\Services\ForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    /** Ringkasan per kombinasi: periode pertama yang berstatus non-aman, atau periode terdekat bila semua aman. */
    public function index(): JsonResponse
    {
        $rows = HasilForecast::latestRun()->orderBy('periode')->get()
            ->groupBy(fn ($r) => $r->wilayah_id.':'.$r->komoditas_id)
            ->map(function ($g) {
                $f = $g->first(fn ($r) => $r->status !== 'aman') ?? $g->first();
                return [
                    'id' => $f->id, 'wilayah_id' => $f->wilayah_id, 'komoditas_id' => $f->komoditas_id,
                    'periode' => $f->periode->toDateString(), 'predicted_value' => $f->predicted_value,
                    'stock_value' => $f->stock_value, 'status' => $f->status,
                    'days_to_event' => $f->status === 'aman' ? null : Presenter::daysToEvent($f),
                    'mape' => $f->mape, 'model_version' => $f->model_version,
                    'created_at' => $f->run_at?->toIso8601String(),
                ];
            })->values();

        return response()->json(['data' => $rows]);
    }

    public function series(Request $request): JsonResponse
    {
        $d = $request->validate(['wilayah_id' => 'required|exists:wilayah,id', 'komoditas_id' => 'required|exists:komoditas,id']);
        $rows = HasilForecast::latestRun()->where($d)->orderBy('periode')->get()->map(fn ($r) => [
            'periode' => $r->periode->toDateString(), 'predicted_value' => $r->predicted_value,
            'stock_value' => $r->stock_value, 'status' => $r->status,
        ]);

        return response()->json(['data' => $rows]);
    }

    public function run(ForecastService $svc): JsonResponse
    {
        @set_time_limit(300);
        $result = $svc->runAll();
        AuditService::log('Menjalankan forecast', 'hasil_forecast', null, null, [
            'processed' => count($result['processed']), 'skipped' => $result['skipped_total'], 'warnings' => $result['warnings_created'],
        ]);

        return response()->json(['data' => $result]);
    }

    public function accuracy(): JsonResponse
    {
        $rows = HasilForecast::latestRun()->get();
        $per = $rows->groupBy(fn ($r) => $r->wilayah_id.':'.$r->komoditas_id)->map(fn ($g) => $g->first());

        return response()->json(['data' => [
            'model_version' => config('foodstock.forecast.model_version'),
            'mape_average' => $per->count() ? round($per->avg('mape'), 2) : null,
            'last_run_at' => $rows->max('run_at')?->toIso8601String(),
            'combinations' => $per->count(),
        ]]);
    }
}
