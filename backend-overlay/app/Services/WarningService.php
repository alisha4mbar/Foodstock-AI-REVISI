<?php

namespace App\Services;

use App\Models\Setting;

/** Menentukan status aman / shortage / surplus (Desain Arsitektur Bagian 7). */
class WarningService
{
    public function margins(): array
    {
        return [
            'shortage' => (float) Setting::read('shortage_margin_pct', config('foodstock.status.shortage_margin_pct')),
            'surplus' => (float) Setting::read('surplus_margin_pct', config('foodstock.status.surplus_margin_pct')),
        ];
    }

    /**
     * shortage : stok < max(kebutuhan, safe_stock) * (1 - margin_shortage/100)
     * surplus  : stok > kebutuhan * (1 + margin_surplus/100)      (default 1,5x)
     * aman     : selain itu
     */
    public function status(float $stock, float $demand, float $safeStock, ?float $shortagePct = null, ?float $surplusPct = null): string
    {
        if ($shortagePct === null || $surplusPct === null) {
            $m = $this->margins();
            $shortagePct ??= $m['shortage'];
            $surplusPct ??= $m['surplus'];
        }

        if ($stock < max($demand, $safeStock) * (1 - $shortagePct / 100)) {
            return 'shortage';
        }
        if ($stock > $demand * (1 + $surplusPct / 100)) {
            return 'surplus';
        }

        return 'aman';
    }
}
