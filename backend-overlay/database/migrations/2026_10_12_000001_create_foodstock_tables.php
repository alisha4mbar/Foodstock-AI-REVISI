<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('province');
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreign('wilayah_id')->references('id')->on('wilayah')->nullOnDelete();
        });

        Schema::create('komoditas', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('unit', 20)->default('ton');
            $t->string('category', 30)->default('pokok');
            $t->decimal('safe_stock', 14, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('stok', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wilayah_id')->constrained('wilayah')->cascadeOnDelete();
            $t->foreignId('komoditas_id')->constrained('komoditas')->cascadeOnDelete();
            $t->decimal('quantity', 14, 2);
            $t->date('recorded_at');
            $t->string('source', 20)->default('manual');      // bps_bapanas|manual|simulasi
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['wilayah_id', 'komoditas_id', 'recorded_at']);
        });

        Schema::create('konsumsi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wilayah_id')->constrained('wilayah')->cascadeOnDelete();
            $t->foreignId('komoditas_id')->constrained('komoditas')->cascadeOnDelete();
            $t->decimal('quantity', 14, 2);
            $t->date('recorded_at');                           // selalu tanggal 1 pada bulan tsb
            $t->string('source', 20)->default('manual');
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['wilayah_id', 'komoditas_id', 'recorded_at']);   // satu nilai per bulan
        });

        // Tambahan di luar 9 tabel dokumen: dibutuhkan halaman Input Distribusi pada frontend.
        Schema::create('distribusi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('origin_wilayah_id')->constrained('wilayah')->cascadeOnDelete();
            $t->foreignId('destination_wilayah_id')->constrained('wilayah')->cascadeOnDelete();
            $t->foreignId('komoditas_id')->constrained('komoditas')->cascadeOnDelete();
            $t->decimal('quantity', 14, 2);
            $t->date('distributed_at');
            $t->string('status', 20)->default('rencana');     // rencana|dikirim|selesai
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('hasil_forecast', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wilayah_id')->constrained('wilayah')->cascadeOnDelete();
            $t->foreignId('komoditas_id')->constrained('komoditas')->cascadeOnDelete();
            $t->date('periode');                                // tanggal 1 bulan yang diproyeksikan
            $t->decimal('predicted_value', 14, 2);              // proyeksi kebutuhan
            $t->decimal('stock_value', 14, 2);                  // stok terakhir saat forecast dijalankan
            $t->string('status', 10);                           // aman|shortage|surplus
            $t->decimal('mape', 6, 2)->nullable();
            $t->string('model_version', 50);
            $t->timestamp('run_at');                            // penanda satu kali proses forecast
            $t->timestamp('followed_up_at')->nullable();
            $t->text('followup_note')->nullable();
            $t->text('followup_result')->nullable();
            $t->foreignId('followed_up_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['wilayah_id', 'komoditas_id', 'periode']);   // cegah peringatan ganda
            $t->index(['status', 'periode']);
        });

        Schema::create('notification_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('forecast_id')->constrained('hasil_forecast')->cascadeOnDelete();
            $t->string('channel', 20)->default('email');
            $t->string('recipient');
            $t->string('status', 10);                           // SENT|FAILED
            $t->text('error_message')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });

        Schema::create('rekomendasi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('forecast_id')->constrained('hasil_forecast')->cascadeOnDelete();
            $t->foreignId('source_wilayah_id')->constrained('wilayah')->cascadeOnDelete();
            $t->foreignId('target_wilayah_id')->constrained('wilayah')->cascadeOnDelete();
            $t->decimal('quantity', 14, 2);
            $t->decimal('distance', 10, 2);                     // km (Haversine)
            $t->string('status', 20)->default('menunggu');      // menunggu|disetujui|eskalasi|selesai
            $t->timestamps();
            $t->unique(['forecast_id', 'source_wilayah_id']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action');
            $t->string('entity')->nullable();
            $t->string('entity_id')->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->string('value');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'audit_logs', 'rekomendasi', 'notification_logs', 'hasil_forecast', 'distribusi', 'konsumsi', 'stok', 'komoditas'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['wilayah_id']));
        Schema::dropIfExists('wilayah');
    }
};
