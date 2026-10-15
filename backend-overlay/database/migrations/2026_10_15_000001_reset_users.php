<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Reset satu kali: menghapus semua pengguna lama beserta token login. Setelah ini,
// Admin Utama dibuat saat login pertama (FOODSTOCK_ADMIN_EMAILS) dan akun per peran
// dibuat oleh `php artisan foodstock:provision-accounts` (password dari env).
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')->delete();
        }
        if (Schema::hasTable('users')) {
            DB::table('users')->delete();
        }
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // tidak dapat dikembalikan
    }
};
