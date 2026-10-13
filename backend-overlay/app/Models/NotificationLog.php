<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = ['user_id', 'forecast_id', 'channel', 'recipient', 'status', 'error_message', 'sent_at'];
    protected $casts = ['sent_at' => 'datetime'];

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(HasilForecast::class, 'forecast_id');
    }
}
