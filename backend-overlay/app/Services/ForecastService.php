<?php

namespace App\Services;

use App\Models\HasilForecast;
use App\Models\Komoditas;
use App\Models\Konsumsi;
use App\Models\Stok;
use App\Models\Wilayah;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FR-03. Holt-Winters aditif (musiman 12 bulan) murni PHP.
 * Metode forecast()/mape()/selectParameters() murni (tanpa DB) sehingga mudah diuji unit.
 */
class ForecastService
{
    public function __construct(
        private WarningService $warnings,
        private NotificationService $notifications,
        private RecommendationService $recommendations,
    ) {
    }

    // ------------------------------------------------------------------ matematika

    /** @param float[] $y deret bulanan berurutan (indeks 0..n-1) */
    public function forecast(array $y, int $horizon = 3, int $m = 12, float $a = 0.3, float $b = 0.1, float $g = 0.2): array
    {
        $y = array_values($y);
        $n = count($y);
        if ($n < 2 * $m) {
            throw new \InvalidArgumentException("Holt-Winters membutuhkan minimal {$m}*2 titik data.");
        }

        $first = array_sum(array_slice($y, 0, $m)) / $m;
        $second = array_sum(array_slice($y, $m, $m)) / $m;
        $level = $first;
        $trend = ($second - $first) / $m;
        $season = [];
        for ($i = 0; $i < $m; $i++) {
            $season[$i] = $y[$i] - $first;
        }

        for ($t = $m; $t < $n; $t++) {
            $s = $season[$t % $m];
            $prev = $level;
            $level = $a * ($y[$t] - $s) + (1 - $a) * ($level + $trend);
            $trend = $b * ($level - $prev) + (1 - $b) * $trend;
            $season[$t % $m] = $g * ($y[$t] - $level) + (1 - $g) * $s;
        }

        $out = [];
        for ($h = 1; $h <= $horizon; $h++) {
            $out[] = $level + $h * $trend + $season[($n + $h - 1) % $m];
        }

        return $out;
    }

    public function mape(array $actual, array $pred): float
    {
        $sum = 0.0;
        $cnt = 0;
        foreach (array_values($actual) as $i => $v) {
            if ($v == 0) {
                continue;
            }
            $sum += abs($v - $pred[$i]) / abs($v);
            $cnt++;
        }

        return $cnt ? ($sum / $cnt) * 100 : 0.0;
    }

    /** Pencarian grid sederhana pada data latih; mengembalikan [a, b, g, mape_holdout]. */
    public function selectParameters(array $train, array $test): array
    {
        $grid = config('foodstock.forecast.grid');
        $m = config('foodstock.forecast.season_length');
        $best = null;

        foreach ($grid['alpha'] as $a) {
            foreach ($grid['beta'] as $b) {
                foreach ($grid['gamma'] as $g) {
                    $pred = $this->forecast($train, count($test), $m, $a, $b, $g);
                    $err = $this->mape($test, $pred);
                    if ($best === null || $err < $best[3]) {
                        $best = [$a, $b, $g, $err];
                    }
                }
            }
        }

        return $best;
    }

    // ------------------------------------------------------------------ orkestrasi

