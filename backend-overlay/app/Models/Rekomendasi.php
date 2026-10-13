<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rekomendasi extends Model
{
    protected $table = 'rekomendasi';
    protected $fillable = ['forecast_id', 'source_wilayah_id', 'target_wilayah_id', 'quantity', 'distance', 'status'];
    protected $casts = ['quantity' => 'float', 'distance' => 'float'];

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(HasilForecast::class, 'forecast_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'source_wilayah_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'target_wilayah_id');
    }
}
