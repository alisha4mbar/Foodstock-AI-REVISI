<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stok extends Model
{
    protected $table = 'stok';
    protected $fillable = ['wilayah_id', 'komoditas_id', 'quantity', 'recorded_at', 'source', 'recorded_by'];
    protected $casts = ['quantity' => 'float', 'recorded_at' => 'date:Y-m-d'];

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
