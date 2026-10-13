<?php

namespace App\Services;

use App\Models\HasilForecast;
use App\Models\Rekomendasi;
use App\Models\Wilayah;
use Illuminate\Support\Carbon;

/** FR-06: wilayah surplus -> wilayah shortage pada komoditas yang sama, diurutkan jarak (Haversine). */
class RecommendationService
{
    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    public function generate(Carbon $runAt): int
    {
        // Ambil baris periode terdekat (bulan ke-1) tiap kombinasi pada run ini.
        $rows = HasilForecast::with(['wilayah', 'komoditas'])
            ->where('run_at', $runAt)
            ->orderBy('periode')
            ->get()
            ->groupBy(fn ($r) => $r->wilayah_id.':'.$r->komoditas_id)
            ->map(fn ($g) => $g->first());

        $created = 0;
        foreach ($rows->groupBy('komoditas_id') as $perKomoditas) {
            $shortages = $perKomoditas->where('status', 'shortage');
            $surpluses = $perKomoditas->where('status', 'surplus');

            foreach ($shortages as $short) {
                $kebutuhan = max($short->predicted_value, (float) $short->komoditas->safe_stock);
                $kekurangan = $kebutuhan - $short->stock_value;
                if ($kekurangan <= 0) {
                    continue;
                }

                $candidates = $surpluses->map(function ($sur) use ($short) {
                    $sur->jarak = self::haversineKm(
                        $short->wilayah->latitude, $short->wilayah->longitude,
                        $sur->wilayah->latitude, $sur->wilayah->longitude
                    );
                    return $sur;
                })->sortBy('jarak');

                $source = $candidates->first();
                if (! $source) {
                    continue;
                }
                $kelebihan = $source->stock_value - max($source->predicted_value, (float) $source->komoditas->safe_stock);
                if ($kelebihan <= 0) {
                    continue;
                }

                $existing = Rekomendasi::where(['forecast_id' => $short->id, 'source_wilayah_id' => $source->wilayah_id])->first();
                Rekomendasi::updateOrCreate(
                    ['forecast_id' => $short->id, 'source_wilayah_id' => $source->wilayah_id],
                    [
                        'target_wilayah_id' => $short->wilayah_id,
                        'quantity' => round(min($kekurangan, $kelebihan), 2),
                        'distance' => round($source->jarak, 2),
                        // jangan menimpa keputusan petugas yang sudah diambil
                        'status' => $existing->status ?? 'menunggu',
                    ]
                );
                $created++;
            }
        }

        return $created;
    }
}
