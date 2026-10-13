<?php

namespace App\Http;

use App\Models\HasilForecast;
use App\Models\Rekomendasi;
use App\Models\User;
use Illuminate\Http\Request;

/** Bentuk JSON yang konsisten untuk semua endpoint (NFR-09). */
class Presenter
{
    public static function paginated($query, Request $request, callable $map): array
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 5000));
        $p = $query->paginate($perPage, ['*'], 'page', max(1, (int) $request->query('page', 1)));

        return [
            'data' => $p->getCollection()->map($map)->values(),
            'meta' => ['page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'last_page' => $p->lastPage()],
        ];
    }

    public static function user(User $u): array
    {
        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role,
            'wilayah_id' => $u->wilayah_id, 'instansi' => $u->instansi, 'status' => $u->status,
            'created_at' => $u->created_at?->toIso8601String(),
        ];
    }

    public static function daysToEvent(HasilForecast $f): int
    {
        return max(0, (int) now()->startOfDay()->diffInDays($f->periode->copy()->startOfDay(), false));
    }

    public static function warning(HasilForecast $f, ?Rekomendasi $rec = null): array
    {
        $kind = $f->status === 'shortage' ? 'kekurangan (shortage)' : 'kelebihan (surplus)';
        $bulan = $f->periode->translatedFormat('F Y');
        $logs = $f->relationLoaded('logs') ? $f->logs : $f->logs()->get();
        $delivery = $logs->contains('status', 'SENT') ? 'terkirim' : ($logs->contains('status', 'FAILED') ? 'gagal' : 'menunggu');

        if ($rec) {
            $tindakan = "Alokasikan {$rec->quantity} {$f->komoditas->unit} dari {$rec->source->name} (jarak ".round($rec->distance)." km).";
        } else {
            $tindakan = $f->status === 'shortage'
                ? 'Koordinasikan pengadaan tambahan dari wilayah surplus terdekat.'
                : 'Pertimbangkan realokasi surplus ke wilayah dengan proyeksi kekurangan.';
        }

        return [
            'id' => $f->id,
            'forecast_id' => $f->id,
            'wilayah_id' => $f->wilayah_id,
            'komoditas_id' => $f->komoditas_id,
            'periode' => $f->periode->toDateString(),
            'status' => $f->status,
            'predicted_value' => $f->predicted_value,
            'stock_value' => $f->stock_value,
            'days_to_event' => self::daysToEvent($f),
            'message' => "Potensi {$kind} {$f->komoditas->name} di {$f->wilayah->name} pada {$bulan}.",
            'recommendation_text' => $tindakan,
            'channels' => ['dashboard', 'email'],
            'delivery_status' => $delivery,
            'followed_up' => $f->followed_up_at !== null,
            'followup_note' => $f->followup_note,
            'followup_result' => $f->followup_result,
            'followed_up_by' => $f->followedUpBy?->name,
            'created_at' => $f->run_at?->toIso8601String(),
        ];
    }

    public static function recommendation(Rekomendasi $r): array
    {
        return [
            'id' => $r->id, 'forecast_id' => $r->forecast_id, 'komoditas_id' => $r->forecast->komoditas_id,
            'source_wilayah' => ['id' => $r->source->id, 'name' => $r->source->name],
            'target_wilayah' => ['id' => $r->target->id, 'name' => $r->target->name],
            'quantity' => $r->quantity, 'distance' => $r->distance, 'status' => $r->status,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
