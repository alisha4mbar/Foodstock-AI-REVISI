<?php

namespace Tests\Unit;

use App\Services\ForecastService;
use App\Services\NotificationService;
use App\Services\RecommendationService;
use App\Services\WarningService;
use PHPUnit\Framework\TestCase;

class ForecastServiceTest extends TestCase
{
    private function svc(): ForecastService
    {
        $warn = new WarningService();
        return new ForecastService($warn, new NotificationService(), new RecommendationService());
    }

    public function test_holt_winters_menangkap_pola_musiman(): void
    {
        $y = [];
        for ($i = 0; $i < 36; $i++) {
            $y[] = 1000 + 3 * $i + 150 * sin(2 * M_PI * ($i % 12) / 12);
        }
        $pred = $this->svc()->forecast($y, 3, 12, 0.3, 0.05, 0.3);
        $actual = [];
        for ($i = 36; $i < 39; $i++) {
            $actual[] = 1000 + 3 * $i + 150 * sin(2 * M_PI * ($i % 12) / 12);
        }
        $this->assertLessThan(5.0, $this->svc()->mape($actual, $pred));
    }

    public function test_mape_nol_untuk_prediksi_sempurna_dan_abaikan_aktual_nol(): void
    {
        $this->assertEquals(0.0, $this->svc()->mape([10, 20], [10, 20]));
        $this->assertEquals(10.0, $this->svc()->mape([100, 0], [110, 5]));
    }

    public function test_bulan_kosong_terdeteksi(): void
    {
        $this->assertSame(['2025-03'], $this->svc()->missingMonths(['2025-01', '2025-02', '2025-04']));
        $this->assertSame([], $this->svc()->missingMonths(['2025-01', '2025-02', '2025-03']));
    }

    public function test_aturan_status(): void
    {
        $w = new WarningService();
        $this->assertSame('shortage', $w->status(500, 1000, 800, 0.0, 50.0));
        $this->assertSame('surplus', $w->status(1600, 1000, 800, 0.0, 50.0));
        $this->assertSame('aman', $w->status(1200, 1000, 800, 0.0, 50.0));
        // safe_stock lebih besar dari kebutuhan menentukan batas shortage
        $this->assertSame('shortage', $w->status(700, 500, 800, 0.0, 50.0));
    }

    public function test_haversine_madiun_ngawi(): void
    {
        $km = RecommendationService::haversineKm(-7.6298, 111.5239, -7.4041, 111.4462);
        $this->assertEqualsWithDelta(28, $km, 3);
    }
}
