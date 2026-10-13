<?php

namespace App\Http\Controllers;

use App\Models\HasilForecast;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\WarningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ambang status early warning yang dapat diubah Admin tanpa menyentuh kode. */
class SettingController extends Controller
{
    private function payload(WarningService $w): array
    {
        $m = $w->margins();
        $rows = HasilForecast::latestRun()->get();

        return [
            'shortage_margin_pct' => $m['shortage'],
            'surplus_margin_pct' => $m['surplus'],
            'horizon_months' => config('foodstock.forecast.horizon'),
            'model_version' => config('foodstock.forecast.model_version'),
            'mape_average' => $rows->count() ? round($rows->avg('mape'), 2) : null,
            'last_run_at' => $rows->max('run_at')?->toIso8601String(),
        ];
    }

    public function show(WarningService $w): JsonResponse
    {
        return response()->json(['data' => $this->payload($w)]);
    }

    public function update(Request $request, WarningService $w): JsonResponse
    {
        $data = $request->validate([
            'shortage_margin_pct' => 'sometimes|numeric|min:0|max:100',
            'surplus_margin_pct' => 'sometimes|numeric|min:0|max:500',
        ]);
        $old = $w->margins();
        foreach ($data as $k => $v) Setting::write($k, $v);
        AuditService::log('Mengubah parameter early warning', 'settings', null, $old, $data);

        return response()->json(['data' => $this->payload($w)]);
    }
}
