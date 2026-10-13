<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Membuat akun awal per peran (dinas, bulog, distributor) berdomain @foodstock.com.
 * Password TIDAK ditulis di kode: dibaca dari env FOODSTOCK_ROLE_PASSWORD. Tanpa env itu,
 * perintah ini tidak melakukan apa pun. Idempoten: akun yang sudah ada tidak diubah.
 * Admin Utama dibuat otomatis saat login pertama dengan email di FOODSTOCK_ADMIN_EMAILS.
 */
class ProvisionAccounts extends Command
{
    protected $signature = 'foodstock:provision-accounts';
    protected $description = 'Buat akun awal per peran (@foodstock.com) dengan password dari env FOODSTOCK_ROLE_PASSWORD';

    public function handle(): int
    {
        $password = (string) env('FOODSTOCK_ROLE_PASSWORD', '');
        if ($password === '') {
            $this->info('FOODSTOCK_ROLE_PASSWORD kosong: pembuatan akun per peran dilewati.');
            return self::SUCCESS;
        }
        if (strlen($password) < 8) {
            $this->error('FOODSTOCK_ROLE_PASSWORD minimal 8 karakter.');
            return self::FAILURE;
        }

        $accounts = [
            ['dinas@foodstock.com', 'dinas', 'Petugas Dinas Ketahanan Pangan', 'Dinas Ketahanan Pangan'],
            ['bulog@foodstock.com', 'bulog', 'Petugas Bulog', 'Bulog / Badan Pangan Nasional'],
            ['distributor@foodstock.com', 'distributor', 'Petugas Distributor', 'Distributor / Pelaku Usaha'],
        ];

        foreach ($accounts as [$email, $role, $name, $instansi]) {
            if (User::where('email', $email)->exists()) {
                $this->line("Sudah ada: {$email}");
                continue;
            }
            User::create([
                'name' => $name, 'email' => $email, 'password' => $password,
                'role' => $role, 'instansi' => $instansi, 'status' => 'aktif',
            ]);
            $this->info("Dibuat: {$email} ({$role})");
        }
        return self::SUCCESS;
    }
}
