<?php

namespace App\Console\Commands;

use App\Services\ForecastService;
use Illuminate\Console\Command;

class ForecastRun extends Command
{
    protected $signature = 'forecast:run';
    protected $description = 'Menjalankan forecast Holt-Winters untuk semua wilayah x komoditas';

    public function handle(ForecastService $svc): int
    {
        $r = $svc->runAll();
        $this->info('Diproses: '.count($r['processed']).' | Dilewati: '.$r['skipped_total'].' | Peringatan baru: '.$r['warnings_created']);
        $this->line('Dilewati per alasan: '.json_encode($r['skipped_by_reason']));
        foreach ($r['skipped'] as $s) {
            $this->warn("{$s['wilayah']} / {$s['komoditas']}: {$s['reason']} ({$s['detail']})");
        }

        return self::SUCCESS;
    }
}
