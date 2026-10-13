<?php

namespace Database\Seeders;

use App\Models\Komoditas;
use App\Models\Konsumsi;
use App\Models\Stok;
use App\Models\Wilayah;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Master data = 38 provinsi x 25 komoditas (sama dengan frontend).
 * Data historis demo HANYA untuk 2 provinsi x 2 komoditas (36 bulan konsumsi + stok) dan bertanda
 * source = 'simulasi'. Kombinasi lain sengaja kosong: forecast melewatinya ("data tidak cukup") sampai
 * Admin mengimpor data BPS/Bapanas (menu Input Data -> Impor, atau POST /api/v1/import/rows).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MasterData::WILAYAH as [$name, $prov, $lat, $lon]) {
            Wilayah::updateOrCreate(['name' => $name], ['province' => $prov, 'latitude' => $lat, 'longitude' => $lon]);
        }
        foreach (MasterData::KOMODITAS as [$name, $cat, $unit, $safe]) {
            Komoditas::updateOrCreate(['name' => $name], ['unit' => $unit, 'category' => $cat, 'safe_stock' => $safe]);
        }

        $jatim = Wilayah::where('name', 'Jawa Timur')->firstOrFail();
        $jateng = Wilayah::where('name', 'Jawa Tengah')->firstOrFail();
        $beras = Komoditas::where('name', 'Beras Medium')->firstOrFail();
        $cabai = Komoditas::where('name', 'Cabai Rawit Merah')->firstOrFail();

        // Tidak ada akun demo. Admin Utama dibuat otomatis saat login dengan email di FOODSTOCK_ADMIN_EMAILS;
        // role Dinas/BULOG/Distributor diberikan Admin lewat menu Kelola Pengguna.

        // ---- 36 bulan data historis berakhir pada bulan sebelum bulan berjalan
        mt_srand(2026);
        $end = Carbon::now()->startOfMonth()->subMonth();
        $profiles = [
            // [wilayah, komoditas, basis konsumsi/bulan, amplitudo musiman, faktor stok akhir]
            [$jatim,  $beras, 1000, 0.12, 0.60],   // stok jauh di bawah kebutuhan -> SHORTAGE
            [$jateng, $beras, 1100, 0.12, 2.20],   // stok jauh di atas kebutuhan  -> SURPLUS
            [$jatim,  $cabai, 90,   0.25, 0.93],   // aman
            [$jateng, $cabai, 80,   0.25, 1.00],   // aman
        ];

        foreach ($profiles as [$w, $k, $base, $amp, $finalFactor]) {
            for ($i = 35; $i >= 0; $i--) {
                $month = $end->copy()->subMonthsNoOverflow($i);
                $t = 35 - $i;
                $season = 1 + $amp * sin(2 * M_PI * ($month->month - 1) / 12);
                $trend = 1 + 0.002 * $t;
                $noise = 1 + (mt_rand(-40, 40) / 1000);
                $qty = round($base * $season * $trend * $noise, 2);

                Konsumsi::updateOrCreate(
                    ['wilayah_id' => $w->id, 'komoditas_id' => $k->id, 'recorded_at' => $month->toDateString()],
                    ['quantity' => $qty, 'source' => 'simulasi']
                );

                $factor = $i === 0 ? $finalFactor : 1.2;
                Stok::updateOrCreate(
                    ['wilayah_id' => $w->id, 'komoditas_id' => $k->id, 'recorded_at' => $month->copy()->endOfMonth()->toDateString()],
                    ['quantity' => round($base * $trend * $factor, 2), 'source' => 'simulasi']
                );
            }
        }
    }
}
