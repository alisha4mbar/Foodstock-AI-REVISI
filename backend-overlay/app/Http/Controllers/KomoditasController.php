<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Models\Komoditas;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KomoditasController extends Controller
{
    private function out(Komoditas $k): array
    {
        return ['id' => $k->id, 'name' => $k->name, 'unit' => $k->unit, 'category' => $k->category, 'safe_stock' => $k->safe_stock];
    }

    private function rules(?int $ignore = null): array
    {
        return [
            'name' => 'required|string|min:2|max:255|unique:komoditas,name'.($ignore ? ",{$ignore}" : ''),
            'unit' => 'required|in:ton,kg,kuintal',
            'category' => 'required|in:pokok,hortikultura,protein,lainnya',
            'safe_stock' => 'required|numeric|min:0',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(Presenter::paginated(Komoditas::orderBy('name'), $request, fn ($k) => $this->out($k)));
    }

    public function show(Komoditas $komoditas): JsonResponse
    {
        return response()->json(['data' => $this->out($komoditas)]);
    }

    public function store(Request $request): JsonResponse
    {
        $k = Komoditas::create($request->validate($this->rules()));
        AuditService::log("Menambah komoditas {$k->name}", 'komoditas', $k->id, null, $k->toArray());

        return response()->json(['data' => $this->out($k)], 201);
    }

    public function update(Request $request, Komoditas $komoditas): JsonResponse
    {
        $old = $komoditas->toArray();
        $komoditas->update($request->validate($this->rules($komoditas->id)));
        AuditService::log("Mengubah komoditas {$komoditas->name}", 'komoditas', $komoditas->id, $old, $komoditas->toArray());

        return response()->json(['data' => $this->out($komoditas)]);
    }

    public function destroy(Komoditas $komoditas): JsonResponse
    {
        AuditService::log("Menghapus komoditas {$komoditas->name}", 'komoditas', $komoditas->id, $komoditas->toArray());
        $komoditas->delete();

        return response()->json(['message' => 'Komoditas dihapus.']);
    }
}
