<?php

namespace App\Services;

use App\Mail\WarningMail;
use App\Models\HasilForecast;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/** FR-05: kirim email ke role Dinas/Bulog/Distributor terkait wilayah, catat SENT/FAILED. */
class NotificationService
{
    public function recipients(HasilForecast $f)
    {
        return User::where('status', 'aktif')
            ->whereIn('role', ['dinas', 'bulog', 'distributor'])
            ->where(fn ($q) => $q->whereNull('wilayah_id')->orWhere('wilayah_id', $f->wilayah_id))
            ->get();
    }

    public function notify(HasilForecast $f): array
    {
        $sent = 0;
        $failed = 0;

        foreach ($this->recipients($f) as $user) {
            try {
                Mail::to($user->email)->send(new WarningMail($f));
                NotificationLog::create([
                    'user_id' => $user->id, 'forecast_id' => $f->id, 'channel' => 'email',
                    'recipient' => $user->email, 'status' => 'SENT', 'sent_at' => now(),
                ]);
                $sent++;
            } catch (\Throwable $e) {
                // Kegagalan email tidak membatalkan forecast; cukup dicatat.
                NotificationLog::create([
                    'user_id' => $user->id, 'forecast_id' => $f->id, 'channel' => 'email',
                    'recipient' => $user->email, 'status' => 'FAILED',
                    'error_message' => mb_substr($e->getMessage(), 0, 500), 'sent_at' => null,
                ]);
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
