<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    /** Impor dari browser: frontend membaca CSV/Excel lalu mengirim baris sebagai JSON. */
    public function rows(Request $request, ImportService $svc): JsonResponse
    {
        @set_time_limit(300);
        $data = $request->validate([
            'rows' => 'required|array|min:1|max:50000',
            'rows.*.jenis' => 'nullable|in:stok,konsumsi',
            'rows.*.wilayah' => 'required|string',
            'rows.*.komoditas' => 'required|string',
            'rows.*.jumlah' => 'required',
            'rows.*.tanggal' => 'required|string',
            'source' => 'sometimes|in:bps_bapanas,manual,simulasi',
        ]);
        $report = $svc->importRows($data['rows'], 'stok', $data['source'] ?? 'bps_bapanas', $request->user()->id);
        AuditService::log('Impor data historis (BPS/Bapanas)', 'import', null, null, $report);

        return response()->json(['data' => $report]);
    }

    public function store(Request $request, string $type, ImportService $svc): JsonResponse
    {
        abort_unless(in_array($type, ['stok', 'konsumsi'], true), 404);
        $data = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'source' => 'required|in:bps_bapanas,manual,simulasi',
        ]);

        try {
            $report = $svc->import($request->file('file')->getRealPath(), $type, $data['source'], $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }
        AuditService::log("Impor data {$type} ({$data['source']})", $type, null, null, $report);

        return response()->json(['data' => $report]);
    }
}
