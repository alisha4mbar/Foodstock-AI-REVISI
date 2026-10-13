<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Models\Rekomendasi;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Rekomendasi::with(['forecast', 'source', 'target'])->latest('id')->get();

        return response()->json(['data' => $rows->map([Presenter::class, 'recommendation'])->values()]);
    }

    public function update(Request $request, Rekomendasi $rekomendasi): JsonResponse
    {
        $status = $request->validate(['status' => 'required|in:menunggu,disetujui,eskalasi,selesai'])['status'];
        $old = $rekomendasi->status;
        $rekomendasi->update(['status' => $status]);
        AuditService::log("Rekomendasi realokasi #{$rekomendasi->id}: {$old} → {$status}", 'rekomendasi', $rekomendasi->id);

        return response()->json(['data' => Presenter::recommendation($rekomendasi->load(['forecast', 'source', 'target']))]);
    }
}