    /**
     * Menjalankan forecast untuk semua kombinasi wilayah x komoditas.
     * Mengembalikan ringkasan: processed, skipped (dengan alasan), warnings, emails.
     */
    public function runAll(): array
    {
        $cfg = config('foodstock.forecast');
        $runAt = now()->startOfSecond(); // MySQL TIMESTAMP tanpa pecahan detik
        $processed = [];
        $skipped = [];
        $newWarnings = [];

        $komoditasAll = Komoditas::orderBy('id')->get();
        foreach (Wilayah::orderBy('id')->get() as $w) {
            foreach ($komoditasAll as $k) {
                $label = "{$w->name} / {$k->name}";

                $byMonth = [];
                foreach (Konsumsi::where('wilayah_id', $w->id)->where('komoditas_id', $k->id)->orderBy('recorded_at')->get() as $row) {
                    $byMonth[$row->recorded_at->format('Y-m')] = (float) $row->quantity;
                }

                if (count($byMonth) < $cfg['min_points']) {
                    $skipped[] = ['wilayah' => $w->name, 'komoditas' => $k->name,
                        'reason' => 'DATA_TIDAK_CUKUP', 'detail' => count($byMonth)." titik bulanan (minimal {$cfg['min_points']}) pada {$label}"];
                    continue;
                }

                $gaps = $this->missingMonths(array_keys($byMonth));
                if ($gaps) {
                    $skipped[] = ['wilayah' => $w->name, 'komoditas' => $k->name,
                        'reason' => 'DATA_TIDAK_LENGKAP', 'detail' => 'Bulan kosong: '.implode(', ', $gaps)];
                    continue;
                }

                $latestStock = Stok::where('wilayah_id', $w->id)->where('komoditas_id', $k->id)
                    ->orderByDesc('recorded_at')->orderByDesc('id')->first();
                if (! $latestStock) {
                    $skipped[] = ['wilayah' => $w->name, 'komoditas' => $k->name,
                        'reason' => 'STOK_BELUM_ADA', 'detail' => "Belum ada data stok untuk {$label}"];
                    continue;
                }

                $values = array_values($byMonth);
                $train = array_slice($values, 0, -$cfg['holdout']);
                $test = array_slice($values, -$cfg['holdout']);
                [$a, $b, $g, $mape] = $this->selectParameters($train, $test);
                $pred = $this->forecast($values, $cfg['horizon'], $cfg['season_length'], $a, $b, $g);

                $lastMonth = Carbon::createFromFormat('Y-m-d', array_key_last($byMonth).'-01')->startOfMonth();
                $stock = (float) $latestStock->quantity;

                DB::transaction(function () use ($w, $k, $pred, $lastMonth, $stock, $mape, $runAt, $cfg, &$processed, &$newWarnings) {
                    foreach ($pred as $i => $value) {
                        $periode = $lastMonth->copy()->addMonthsNoOverflow($i + 1)->toDateString();
                        $demand = max(0, round($value, 2));
                        $status = $this->warnings->status($stock, $demand, (float) $k->safe_stock);

                        $row = HasilForecast::where(['wilayah_id' => $w->id, 'komoditas_id' => $k->id, 'periode' => $periode])->first();
                        $changed = ! $row || $row->status !== $status;

                        $row = HasilForecast::updateOrCreate(
                            ['wilayah_id' => $w->id, 'komoditas_id' => $k->id, 'periode' => $periode],
                            [
                                'predicted_value' => $demand,
                                'stock_value' => $stock,
                                'status' => $status,
                                'mape' => round($mape, 2),
                                'model_version' => $cfg['model_version'],
                                'run_at' => $runAt,
                                // status berubah -> peringatan baru, tindak lanjut direset
                                'followed_up_at' => $changed ? null : ($row->followed_up_at ?? null),
                                'followed_up_by' => $changed ? null : ($row->followed_up_by ?? null),
                            ]
                        );

                        if ($status !== 'aman' && $changed) {
                            $newWarnings[] = $row;
                        }
                    }
                    $processed[] = ['wilayah' => $w->name, 'komoditas' => $k->name, 'mape' => round($mape, 2)];
                });
            }
        }

        // Rekomendasi realokasi (FR-06) lalu email (FR-05). Kegagalan email tidak membatalkan forecast.
        $this->recommendations->generate($runAt);
        $emails = ['sent' => 0, 'failed' => 0];
        // Satu email per kombinasi wilayah x komoditas (periode terdekat), bukan per bulan proyeksi.
        $perCombo = collect($newWarnings)->sortBy('periode')->groupBy(fn ($r) => $r->wilayah_id.':'.$r->komoditas_id)->map(fn ($g) => $g->first());
        foreach ($perCombo as $row) {
            $r = $this->notifications->notify($row->load(['wilayah', 'komoditas']));
            $emails['sent'] += $r['sent'];
            $emails['failed'] += $r['failed'];
        }

        return [
            'run_at' => $runAt->toIso8601String(),
            'processed' => $processed,
            'skipped_total' => count($skipped),
            'skipped_by_reason' => array_count_values(array_column($skipped, 'reason')),
            'skipped' => array_slice($skipped, 0, 25), // contoh; daftar penuh bisa ribuan (38 x 25 kombinasi)
            'warnings_created' => $perCombo->count(),
            'emails' => $emails,
        ];
    }

    /** @param string[] $months daftar 'Y-m' terurut; kembalikan bulan yang bolong di tengah deret */
    public function missingMonths(array $months): array
    {
        if (count($months) < 2) {
            return [];
        }
        $have = array_flip($months);
        $cursor = new \DateTimeImmutable($months[0].'-01');
        $end = new \DateTimeImmutable(end($months).'-01');
        $missing = [];
        while ($cursor < $end) {
            $key = $cursor->format('Y-m');
            if (! isset($have[$key])) {
                $missing[] = $key;
            }
            $cursor = $cursor->modify('first day of next month');
        }

        return $missing;
    }
}
