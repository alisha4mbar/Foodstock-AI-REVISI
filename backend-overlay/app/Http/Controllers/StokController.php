<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Models\Stok;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StokController extends Controller
{
    private function out(Stok $s): array
    {
        return [
            'id' => $s->id, 'wilayah_id' => $s->wilayah_id, 'komoditas_id' => $s->komoditas_id,
            'quantity' => $s->quantity, 'recorded_at' => $s->recorded_at->toDateString(), 'source' => $s->source,
            'recorded_by' => $s->recorder?->name,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $q = Stok::with('recorder')->orderByDesc('recorded_at')->orderByDesc('id');
        if ($v = $request->query('wilayah_id')) $q->where('wilayah_id', $v);
        if ($v = $request->query('komoditas_id')) $q->where('komoditas_id', $v);

        return response()->json(Presenter::paginated($q, $request, fn ($s) => $this->out($s)));
    }

    public function update(Request $request, Stok $stok): JsonResponse
    {
        $data = $request->validate(['quantity' => 'required|numeric|min:0']);
        $old = $stok->quantity;
        $stok->update($data);
        AuditService::log('Mengubah data stok', 'stok', $stok->id, ['quantity' => $old], $data);

        return response()->json(['data' => $this->out($stok->load('recorder'))]);
    }

    public function destroy(Stok $stok): JsonResponse
    {
        AuditService::log('Menghapus data stok', 'stok', $stok->id, $stok->only(['wilayah_id', 'komoditas_id', 'quantity', 'recorded_at']));
        $stok->delete();

        return response()->json(['message' => 'Data stok dihapus.']);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wilayah_id' => 'required|exists:wilayah,id',
            'komoditas_id' => 'required|exists:komoditas,id',
            'quantity' => 'required|numeric|min:0',
            'recorded_at' => 'required|date',
            'source' => 'sometimes|in:bps_bapanas,manual,simulasi,gudang_bulog',
        ]);
        // Stok yang diinput petugas Bulog otomatis bertanda gudang_bulog (ditentukan server, bukan klien).
        $data['source'] = $request->user()->role === 'bulog' ? 'gudang_bulog' : ($data['source'] ?? 'manual');
        $s = Stok::create($data + ['recorded_by' => $request->user()->id]);

        return response()->json(['data' => $this->out($s->load('recorder'))], 201);
    }
}
