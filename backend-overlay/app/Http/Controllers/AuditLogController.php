<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = AuditLog::with('user')->orderByDesc('id');

        return response()->json(Presenter::paginated($q, $request, fn ($a) => [
            'id' => $a->id, 'actor_name' => $a->user?->name ?? 'Sistem', 'actor_role' => $a->user?->role,
            'action' => $a->action, 'entity' => $a->entity, 'entity_id' => $a->entity_id,
            'old_values' => $a->old_values, 'new_values' => $a->new_values, 'created_at' => $a->created_at?->toIso8601String(),
        ]));
    }
}
