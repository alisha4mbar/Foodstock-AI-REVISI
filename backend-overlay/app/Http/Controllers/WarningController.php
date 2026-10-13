<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Models\HasilForecast;
use App\Models\Rekomendasi;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** FR-05: peringatan = baris hasil_forecast (run terbaru) berstatus shortage/surplus. */
class WarningController extends Controller
{
    /** Satu peringatan per kombinasi wilayah x komoditas: periode terdekat yang berstatus non-aman. */
    public function index(): JsonResponse
    {
        $rows = HasilForecast::with(['wilayah', 'komoditas', 'logs', 'followedUpBy'])->latestRun()
            ->whereIn('status', ['shortage', 'surplus'])->orderBy('periode')->get()
            ->groupBy(fn ($r) => $r->wilayah_id.':'.$r->komoditas_id)
            ->map(fn ($g) => $g->first())->values();

        $recs = Rekomendasi::with('source')->whereIn('forecast_id', $rows->pluck('id'))->get()->keyBy('forecast_id');

        return response()->json(['data' => $rows->map(fn ($f) => Presenter::warning($f, $recs->get($f->id)))->values()]);
    }

    public function followUp(Request $request, HasilForecast $forecast): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:2000', 'result' => 'nullable|string|max:2000']);
        $forecast->update([
            'followed_up_at' => now(),
            'followed_up_by' => $request->user()->id,
            'followup_note' => $data['note'] ?? $forecast->followup_note,
            'followup_result' => $data['result'] ?? $forecast->followup_result,
        ]);
        AuditService::log('Mencatat tindak lanjut peringatan', 'hasil_forecast', $forecast->id, null, $data);

        return response()->json(['message' => 'Peringatan ditandai sudah ditindaklanjuti.']);
    }

    /** Kirim ulang email peringatan (Admin). Pengiriman baru dicatat sebagai baris notification_logs baru. */
    public function retryEmail(HasilForecast $forecast, NotificationService $svc): JsonResponse
    {
        $result = $svc->notify($forecast->load(['wilayah', 'komoditas']));
        AuditService::log('Kirim ulang email peringatan', 'hasil_forecast', $forecast->id, null, $result);

        return response()->json(['data' => $result]);
    }
}
