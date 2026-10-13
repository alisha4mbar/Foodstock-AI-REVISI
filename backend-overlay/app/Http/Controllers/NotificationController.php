<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** FR-10: riwayat/log notifikasi. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = NotificationLog::with('forecast')->latest('id');

        return response()->json(Presenter::paginated($q, $request, fn ($n) => [
            'id' => $n->id, 'forecast_id' => $n->forecast_id, 'wilayah_id' => $n->forecast->wilayah_id,
            'komoditas_id' => $n->forecast->komoditas_id, 'forecast_status' => $n->forecast->status,
            'channel' => $n->channel, 'recipient' => $n->recipient, 'status' => $n->status,
            'error_message' => $n->error_message, 'sent_at' => $n->sent_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ]));
    }
}
