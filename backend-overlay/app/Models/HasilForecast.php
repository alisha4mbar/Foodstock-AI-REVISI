<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HasilForecast extends Model
{
    protected $table = 'hasil_forecast';
    protected $fillable = [
        'wilayah_id', 'komoditas_id', 'periode', 'predicted_value', 'stock_value',
        'status', 'mape', 'model_version', 'run_at', 'followed_up_at', 'followed_up_by', 'followup_note', 'followup_result',
    ];
    protected $casts = [
        'periode' => 'date:Y-m-d',
        'predicted_value' => 'float',
        'stock_value' => 'float',
        'mape' => 'float',
        'run_at' => 'datetime',
        'followed_up_at' => 'datetime',
    ];

    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class);
    }

    public function komoditas(): BelongsTo
    {
        return $this->belongsTo(Komoditas::class);
    }

    public function followedUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followed_up_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'forecast_id');
    }

    /** Hanya baris dari proses forecast terbaru per kombinasi wilayah x komoditas. */
    public function scopeLatestRun($q)
    {
        return $q->whereRaw(
            'hasil_forecast.run_at = (select max(h2.run_at) from hasil_forecast h2 '
            .'where h2.wilayah_id = hasil_forecast.wilayah_id and h2.komoditas_id = hasil_forecast.komoditas_id)'
        );
    }
}
