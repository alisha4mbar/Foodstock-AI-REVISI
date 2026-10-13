<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distribusi extends Model
{
    protected $table = 'distribusi';
    protected $fillable = ['origin_wilayah_id', 'destination_wilayah_id', 'komoditas_id', 'quantity', 'distributed_at', 'status', 'recorded_by'];
    protected $casts = ['quantity' => 'float', 'distributed_at' => 'date:Y-m-d'];

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
