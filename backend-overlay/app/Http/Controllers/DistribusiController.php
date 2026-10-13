<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Models\Distribusi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pencatatan distribusi oleh Distributor (dibutuhkan halaman Input Distribusi pada frontend). */
class DistribusiController extends Controller
{
    private function out(Distribusi $d): array
    {
        return [
            'id' => $d->id, 'origin_wilayah_id' => $d->origin_wilayah_id, 'destination_wilayah_id' => $d->destination_wilayah_id,
            'komoditas_id' => $d->komoditas_id, 'quantity' => $d->quantity, 'distributed_at' => $d->distributed_at->toDateString(),
            'status' => $d->status, 'recorded_by' => $d->recorder?->name,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(Presenter::paginated(Distribusi::with('recorder')->orderByDesc('distributed_at')->orderByDesc('id'), $request, fn ($d) => $this->out($d)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin_wilayah_id' => 'required|exists:wilayah,id',
            'destination_wilayah_id' => 'required|different:origin_wilayah_id|exists:wilayah,id',
            'komoditas_id' => 'required|exists:komoditas,id',
            'quantity' => 'required|numeric|min:0',
            'distributed_at' => 'required|date',
            'status' => 'sometimes|in:rencana,dikirim,selesai',
        ]);
        $d = Distribusi::create($data + ['status' => 'rencana', 'recorded_by' => $request->user()->id]);

        return response()->json(['data' => $this->out($d->load('recorder'))], 201);
    }

    public function updateStatus(Request $request, Distribusi $distribusi): JsonResponse
    {
        $distribusi->update($request->validate(['status' => 'required|in:rencana,dikirim,selesai']));

        return response()->json(['data' => $this->out($distribusi->load('recorder'))]);
    }
}
