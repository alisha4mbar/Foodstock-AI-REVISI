<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistribusiController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\KomoditasController;
use App\Http\Controllers\KonsumsiController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StokController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarningController;
use App\Http\Controllers\WilayahController;
use Illuminate\Support\Facades\Route;

// Laravel otomatis memberi awalan /api  =>  base URL final: /api/v1
Route::prefix('v1')->group(function () {
    // ---- publik
    Route::get('/health', HealthController::class);
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');

    // ---- terautentikasi (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::put('/auth/password', [AuthController::class, 'password']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Semua rute di bawah ini hanya untuk akun yang sudah diverifikasi (status aktif).
        Route::middleware('active')->group(function () {

        // master data: semua role boleh membaca (FR-08)
        Route::get('/wilayah', [WilayahController::class, 'index']);
        Route::get('/wilayah/{wilayah}', [WilayahController::class, 'show']);
        Route::get('/komoditas', [KomoditasController::class, 'index']);
        Route::get('/komoditas/{komoditas}', [KomoditasController::class, 'show']);

        // data operasional (baca: semua role)
        Route::get('/stok', [StokController::class, 'index']);
        Route::get('/konsumsi', [KonsumsiController::class, 'index']);
        Route::get('/distribusi', [DistribusiController::class, 'index']);
        Route::get('/forecast', [ForecastController::class, 'index']);
        Route::get('/forecast/series', [ForecastController::class, 'series']);
        Route::get('/forecast/accuracy', [ForecastController::class, 'accuracy']);
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('/warnings', [WarningController::class, 'index']);
        Route::post('/warnings/{forecast}/follow-up', [WarningController::class, 'followUp'])->middleware('role:admin,dinas,bulog');

        // input data
        Route::post('/stok', [StokController::class, 'store'])->middleware('role:admin,dinas,bulog');
        Route::put('/stok/{stok}', [StokController::class, 'update'])->middleware('role:admin,dinas');
        Route::delete('/stok/{stok}', [StokController::class, 'destroy'])->middleware('role:admin,dinas');
        Route::post('/konsumsi', [KonsumsiController::class, 'store'])->middleware('role:admin,dinas');
        Route::put('/konsumsi/{konsumsi}', [KonsumsiController::class, 'update'])->middleware('role:admin,dinas');
        Route::delete('/konsumsi/{konsumsi}', [KonsumsiController::class, 'destroy'])->middleware('role:admin,dinas');
        Route::post('/distribusi', [DistribusiController::class, 'store'])->middleware('role:admin,distributor');
        Route::patch('/distribusi/{distribusi}', [DistribusiController::class, 'updateStatus'])->middleware('role:admin,distributor');

        // menjalankan forecast: Admin dan Petugas Dinas (UI Dinas memuat tombolnya; dokumen desain menyebut Admin saja)
        Route::post('/forecast/run', [ForecastController::class, 'run'])->middleware(['role:admin,dinas', 'throttle:6,1']);

        // rekomendasi & riwayat notifikasi
        Route::middleware('role:admin,dinas,bulog')->group(function () {
            Route::get('/recommendations', [RecommendationController::class, 'index']);
            Route::patch('/recommendations/{rekomendasi}', [RecommendationController::class, 'update']);
            Route::get('/notifications', [NotificationController::class, 'index']);
        });

        // ---- khusus Admin
        Route::middleware('role:admin')->group(function () {
            Route::post('/wilayah', [WilayahController::class, 'store']);
            Route::put('/wilayah/{wilayah}', [WilayahController::class, 'update']);
            Route::delete('/wilayah/{wilayah}', [WilayahController::class, 'destroy']);
            Route::post('/komoditas', [KomoditasController::class, 'store']);
            Route::put('/komoditas/{komoditas}', [KomoditasController::class, 'update']);
            Route::delete('/komoditas/{komoditas}', [KomoditasController::class, 'destroy']);

            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::put('/users/{user}', [UserController::class, 'update']);
            Route::post('/users/{user}/verify', [UserController::class, 'verify']);
            Route::post('/users/{user}/toggle-lock', [UserController::class, 'toggleLock']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);

            Route::post('/import/rows', [ImportController::class, 'rows']);
            Route::post('/import/{type}', [ImportController::class, 'store']);
            Route::get('/health/details', [HealthController::class, 'details']);
            Route::post('/warnings/{forecast}/retry-email', [WarningController::class, 'retryEmail']);
            Route::get('/settings/model', [SettingController::class, 'show']);
            Route::put('/settings/model', [SettingController::class, 'update']);
            Route::get('/audit-logs', [AuditLogController::class, 'index']);
        });
        });
    });
});
