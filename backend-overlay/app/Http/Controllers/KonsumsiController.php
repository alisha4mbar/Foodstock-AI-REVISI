<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Models\Konsumsi;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KonsumsiController extends Controller
{
    private function out(Konsumsi $k): array
    {
        return [
            'id' => $k->id, 'wilayah_id' => $k->wilayah_id, 'komoditas_id' => $k->komoditas_id,
            'quantity' => $k->quantity, 'recorded_at' => $k->recorded_at->toDateString(), 'source' => $k->source,
        ];
    }

    public function update(Request $request, Konsumsi $konsumsi): JsonResponse
    {
        $data = $request->validate(['quantity' => 'required|numeric|min:0']);
        $old = $konsumsi->quantity;
        $konsumsi->update($data);
        AuditService::log('Mengubah data konsumsi', 'konsumsi', $konsumsi->id, ['quantity' => $old], $data);

        return response()->json(['data' => $this->out($konsumsi)]);
    }

    public function destroy(Konsumsi $konsumsi): JsonResponse
    {
        AuditService::log('Menghapus data konsumsi', 'konsumsi', $konsumsi->id, $konsumsi->only(['wilayah_id', 'komoditas_id', 'quantity', 'recorded_at']));
        $konsumsi->delete();

        return response()->json(['message' => 'Data konsumsi dihapus.']);
    }

    public function index(Request $request): JsonResponse
    {
        $q = Konsumsi::orderByDesc('recorded_at');
        if ($v = $request->query('wilayah_id')) $q->where('wilayah_id', $v);
        if ($v = $request->query('komoditas_id')) $q->where('komoditas_id', $v);

        return response()->json(Presenter::paginated($q, $request, fn ($k) => $this->out($k)));
    }

    /** Satu nilai per wilayah, komoditas, dan bulan: input ulang memperbarui nilai (tidak menggandakan). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wilayah_id' => 'required|exists:wilayah,id',
            'komoditas_id' => 'required|exists:komoditas,id',
            'quantity' => 'required|numeric|min:0',
            'recorded_at' => 'required|date',
            'source' => 'sometimes|in:bps_bapanas,manual,simulasi',
        ]);
        $k = Konsumsi::updateOrCreate(
            ['wilayah_id' => $data['wilayah_id'], 'komoditas_id' => $data['komoditas_id'],
                'recorded_at' => Carbon::parse($data['recorded_at'])->startOfMonth()->toDateString()],
            ['quantity' => $data['quantity'], 'source' => $data['source'] ?? 'manual', 'recorded_by' => $request->user()->id]
        );

        return response()->json(['data' => $this->out($k)], $k->wasRecentlyCreated ? 201 : 200);
    }
}
