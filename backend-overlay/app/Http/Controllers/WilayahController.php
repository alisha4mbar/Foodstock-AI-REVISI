<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Models\Wilayah;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WilayahController extends Controller
{
    private function out(Wilayah $w): array
    {
        return ['id' => $w->id, 'name' => $w->name, 'province' => $w->province, 'latitude' => $w->latitude, 'longitude' => $w->longitude];
    }

    private function rules(?int $ignore = null): array
    {
        return [
            'name' => 'required|string|min:2|max:255|unique:wilayah,name'.($ignore ? ",{$ignore}" : ''),
            'province' => 'required|string|min:2|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(Presenter::paginated(Wilayah::orderBy('name'), $request, fn ($w) => $this->out($w)));
    }

    public function show(Wilayah $wilayah): JsonResponse
    {
        return response()->json(['data' => $this->out($wilayah)]);
    }

    public function store(Request $request): JsonResponse
    {
        $w = Wilayah::create($request->validate($this->rules()));
        AuditService::log("Menambah wilayah {$w->name}", 'wilayah', $w->id, null, $w->toArray());

        return response()->json(['data' => $this->out($w)], 201);
    }

    public function update(Request $request, Wilayah $wilayah): JsonResponse
    {
        $old = $wilayah->toArray();
        $wilayah->update($request->validate($this->rules($wilayah->id)));
        AuditService::log("Mengubah wilayah {$wilayah->name}", 'wilayah', $wilayah->id, $old, $wilayah->toArray());

        return response()->json(['data' => $this->out($wilayah)]);
    }

    public function destroy(Wilayah $wilayah): JsonResponse
    {
        AuditService::log("Menghapus wilayah {$wilayah->name}", 'wilayah', $wilayah->id, $wilayah->toArray());
        $wilayah->delete();

        return response()->json(['message' => 'Wilayah dihapus.']);
    }
}
